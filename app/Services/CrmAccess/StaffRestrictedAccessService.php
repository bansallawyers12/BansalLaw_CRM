<?php

namespace App\Services\CrmAccess;

use App\Models\Staff;
use App\Models\StaffAccessAudit;
use App\Models\StaffAccessProfile;
use App\Models\StaffRecordGrant;
use App\Models\UserRole;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Step 2+ helper: Restricted User role + default access profiles.
 * Enforcement (nav / lists / dashboard widgets) comes in later steps.
 */
class StaffRestrictedAccessService
{
    public function isFeatureEnabled(): bool
    {
        // Config value is hardcoded false. CRM_RESTRICTED_ACCESS_ENABLED is not read.
        return (bool) config('crm_access.restricted_access.enabled', false);
    }

    public function restrictedRoleName(): string
    {
        return trim((string) config('crm_access.restricted_access.restricted_role_name', 'Restricted User'))
            ?: 'Restricted User';
    }

    public function restrictedRoleId(): ?int
    {
        $configured = (int) config('crm_access.restricted_access.restricted_role_id', 0);
        if ($configured > 0) {
            return $configured;
        }

        $table = (new UserRole)->getTable();
        if (! Schema::hasTable($table)) {
            return null;
        }

        $id = UserRole::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower($this->restrictedRoleName())])
            ->value('id');

        return $id ? (int) $id : null;
    }

    public function isRestrictedRole(?int $roleId): bool
    {
        if (! $roleId) {
            return false;
        }

        $restrictedId = $this->restrictedRoleId();

        return $restrictedId !== null && (int) $roleId === $restrictedId;
    }

    /**
     * Create or refresh the Restricted User role with empty module_access.
     * Profile + bare dashboard are allowed via staff_access_profiles, not modules.
     */
    public function ensureRestrictedRole(): UserRole
    {
        $name = $this->restrictedRoleName();
        $moduleAccess = config('crm_access.restricted_access.default_module_access', []);
        if (! is_array($moduleAccess)) {
            $moduleAccess = [];
        }

        $role = UserRole::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower($name)])
            ->first();

        $payload = [
            'name' => $name,
            'description' => 'Local restricted shell: own profile + empty dashboard only. '
                . 'Clients, leads, matters, and emails require Super Admin grants. '
                . 'Admin is not full access.',
            'module_access' => json_encode((object) $moduleAccess, JSON_FORCE_OBJECT),
        ];

        $fullAccessIds = array_map('intval', (array) config('crm_access.restricted_access.full_access_role_ids', [1]));

        if ($role) {
            // Never overwrite Super Admin / full-access role rows if name collided.
            if (in_array((int) $role->id, $fullAccessIds, true)) {
                $role = UserRole::query()
                    ->whereNotIn('id', $fullAccessIds)
                    ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower($name)])
                    ->first();
            }
        }

        if ($role && ! in_array((int) $role->id, $fullAccessIds, true)) {
            $role->fill($payload);
            $role->save();

            return $role->fresh();
        }

        $role = new UserRole();
        $role->fill($payload);
        $role->save();

        return $role->fresh();
    }

    /**
     * Ensure a staff_access_profiles row for restricted mode (empty widgets by default).
     */
    public function ensureRestrictedProfile(Staff $staff, ?Staff $actor = null): StaffAccessProfile
    {
        $widgets = config('crm_access.restricted_access.default_dashboard_widgets', []);
        if (! is_array($widgets)) {
            $widgets = [];
        }

        $profile = StaffAccessProfile::query()->firstOrNew(['staff_id' => $staff->id]);
        $before = $profile->exists ? $profile->toArray() : null;

        $profile->access_mode = StaffAccessProfile::MODE_RESTRICTED;
        $profile->dashboard_widgets = array_values($widgets);
        $profile->can_view_own_profile = true;
        $profile->can_view_dashboard = true;
        $profile->updated_by_staff_id = $actor?->id;
        $profile->save();

        $this->audit(
            $actor?->id,
            $staff->id,
            'profile_ensured_restricted',
            null,
            null,
            $before,
            $profile->fresh()->toArray()
        );

        return $profile->fresh();
    }

    /**
     * When staff is assigned the Restricted User role, sync their access profile.
     */
    public function syncProfileIfRestrictedRole(Staff $staff, ?Staff $actor = null): ?StaffAccessProfile
    {
        if (! $this->isFeatureEnabled()) {
            return null;
        }

        if (! $this->isRestrictedRole((int) ($staff->role ?? 0))) {
            return null;
        }

        return $this->ensureRestrictedProfile($staff, $actor);
    }

    /**
     * Full CRM bypass under restricted-access mode: native Super Admin, effective super admin,
     * or the per-staff {@see Staff::hasFullCrmAllocationAccess()} grant (same flag as Staff form
     * and Staff Access "see all records").
     */
    public function hasFullCrmAccess(Staff $staff): bool
    {
        $roleIds = config('crm_access.restricted_access.full_access_role_ids', [1]);
        $roleIds = is_array($roleIds) ? array_map('intval', $roleIds) : [1];

        if (in_array((int) ($staff->role ?? 0), $roleIds, true)) {
            return true;
        }

        if (config('crm_access.restricted_access.honor_effective_super_admin', true)
            && method_exists($staff, 'hasEffectiveSuperAdminPrivileges')
            && $staff->hasEffectiveSuperAdminPrivileges()
        ) {
            return true;
        }

        if (method_exists($staff, 'hasFullCrmAllocationAccess') && $staff->hasFullCrmAllocationAccess()) {
            return true;
        }

        return false;
    }

    /**
     * After Staff save: if full allocation access is on, ensure profile is not stuck in restricted mode.
     */
    public function syncFullCrmAllocationAccessAfterStaffSave(Staff $target, bool $enabled, ?Staff $actor = null): void
    {
        if (! $enabled || ! $this->isFeatureEnabled() || ! Schema::hasTable('staff_access_profiles')) {
            return;
        }

        $profile = StaffAccessProfile::query()->where('staff_id', (int) $target->id)->first();
        if (! $profile || $profile->access_mode !== StaffAccessProfile::MODE_RESTRICTED) {
            return;
        }

        $this->updateAccessProfile(
            $target,
            StaffAccessProfile::MODE_FULL,
            [],
            (bool) ($profile->can_view_own_profile ?? true),
            (bool) ($profile->can_view_dashboard ?? true),
            $actor,
            [],
            []
        );
    }

    /**
     * Super Admin overview: what a staff member can already see (grants + nav + emails).
     *
     * @return array{
     *   clients:int,
     *   leads:int,
     *   matters:int,
     *   closed_matters:int,
     *   total_grants:int,
     *   email_people:int,
     *   can_see_emails:bool,
     *   nav_modules:array<string, list<string>>,
     *   admin_console_modules:list<string>,
     *   access_mode:?string
     * }
     */
    public function accessOverview(Staff $staff, ?StaffAccessGate $gate = null): array
    {
        $gate = $gate ?? app(StaffAccessGate::class);
        $profile = $staff->accessProfile;
        $counts = [
            StaffRecordGrant::TYPE_CLIENT => 0,
            StaffRecordGrant::TYPE_LEAD => 0,
            StaffRecordGrant::TYPE_MATTER => 0,
            StaffRecordGrant::TYPE_CLOSED_MATTER => 0,
        ];

        if (Schema::hasTable('staff_record_grants')) {
            $rows = StaffRecordGrant::query()
                ->active()
                ->where('staff_id', (int) $staff->id)
                ->selectRaw('resource_type, COUNT(*) as aggregate')
                ->groupBy('resource_type')
                ->pluck('aggregate', 'resource_type');

            foreach ($counts as $type => $_) {
                $counts[$type] = (int) ($rows[$type] ?? 0);
            }
        }

        $emailPeople = 0;
        if ($gate->isRestricted($staff) || ($counts[StaffRecordGrant::TYPE_CLIENT]
            + $counts[StaffRecordGrant::TYPE_LEAD]
            + $counts[StaffRecordGrant::TYPE_MATTER]
            + $counts[StaffRecordGrant::TYPE_CLOSED_MATTER]) > 0) {
            $emailPeople = count($gate->allowedAdminIds($staff));
        }

        $total = array_sum($counts);

        return [
            'clients' => $counts[StaffRecordGrant::TYPE_CLIENT],
            'leads' => $counts[StaffRecordGrant::TYPE_LEAD],
            'matters' => $counts[StaffRecordGrant::TYPE_MATTER],
            'closed_matters' => $counts[StaffRecordGrant::TYPE_CLOSED_MATTER],
            'total_grants' => $total,
            'email_people' => $emailPeople,
            'can_see_emails' => $emailPeople > 0,
            'nav_modules' => $profile ? $profile->navModuleActionsMap() : [],
            'admin_console_modules' => $profile ? $profile->allowedAdminConsoleModules() : [],
            'access_mode' => $profile?->access_mode,
        ];
    }

    /**
     * Known dashboard widget keys for Super Admin UI.
     *
     * @return list<string>
     */
    public function knownDashboardWidgets(): array
    {
        return [
            'active_matters',
            'closed_matters',
            'tasks',
            'recent_activity',
            'calendar',
            'access_approvals',
        ];
    }

    /**
     * Known grant scopes for Super Admin UI.
     *
     * @return list<string>
     */
    public function knownGrantScopes(): array
    {
        return [
            StaffRecordGrant::SCOPE_VIEW,
            StaffRecordGrant::SCOPE_EMAILS,
            StaffRecordGrant::SCOPE_DOCUMENTS,
            StaffRecordGrant::SCOPE_NOTES,
        ];
    }

    /**
     * Top menu bar keys Super Admin can grant to restricted staff.
     *
     * @return list<string>
     */
    public function knownNavModules(): array
    {
        return array_keys($this->knownNavModuleActions());
    }

    /**
     * Per-module actions available in Staff Access UI (view / add / edit / delete).
     *
     * @return array<string, list<string>>
     */
    public function knownNavModuleActions(): array
    {
        return [
            'clients' => ['view', 'add', 'edit', 'delete'],
            'leads' => ['view', 'add', 'edit', 'delete'],
            'accounts' => ['view'],
            'booking' => ['view', 'add', 'edit', 'delete'],
            'signatures' => ['view'],
            'office_visits' => ['view', 'add', 'edit'],
            'front_desk' => ['view'],
            'tasks' => ['view', 'add', 'edit', 'delete'],
            'unassigned_mail' => ['view'],
            'calendar' => ['view', 'add', 'edit', 'delete'],
            'search' => ['view'],
            'office_checkin' => ['view'],
            'admin_console' => ['view'],
        ];
    }

    /**
     * @return list<string>
     */
    public function knownNavActions(): array
    {
        return ['view', 'add', 'edit', 'delete'];
    }

    /**
     * Admin Console sidebar keys Super Admin can grant to restricted staff.
     *
     * @return list<string>
     */
    public function knownAdminConsoleModules(): array
    {
        return [
            'email_labels',
            'workflows',
            'emails',
            'crm_email_template',
            'offices',
            'staff',
            'teams',
            'roles',
            'document_checklist',
            'matter_list',
            'sms',
            'sms_templates',
            'esignature',
            'activity_search',
        ];
    }

    /**
     * Update access profile (mode + widgets + shell flags + nav / admin console).
     *
     * @param  list<string>  $widgets
     * @param  list<string>|array<string, list<string>>  $navModules  module list or module=>actions map
     * @param  list<string>  $adminConsoleModules
     */
    public function updateAccessProfile(
        Staff $target,
        string $accessMode,
        array $widgets,
        bool $canViewOwnProfile,
        bool $canViewDashboard,
        ?Staff $actor = null,
        array $navModules = [],
        array $adminConsoleModules = []
    ): StaffAccessProfile {
        if (! in_array($accessMode, [StaffAccessProfile::MODE_FULL, StaffAccessProfile::MODE_RESTRICTED], true)) {
            throw new InvalidArgumentException('Invalid access mode.');
        }

        $widgets = $this->filterKnownKeys($widgets, $this->knownDashboardWidgets());
        $navModules = $this->normalizeNavModuleActions($navModules);
        $adminConsoleModules = $this->filterKnownKeys($adminConsoleModules, $this->knownAdminConsoleModules());

        // Admin Console sidebar grants imply the Admin Console top-menu entry (view).
        if ($adminConsoleModules !== [] && ! isset($navModules['admin_console'])) {
            $navModules['admin_console'] = ['view'];
        }

        $profile = StaffAccessProfile::query()->firstOrNew(['staff_id' => $target->id]);
        $before = $profile->exists ? $profile->toArray() : null;

        $restricted = $accessMode === StaffAccessProfile::MODE_RESTRICTED;

        if ($restricted && Schema::hasColumn('staff', 'has_full_crm_allocation_access') && $target->hasFullCrmAllocationAccess()) {
            $target->forceFill(['has_full_crm_allocation_access' => false]);
            $target->save();
        }

        $profile->access_mode = $accessMode;
        $profile->dashboard_widgets = $restricted ? $widgets : [];
        $profile->nav_modules = $restricted ? $navModules : [];
        $profile->admin_console_modules = $restricted ? $adminConsoleModules : [];
        $profile->can_view_own_profile = $canViewOwnProfile;
        $profile->can_view_dashboard = $canViewDashboard;
        $profile->updated_by_staff_id = $actor?->id;
        $profile->save();

        if ($restricted && $this->isRestrictedRole((int) ($target->role ?? 0)) === false) {
            // Keep role as-is; profile mode alone is enough for gate. Optional role sync left to Staff page.
        }

        $this->audit(
            $actor?->id,
            $target->id,
            'profile_updated',
            'staff_access_profile',
            (int) $profile->id,
            $before,
            $profile->fresh()->toArray()
        );

        return $profile->fresh();
    }

    /**
     * Normalize nav grants to {"clients":["view","edit"], ...}.
     *
     * @param  list<string>|array<string, mixed>  $input
     * @return array<string, list<string>>
     */
    public function normalizeNavModuleActions(array $input): array
    {
        $known = $this->knownNavModuleActions();
        $map = [];

        // Legacy list: ["clients", "booking"]
        if ($input !== [] && array_is_list($input)) {
            foreach ($input as $key) {
                $key = (string) $key;
                if (! isset($known[$key])) {
                    continue;
                }
                $map[$key] = ['view'];
            }

            return $map;
        }

        foreach ($input as $key => $actions) {
            $key = (string) $key;
            if (! isset($known[$key])) {
                continue;
            }
            $allowed = $known[$key];
            if (is_bool($actions)) {
                if ($actions) {
                    $map[$key] = ['view'];
                }
                continue;
            }
            if (! is_array($actions)) {
                continue;
            }
            $normalized = array_values(array_unique(array_filter(
                array_map('strval', $actions),
                static fn (string $action) => in_array($action, $allowed, true)
            )));
            if ($normalized === []) {
                continue;
            }
            if (! in_array('view', $normalized, true) && in_array('view', $allowed, true)) {
                array_unshift($normalized, 'view');
                $normalized = array_values(array_unique($normalized));
            }
            $map[$key] = $normalized;
        }

        return $map;
    }

    /**
     * @param  list<string>  $keys
     * @param  list<string>  $known
     * @return list<string>
     */
    protected function filterKnownKeys(array $keys, array $known): array
    {
        return array_values(array_unique(array_filter(
            array_map('strval', $keys),
            static fn (string $key) => in_array($key, $known, true)
        )));
    }

    /**
     * Create or refresh an active record grant.
     *
     * @param  list<string>  $scopes
     */
    public function grantRecord(
        Staff $target,
        string $resourceType,
        int $resourceId,
        array $scopes,
        ?Staff $actor = null,
        ?string $notes = null
    ): StaffRecordGrant {
        $allowedTypes = StaffRecordGrant::grantableResourceTypes();
        if (! in_array($resourceType, $allowedTypes, true) || $resourceId <= 0) {
            throw new InvalidArgumentException('Invalid grant resource.');
        }

        $known = $this->knownGrantScopes();
        $scopes = array_values(array_unique(array_filter(
            array_map('strval', $scopes),
            static fn (string $scope) => in_array($scope, $known, true)
        )));
        if ($scopes === []) {
            $defaults = config('crm_access.restricted_access.default_grant_scopes', [StaffRecordGrant::SCOPE_VIEW]);
            $scopes = is_array($defaults) ? array_values($defaults) : [StaffRecordGrant::SCOPE_VIEW];
        }
        if (! in_array(StaffRecordGrant::SCOPE_VIEW, $scopes, true)) {
            array_unshift($scopes, StaffRecordGrant::SCOPE_VIEW);
            $scopes = array_values(array_unique($scopes));
        }

        $existing = StaffRecordGrant::query()
            ->where('staff_id', (int) $target->id)
            ->where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->whereNull('revoked_at')
            ->orderByDesc('id')
            ->first();

        $before = $existing?->toArray();

        if ($existing) {
            $existing->scopes = $scopes;
            $existing->granted_by_staff_id = $actor?->id;
            $existing->notes = $notes;
            $existing->starts_at = $existing->starts_at ?? now();
            $existing->ends_at = null;
            $existing->save();
            $grant = $existing->fresh();
            $action = 'grant_updated';
        } else {
            $grant = StaffRecordGrant::query()->create([
                'staff_id' => (int) $target->id,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'scopes' => $scopes,
                'granted_by_staff_id' => $actor?->id,
                'starts_at' => now(),
                'notes' => $notes,
            ]);
            $action = 'grant_created';
        }

        $this->audit(
            $actor?->id,
            $target->id,
            $action,
            $resourceType,
            $resourceId,
            $before,
            $grant->toArray()
        );

        return $grant;
    }

    public function revokeGrant(StaffRecordGrant $grant, ?Staff $actor = null, ?string $notes = null): StaffRecordGrant
    {
        if ($grant->revoked_at !== null) {
            return $grant;
        }

        $before = $grant->toArray();
        $grant->revoked_at = now();
        if ($notes !== null && $notes !== '') {
            $grant->notes = trim((string) $grant->notes . "\n" . $notes);
        }
        $grant->save();

        $this->audit(
            $actor?->id,
            (int) $grant->staff_id,
            'grant_revoked',
            (string) $grant->resource_type,
            (int) $grant->resource_id,
            $before,
            $grant->fresh()->toArray()
        );

        return $grant->fresh();
    }

    protected function audit(
        ?int $actorId,
        ?int $targetId,
        string $action,
        ?string $resourceType,
        ?int $resourceId,
        mixed $before,
        mixed $after
    ): void {
        if (! Schema::hasTable('staff_access_audit')) {
            return;
        }

        StaffAccessAudit::query()->create([
            'actor_staff_id' => $actorId,
            'target_staff_id' => $targetId,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'before' => $before,
            'after' => $after,
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
