<?php

namespace App\Services\CrmAccess;

use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\Staff;
use App\Models\StaffAccessProfile;
use App\Models\StaffRecordGrant;
use App\Models\UserRole;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Schema;

/**
 * Step 3 — Central access gate for restricted staff.
 *
 * Restricted users: grants-only for clients/leads/matters; modules/widgets from
 * role + profile allow-lists. Full CRM bypass: Super Admin only (not Admin).
 */
class StaffAccessGate
{
    public function __construct(
        protected StaffRestrictedAccessService $restrictedAccess
    ) {}

    public function isFeatureEnabled(): bool
    {
        return $this->restrictedAccess->isFeatureEnabled();
    }

    public function hasFullCrmAccess(Staff|Authenticatable|null $staff): bool
    {
        if (! $staff instanceof Staff) {
            return false;
        }

        return $this->restrictedAccess->hasFullCrmAccess($staff);
    }

    /**
     * Restricted = explicit profile mode, or assigned Restricted User role.
     */
    public function isRestricted(Staff|Authenticatable|null $staff): bool
    {
        if (! $staff instanceof Staff || ! $this->isFeatureEnabled()) {
            return false;
        }

        if ($this->hasFullCrmAccess($staff)) {
            return false;
        }

        if (Schema::hasTable('staff_access_profiles')) {
            $mode = StaffAccessProfile::query()
                ->where('staff_id', (int) $staff->id)
                ->value('access_mode');
            if ($mode === StaffAccessProfile::MODE_RESTRICTED) {
                return true;
            }
            if ($mode === StaffAccessProfile::MODE_FULL) {
                return false;
            }
        }

        return $this->restrictedAccess->isRestrictedRole((int) ($staff->role ?? 0));
    }

    /**
     * @return list<string>
     */
    public function moduleAccessKeys(Staff $staff): array
    {
        $roleId = (int) ($staff->role ?? 0);
        if ($roleId <= 0 || ! Schema::hasTable((new UserRole)->getTable())) {
            return [];
        }

        $raw = UserRole::query()->where('id', $roleId)->value('module_access');
        $decoded = json_decode((string) $raw, true);
        if (! is_array($decoded)) {
            return [];
        }

        return array_map('strval', array_keys($decoded));
    }

    public function canModule(Staff|Authenticatable|null $staff, int|string $moduleKey): bool
    {
        if (! $staff instanceof Staff) {
            return false;
        }

        if (! $this->isFeatureEnabled() || $this->hasFullCrmAccess($staff)) {
            return true;
        }

        if (! $this->isRestricted($staff)) {
            // Non-restricted under feature flag: defer to existing role module checks elsewhere.
            return in_array((string) $moduleKey, $this->moduleAccessKeys($staff), true);
        }

        return in_array((string) $moduleKey, $this->moduleAccessKeys($staff), true);
    }

    public function canViewOwnProfile(Staff|Authenticatable|null $staff): bool
    {
        if (! $staff instanceof Staff) {
            return false;
        }

        if (! $this->isFeatureEnabled() || $this->hasFullCrmAccess($staff) || ! $this->isRestricted($staff)) {
            return true;
        }

        $profile = $this->profileFor($staff);

        return $profile ? (bool) $profile->can_view_own_profile : true;
    }

    public function canViewDashboard(Staff|Authenticatable|null $staff): bool
    {
        if (! $staff instanceof Staff) {
            return false;
        }

        if (! $this->isFeatureEnabled() || $this->hasFullCrmAccess($staff) || ! $this->isRestricted($staff)) {
            return true;
        }

        $profile = $this->profileFor($staff);

        return $profile ? (bool) $profile->can_view_dashboard : true;
    }

    public function canDashboardWidget(Staff|Authenticatable|null $staff, string $widgetKey): bool
    {
        if (! $staff instanceof Staff) {
            return false;
        }

        if (! $this->isFeatureEnabled() || $this->hasFullCrmAccess($staff) || ! $this->isRestricted($staff)) {
            return true;
        }

        if (! $this->canViewDashboard($staff)) {
            return false;
        }

        // Closed matters stay off unless Super Admin granted a closed-matter record
        // and explicitly enabled the widget.
        if ($widgetKey === 'closed_matters') {
            if (! $this->hasClosedMatterGrants($staff)) {
                return false;
            }
        }

        $profile = $this->profileFor($staff);
        $allowed = $profile ? $profile->allowedDashboardWidgets() : [];
        if (in_array($widgetKey, $allowed, true)) {
            return true;
        }

        // Once any client/lead/matter grant exists, unlock personal productivity widgets.
        if (in_array($widgetKey, ['tasks', 'calendar', 'recent_activity', 'active_matters'], true)
            && $this->hasActiveGrants($staff)) {
            return true;
        }

        return false;
    }

    /**
     * Dashboard section visibility for the shell + AJAX endpoints.
     *
     * Known widget keys (Super Admin may grant via staff_access_profiles.dashboard_widgets):
     * active_matters, closed_matters, tasks, recent_activity, calendar, access_approvals.
     *
     * @return array{
     *   restricted: bool,
     *   shell_only: bool,
     *   active_matters: bool,
     *   closed_matters: bool,
     *   tasks: bool,
     *   recent_activity: bool,
     *   calendar: bool,
     *   access_approvals: bool,
     *   new_client: bool
     * }
     */
    public function dashboardWidgetFlags(Staff|Authenticatable|null $staff): array
    {
        $allOn = [
            'restricted' => false,
            'shell_only' => false,
            'active_matters' => true,
            'closed_matters' => true,
            'tasks' => true,
            'recent_activity' => true,
            'calendar' => true,
            'access_approvals' => true,
            'new_client' => true,
        ];

        if (! $staff instanceof Staff || ! $this->isFeatureEnabled() || $this->hasFullCrmAccess($staff) || ! $this->isRestricted($staff)) {
            return $allOn;
        }

        $flags = [
            'restricted' => true,
            'shell_only' => false,
            'active_matters' => $this->canDashboardWidget($staff, 'active_matters'),
            'closed_matters' => $this->canDashboardWidget($staff, 'closed_matters'),
            'tasks' => $this->canDashboardWidget($staff, 'tasks'),
            'recent_activity' => $this->canDashboardWidget($staff, 'recent_activity'),
            'calendar' => $this->canDashboardWidget($staff, 'calendar'),
            'access_approvals' => $this->canDashboardWidget($staff, 'access_approvals'),
            // Restricted users never create clients from the dashboard shell.
            'new_client' => false,
        ];

        $flags['shell_only'] = ! $flags['active_matters']
            && ! $flags['closed_matters']
            && ! $flags['tasks']
            && ! $flags['recent_activity']
            && ! $flags['calendar']
            && ! $flags['access_approvals'];

        return $flags;
    }

    public function canClient(Staff|Authenticatable|null $staff, int $adminId): bool
    {
        return $this->canRecord($staff, StaffRecordGrant::TYPE_CLIENT, $adminId)
            || $this->hasMatterGrantForAdmin($staff, $adminId);
    }

    public function canLead(Staff|Authenticatable|null $staff, int $adminId): bool
    {
        return $this->canRecord($staff, StaffRecordGrant::TYPE_LEAD, $adminId);
    }

    public function canMatter(Staff|Authenticatable|null $staff, int $matterId): bool
    {
        if (! $staff instanceof Staff || $matterId <= 0) {
            return false;
        }

        if ($this->hasFullCrmAccess($staff)) {
            return true;
        }

        if (! $this->isFeatureEnabled() || ! $this->isRestricted($staff)) {
            return false;
        }

        $matter = Schema::hasTable('client_matters')
            ? ClientMatter::query()->with('workflowStage')->find($matterId)
            : null;

        if ($matter && ClientMatter::isClosed($matter)) {
            // Closed matters require an explicit closed_matter grant (not client/open-matter).
            return $this->canRecord($staff, StaffRecordGrant::TYPE_CLOSED_MATTER, $matterId);
        }

        return $this->canRecord($staff, StaffRecordGrant::TYPE_MATTER, $matterId)
            || $this->canRecord($staff, StaffRecordGrant::TYPE_CLOSED_MATTER, $matterId);
    }

    /**
     * Client or lead row (admins.id) for restricted staff — grants only.
     */
    public function canClientOrLead(Staff|Authenticatable|null $staff, int $adminId, ?string $type = null): bool
    {
        if (! $staff instanceof Staff || $adminId <= 0) {
            return false;
        }

        if ($this->hasFullCrmAccess($staff)) {
            return true;
        }

        if (! $this->isRestricted($staff)) {
            return false;
        }

        $type = $type ? strtolower($type) : null;
        if ($type === 'lead') {
            return $this->canLead($staff, $adminId);
        }
        if ($type === 'client') {
            return $this->canClient($staff, $adminId);
        }

        return $this->canClient($staff, $adminId) || $this->canLead($staff, $adminId);
    }

    public function canEmails(Staff|Authenticatable|null $staff, ?int $adminId = null, ?int $matterId = null): bool
    {
        if (! $staff instanceof Staff) {
            return false;
        }

        if (! $this->isFeatureEnabled() || $this->hasFullCrmAccess($staff) || ! $this->isRestricted($staff)) {
            return true;
        }

        // Related emails follow record grants (view is enough). Optional emails scope still works.
        if ($matterId && $matterId > 0) {
            if ($this->canMatter($staff, $matterId)) {
                return true;
            }

            if ($adminId && $adminId > 0 && ($this->canClient($staff, $adminId) || $this->canLead($staff, $adminId))) {
                // Client/lead grant covers that file's emails, but not closed matters without a closed grant.
                $matter = Schema::hasTable('client_matters')
                    ? ClientMatter::query()->with('workflowStage')->find($matterId)
                    : null;
                if ($matter && ClientMatter::isClosed($matter)) {
                    return $this->canRecord($staff, StaffRecordGrant::TYPE_CLOSED_MATTER, $matterId);
                }

                return true;
            }

            return false;
        }

        if ($adminId && $adminId > 0) {
            return $this->canClient($staff, $adminId) || $this->canLead($staff, $adminId);
        }

        return false;
    }

    /**
     * Admin IDs the restricted staff may see emails for (any active grant on that client/lead/matter).
     *
     * @return list<int>
     */
    public function allowedAdminIdsWithEmailScope(Staff $staff): array
    {
        return $this->allowedAdminIds($staff);
    }

    /**
     * Whether this restricted staff has any active record grant.
     */
    public function hasActiveGrants(Staff|Authenticatable|null $staff): bool
    {
        if (! $staff instanceof Staff || ! Schema::hasTable('staff_record_grants')) {
            return false;
        }

        return StaffRecordGrant::query()
            ->active()
            ->where('staff_id', (int) $staff->id)
            ->exists();
    }

    /**
     * Whether this staff has at least one active closed-matter grant.
     */
    public function hasClosedMatterGrants(Staff|Authenticatable|null $staff): bool
    {
        if (! $staff instanceof Staff || ! Schema::hasTable('staff_record_grants')) {
            return false;
        }

        return StaffRecordGrant::query()
            ->active()
            ->where('staff_id', (int) $staff->id)
            ->where('resource_type', StaffRecordGrant::TYPE_CLOSED_MATTER)
            ->exists();
    }

    /**
     * Limit email_logs queries to granted clients / matters (assigned or linked mail only).
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    public function applyGrantedEmailLogFilter($query, Staff $staff): void
    {
        $adminIds = $this->allowedAdminIds($staff);
        $matterIds = $this->allowedMatterIds($staff);

        if ($adminIds === [] && $matterIds === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->where(function ($outer) use ($adminIds, $matterIds) {
            if ($adminIds !== []) {
                $outer->whereIn('client_id', $adminIds);
            }
            if ($matterIds !== []) {
                $method = $adminIds !== [] ? 'orWhereIn' : 'whereIn';
                $outer->{$method}('client_matter_id', $matterIds);
            }
        });
    }

    /**
     * @return list<int> admins.id visible via client/lead grants or matter→client
     */
    public function allowedAdminIds(Staff $staff): array
    {
        if (! Schema::hasTable('staff_record_grants')) {
            return [];
        }

        $ids = [];

        $grants = StaffRecordGrant::query()
            ->active()
            ->where('staff_id', (int) $staff->id)
            ->whereIn('resource_type', array_merge(
                [StaffRecordGrant::TYPE_CLIENT, StaffRecordGrant::TYPE_LEAD],
                StaffRecordGrant::matterResourceTypes()
            ))
            ->get(['resource_type', 'resource_id', 'scopes']);

        $matterIds = [];
        foreach ($grants as $grant) {
            if (in_array($grant->resource_type, [StaffRecordGrant::TYPE_CLIENT, StaffRecordGrant::TYPE_LEAD], true)) {
                $ids[] = (int) $grant->resource_id;
            } elseif (StaffRecordGrant::isMatterResourceType((string) $grant->resource_type)) {
                $matterIds[] = (int) $grant->resource_id;
            }
        }

        if ($matterIds !== [] && Schema::hasTable('client_matters')) {
            $clientIds = ClientMatter::query()
                ->whereIn('id', $matterIds)
                ->pluck('client_id')
                ->map(fn ($v) => (int) $v)
                ->all();
            $ids = array_merge($ids, $clientIds);
        }

        return array_values(array_unique(array_filter($ids, static fn (int $id) => $id > 0)));
    }

    /**
     * @return list<int>
     */
    public function allowedMatterIds(Staff $staff): array
    {
        if (! Schema::hasTable('staff_record_grants')) {
            return [];
        }

        return StaffRecordGrant::query()
            ->active()
            ->where('staff_id', (int) $staff->id)
            ->whereIn('resource_type', StaffRecordGrant::matterResourceTypes())
            ->pluck('resource_id')
            ->map(fn ($v) => (int) $v)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    protected function activeMatterGrant(Staff $staff, int $matterId): ?StaffRecordGrant
    {
        return $this->activeGrant($staff, StaffRecordGrant::TYPE_MATTER, $matterId)
            ?? $this->activeGrant($staff, StaffRecordGrant::TYPE_CLOSED_MATTER, $matterId);
    }

    protected function canRecord(Staff|Authenticatable|null $staff, string $type, int $resourceId): bool
    {
        if (! $staff instanceof Staff || $resourceId <= 0) {
            return false;
        }

        if ($this->hasFullCrmAccess($staff)) {
            return true;
        }

        if (! $this->isFeatureEnabled() || ! $this->isRestricted($staff)) {
            return false;
        }

        // Any active grant for this resource allows viewing the record.
        // Email/document scopes are checked separately (canEmails, etc.).
        return $this->activeGrant($staff, $type, $resourceId) !== null;
    }

    protected function activeGrant(Staff $staff, string $type, int $resourceId): ?StaffRecordGrant
    {
        if (! Schema::hasTable('staff_record_grants')) {
            return null;
        }

        return StaffRecordGrant::query()
            ->active()
            ->where('staff_id', (int) $staff->id)
            ->where('resource_type', $type)
            ->where('resource_id', $resourceId)
            ->first();
    }

    protected function hasMatterGrantForAdmin(Staff|Authenticatable|null $staff, int $adminId): bool
    {
        if (! $staff instanceof Staff || $adminId <= 0 || ! Schema::hasTable('client_matters')) {
            return false;
        }

        $matterIds = $this->allowedMatterIds($staff);
        if ($matterIds === []) {
            return false;
        }

        return ClientMatter::query()
            ->where('client_id', $adminId)
            ->whereIn('id', $matterIds)
            ->exists();
    }

    protected function hasMatterEmailGrantForAdmin(Staff $staff, int $adminId): bool
    {
        if (! Schema::hasTable('staff_record_grants') || ! Schema::hasTable('client_matters')) {
            return false;
        }

        $matterIds = StaffRecordGrant::query()
            ->active()
            ->where('staff_id', (int) $staff->id)
            ->whereIn('resource_type', StaffRecordGrant::matterResourceTypes())
            ->get(['resource_id', 'scopes'])
            ->filter(fn (StaffRecordGrant $g) => $g->hasScope(StaffRecordGrant::SCOPE_EMAILS))
            ->pluck('resource_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        if ($matterIds === []) {
            return false;
        }

        return ClientMatter::query()
            ->where('client_id', $adminId)
            ->whereIn('id', $matterIds)
            ->exists();
    }

    /**
     * Topbar / nav visibility flags for restricted staff.
     *
     * @return array{
     *   restricted: bool,
     *   dashboard: bool,
     *   profile: bool,
     *   clients: bool,
     *   leads: bool,
     *   accounts: bool,
     *   booking: bool,
     *   signatures: bool,
     *   office_visits: bool,
     *   front_desk: bool,
     *   tasks: bool,
     *   unassigned_mail: bool,
     *   calendar: bool,
     *   search: bool,
     *   office_checkin: bool,
     *   admin_console: bool,
     *   notifications: bool,
     *   closed_matters: bool
     * }
     */
    public function navFlags(Staff|Authenticatable|null $staff): array
    {
        $allOn = [
            'restricted' => false,
            'dashboard' => true,
            'profile' => true,
            'clients' => true,
            'leads' => true,
            'accounts' => true,
            'booking' => true,
            'signatures' => true,
            'office_visits' => true,
            'front_desk' => true,
            'tasks' => true,
            'unassigned_mail' => true,
            'calendar' => true,
            'search' => true,
            'office_checkin' => true,
            'admin_console' => true,
            'notifications' => true,
            'closed_matters' => true,
        ];

        if (! $staff instanceof Staff || ! $this->isFeatureEnabled() || $this->hasFullCrmAccess($staff) || ! $this->isRestricted($staff)) {
            return $allOn;
        }

        $adminIds = $this->allowedAdminIds($staff);
        $hasGrants = $this->hasActiveGrants($staff);
        $hasClientGrant = false;
        $hasLeadGrant = false;
        if ($adminIds !== [] && Schema::hasTable('admins')) {
            $types = Admin::query()->whereIn('id', $adminIds)->pluck('type')->all();
            $hasClientGrant = in_array('client', $types, true) || $this->allowedMatterIds($staff) !== [];
            $hasLeadGrant = in_array('lead', $types, true);
        } elseif ($this->allowedMatterIds($staff) !== []) {
            $hasClientGrant = true;
        }

        return [
            'restricted' => true,
            'dashboard' => $this->canViewDashboard($staff),
            'profile' => $this->canViewOwnProfile($staff),
            // Record grants and/or explicit top-menu grants from Staff Access.
            'clients' => $hasClientGrant || $this->canNavModule($staff, 'clients'),
            'leads' => $hasLeadGrant || $this->canNavModule($staff, 'leads'),
            'accounts' => $this->canNavModule($staff, 'accounts'),
            'booking' => $this->canNavModule($staff, 'booking'),
            'signatures' => $this->canNavModule($staff, 'signatures'),
            'office_visits' => $this->canNavModule($staff, 'office_visits'),
            'front_desk' => $this->canNavModule($staff, 'front_desk'),
            // Personal productivity unlocks once Super Admin assigns any record grant.
            'tasks' => $hasGrants || $this->canNavModule($staff, 'tasks'),
            'unassigned_mail' => $hasGrants || $this->canNavModule($staff, 'unassigned_mail'),
            'calendar' => $hasGrants || $this->canDashboardWidget($staff, 'calendar') || $this->canNavModule($staff, 'calendar'),
            'search' => $hasClientGrant || $hasLeadGrant || $this->canNavModule($staff, 'search'),
            'office_checkin' => $this->canNavModule($staff, 'office_checkin'),
            'admin_console' => $this->canAdminConsole($staff),
            'notifications' => true,
            'closed_matters' => $this->hasClosedMatterGrants($staff),
        ];
    }

    /**
     * Whether restricted staff may use a top-menu module (explicit profile grant).
     */
    public function canNavModule(Staff|Authenticatable|null $staff, string $moduleKey): bool
    {
        return $this->canNavAction($staff, $moduleKey, 'view');
    }

    /**
     * Whether restricted staff may perform a top-menu action (view / add / edit / delete).
     */
    public function canNavAction(Staff|Authenticatable|null $staff, string $moduleKey, string $action): bool
    {
        if (! $staff instanceof Staff) {
            return false;
        }

        if (! $this->isFeatureEnabled() || $this->hasFullCrmAccess($staff) || ! $this->isRestricted($staff)) {
            return true;
        }

        $profile = $this->profileFor($staff);
        if (! $profile) {
            return false;
        }

        return $profile->hasNavAction($moduleKey, $action);
    }

    /**
     * Infer required nav action from a route name under a module prefix family.
     */
    public function navActionForRoute(string $routeName): string
    {
        $routeName = strtolower($routeName);

        if (preg_match('/\.(destroy|delete|remove|revoke|unlink)(\.|$)/', $routeName)
            || str_contains($routeName, '.destroy')
            || str_contains($routeName, '.delete')
            || str_contains($routeName, 'removetag')
            || str_contains($routeName, '.remove')) {
            return 'delete';
        }

        if (preg_match('/\.(store|create|import|add)(\.|$)/', $routeName)
            || str_contains($routeName, '.store')
            || str_contains($routeName, '.create')
            || str_contains($routeName, '.import')) {
            return 'add';
        }

        if (preg_match('/\.(edit|update|save|patch|put)(\.|$)/', $routeName)
            || str_contains($routeName, '.edit')
            || str_contains($routeName, '.update')
            || str_contains($routeName, 'save')
            || str_contains($routeName, 'change_')) {
            return 'edit';
        }

        return 'view';
    }

    /**
     * Whether restricted staff may open Admin Console at all.
     */
    public function canAdminConsole(Staff|Authenticatable|null $staff): bool
    {
        if (! $staff instanceof Staff) {
            return false;
        }

        if (! $this->isFeatureEnabled() || $this->hasFullCrmAccess($staff) || ! $this->isRestricted($staff)) {
            return method_exists($staff, 'canAccessAdminConsoleByRole')
                ? $staff->canAccessAdminConsoleByRole()
                : true;
        }

        $profile = $this->profileFor($staff);
        if (! $profile) {
            return false;
        }

        if ($profile->hasNavAction('admin_console', 'view')) {
            return true;
        }

        return $profile->allowedAdminConsoleModules() !== [];
    }

    /**
     * Whether restricted staff may use a specific Admin Console sidebar module.
     */
    public function canAdminConsoleModule(Staff|Authenticatable|null $staff, string $moduleKey): bool
    {
        if (! $staff instanceof Staff) {
            return false;
        }

        if (! $this->isFeatureEnabled() || $this->hasFullCrmAccess($staff) || ! $this->isRestricted($staff)) {
            return true;
        }

        if (! $this->canAdminConsole($staff)) {
            return false;
        }

        $profile = $this->profileFor($staff);
        $allowed = $profile ? $profile->allowedAdminConsoleModules() : [];

        return in_array($moduleKey, $allowed, true);
    }

    /**
     * Map Admin Console module keys to route-name prefixes.
     *
     * @return array<string, list<string>>
     */
    public function adminConsoleModuleRoutePrefixes(): array
    {
        return [
            'email_labels' => ['adminconsole.features.emaillabels.'],
            'workflows' => ['adminconsole.features.workflow.'],
            'emails' => ['adminconsole.features.emails.'],
            'crm_email_template' => ['adminconsole.features.crmemailtemplate.'],
            'offices' => ['adminconsole.system.offices.'],
            'staff' => ['adminconsole.staff.'],
            'teams' => ['adminconsole.system.teams.'],
            'roles' => ['adminconsole.system.roles.'],
            'document_checklist' => ['adminconsole.features.documentchecklist.'],
            'matter_list' => ['adminconsole.features.matter.'],
            'sms' => ['adminconsole.features.sms.'],
            'sms_templates' => ['adminconsole.features.sms.templates.'],
            'esignature' => ['adminconsole.features.esignature.'],
            'activity_search' => ['adminconsole.system.activity-search.'],
        ];
    }

    /**
     * Route names / prefixes always allowed for restricted staff.
     *
     * @return list<string>
     */
    public function allowedRoutePatterns(): array
    {
        return [
            'dashboard',
            'dashboard.*',
            'my_profile',
            'my_profile.update',
            'change_password',
            'change_password.update',
            'crm.logout',
            'crm.logout.get',
            'crm.all-notifications',
            'crm.session.super-admin-mode',
            'tasks.counts',
        ];
    }

    /**
     * Whether a restricted user may hit this named route (nav / deep-link gate).
     */
    public function canAccessRoute(Staff $staff, ?string $routeName): bool
    {
        if (! $this->isFeatureEnabled() || $this->hasFullCrmAccess($staff) || ! $this->isRestricted($staff)) {
            return true;
        }

        $routeName = (string) $routeName;
        if ($routeName === '') {
            return false;
        }

        foreach ($this->allowedRoutePatterns() as $pattern) {
            if ($pattern === $routeName || (str_ends_with($pattern, '.*') && str_starts_with($routeName, substr($pattern, 0, -1)))) {
                return true;
            }
        }

        $flags = $this->navFlags($staff);

        // Closed matters list / deep links — only with an explicit closed_matter grant.
        if (str_contains($routeName, 'closedmatter') || $routeName === 'clients.closedmatterslist') {
            return ! empty($flags['closed_matters']);
        }

        if ($flags['clients'] && (
            str_starts_with($routeName, 'clients.')
            && ! str_starts_with($routeName, 'clients.invoice')
            && ! str_starts_with($routeName, 'clients.clientreceipt')
            && ! str_starts_with($routeName, 'clients.officereceipt')
            && ! str_starts_with($routeName, 'clients.journalreceipt')
            && ! str_starts_with($routeName, 'clients.analytics')
            && $routeName !== 'clients.unassigned-emails'
            && ! str_contains($routeName, 'receipt')
        )) {
            // Record grants imply view; explicit nav actions gate add/edit/delete.
            $action = $this->navActionForRoute($routeName);
            if ($action === 'view') {
                return true;
            }

            return $this->canNavAction($staff, 'clients', $action);
        }

        if ($flags['leads'] && str_starts_with($routeName, 'leads.')) {
            $action = $this->navActionForRoute($routeName);
            if ($action === 'view') {
                return true;
            }

            return $this->canNavAction($staff, 'leads', $action);
        }

        // Own tasks / personal calendar / reminders after grants exist or nav grant.
        if (! empty($flags['tasks']) && (
            $routeName === 'assignee.tasks'
            || str_starts_with($routeName, 'assignee.tasks.')
            || $routeName === 'tasks.counts'
        )) {
            $action = $this->navActionForRoute($routeName);
            if ($action === 'view' || $this->hasActiveGrants($staff)) {
                return true;
            }

            return $this->canNavAction($staff, 'tasks', $action);
        }

        if (! empty($flags['calendar']) && (
            $routeName === 'dashboard.calendar-events'
            || $routeName === 'booking.appointments.calendar.staff'
            || str_starts_with($routeName, 'booking.api.calendar-events')
            || $routeName === 'booking.api.calendar-stats.staff'
        )) {
            $action = $this->navActionForRoute($routeName);
            if ($action === 'view' || $this->hasActiveGrants($staff)) {
                return true;
            }

            return $this->canNavAction($staff, 'calendar', $action);
        }

        if (! empty($flags['unassigned_mail']) && (
            $routeName === 'clients.unassigned-emails'
            || str_starts_with($routeName, 'clients.unassigned')
            || str_contains($routeName, 'synced')
            || str_contains($routeName, 'outlook')
            || str_contains($routeName, 'fetch-all')
            || str_contains($routeName, 'fetchAll')
        )) {
            return true;
        }

        if (! empty($flags['booking']) && str_starts_with($routeName, 'booking.')) {
            $action = $this->navActionForRoute($routeName);

            return $action === 'view' || $this->canNavAction($staff, 'booking', $action);
        }

        if (! empty($flags['signatures']) && str_starts_with($routeName, 'signatures.')) {
            return $this->canNavAction($staff, 'signatures', 'view');
        }

        if (! empty($flags['office_visits']) && str_starts_with($routeName, 'office-visits.')) {
            $action = $this->navActionForRoute($routeName);

            return $action === 'view' || $this->canNavAction($staff, 'office_visits', $action);
        }

        if (! empty($flags['front_desk']) && str_starts_with($routeName, 'front-desk.')) {
            return $this->canNavAction($staff, 'front_desk', 'view');
        }

        if (! empty($flags['office_checkin']) && (
            str_contains($routeName, 'office') && str_contains($routeName, 'checkin')
        )) {
            return true;
        }

        if (! empty($flags['accounts']) && (
            str_contains($routeName, 'invoice')
            || str_contains($routeName, 'receipt')
            || str_contains($routeName, 'analytics-dashboard')
        )) {
            return true;
        }

        // Admin Console: only modules Super Admin ticked on Staff Access.
        if (str_starts_with($routeName, 'adminconsole.')) {
            if (! $this->canAdminConsole($staff)) {
                return false;
            }
            // Never allow Staff Access management for restricted users.
            if (str_starts_with($routeName, 'adminconsole.system.staff-access.')) {
                return false;
            }
            foreach ($this->adminConsoleModuleRoutePrefixes() as $moduleKey => $prefixes) {
                if (! $this->canAdminConsoleModule($staff, $moduleKey)) {
                    continue;
                }
                foreach ($prefixes as $prefix) {
                    if ($routeName === rtrim($prefix, '.') || str_starts_with($routeName, $prefix)) {
                        // SMS Management grant does not include SMS Templates.
                        if ($moduleKey === 'sms' && str_starts_with($routeName, 'adminconsole.features.sms.templates.')) {
                            continue;
                        }

                        return true;
                    }
                }
            }

            return false;
        }

        // Explicit denylist for high-risk modules when restricted.
        $blockedPrefixes = [
            'adminconsole.',
            'booking.',
            'signatures.',
            'office-visits.',
            'front-desk.',
            'assignee.tasks',
            'emails.smart-import',
            'communication-check.',
            'system_errors.',
            'crm.access.',
        ];
        foreach ($blockedPrefixes as $prefix) {
            if ($routeName === rtrim($prefix, '.') || str_starts_with($routeName, $prefix)) {
                return false;
            }
        }

        return false;
    }

    protected function profileFor(Staff $staff): ?StaffAccessProfile
    {
        if (! Schema::hasTable('staff_access_profiles')) {
            return null;
        }

        return StaffAccessProfile::query()->where('staff_id', (int) $staff->id)->first();
    }
}
