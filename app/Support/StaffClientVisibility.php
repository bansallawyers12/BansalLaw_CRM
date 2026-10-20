<?php

namespace App\Support;

use App\Models\Admin;
use App\Models\BookingAppointment;
use App\Models\ClientAccessGrant;
use App\Models\Document;
use App\Models\Staff;
use App\Services\CrmAccess\CrmAccessService;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Row-level visibility for CRM staff.
 *
 * Matter allocation (clients): staff other than Super Admin, Calling Team, and the named
 * matter-visibility exceptions (Kabir, Ajay, Khusi/Khushi) only see client_matters rows where they
 * are Legal Practitioner, Person Responsible, or Person Assisting, plus an active matter_temp grant
 * on that same row. Client ownership and a grant on a different matter of the same client do not
 * reveal the rest of that client's matters.
 * Lead listing, bookings, documents, and the global search all use seesAllClientMatters() for the
 * full-visibility group and matter assignment for everyone else.
 * With CRM_ACCESS_ASSIGNMENT_ONLY=true, other staff only see leads/clients/mail they are assigned to
 * (matter team, lead owner, grants, or synced mail To/Cc/Bcc).
 *
 * Cross-access grants and strict allocation are controlled by config/crm_access.php
 * (CRM_ACCESS_ALLOCATION_ENABLED, CRM_ACCESS_STRICT_ALLOCATION, exempt roles, approvers, quick access).
 */
final class StaffClientVisibility
{
    private const DEFAULT_PERSON_ASSISTING_ROLE_IDS = [13];

    /**
     * CRM requests should resolve the logged-in staff from the admin guard first (matches auth:admin).
     */
    private static function currentStaff(?Authenticatable $user = null): ?Authenticatable
    {
        if ($user !== null) {
            return $user;
        }

        $fromAdmin = Auth::guard('admin')->user();

        return $fromAdmin instanceof Authenticatable ? $fromAdmin : Auth::user();
    }

    /**
     * Roles that get full lead visibility in non-strict mode.
     * In strict mode (CRM_ACCESS_STRICT_ALLOCATION=true) this list is IGNORED and
     * everyone (including PR role 12) is subject to allocation + grant checks.
     * Must stay aligned with config/crm.php.
     *
     * @see userMaySeeByAllocation for the strict-mode enforcement path
     */
    private const DEFAULT_LEAD_FULL_ACCESS_ROLE_IDS = [1, 12, 17];

    public static function personAssistingRoleIds(): array
    {
        $ids = config('crm.person_assisting_role_ids', self::DEFAULT_PERSON_ASSISTING_ROLE_IDS);
        $filtered = array_values(array_filter(
            array_map('intval', (array) $ids),
            static fn (int $id) => $id > 0
        ));

        return $filtered !== [] ? $filtered : self::DEFAULT_PERSON_ASSISTING_ROLE_IDS;
    }

    /**
     * @return list<int>
     */
    public static function leadFullAccessRoleIds(): array
    {
        $ids = config('crm.lead_full_access_role_ids', self::DEFAULT_LEAD_FULL_ACCESS_ROLE_IDS);
        $filtered = array_values(array_filter(
            array_map('intval', (array) $ids),
            static fn (int $id) => $id > 0
        ));

        return $filtered !== [] ? $filtered : self::DEFAULT_LEAD_FULL_ACCESS_ROLE_IDS;
    }

    /**
     * @return array{status: bool, message: string, error_type: string}
     */
    public static function unauthorizedPayload(): array
    {
        return [
            'status' => false,
            'message' => 'Unauthorized',
            'error_type' => 'forbidden',
        ];
    }

    /**
     * When false, allocation filters, search locks, and cross-access UI are disabled site-wide.
     * Super-admin-only client file IDs (config/crm.php) remain enforced.
     */
    public static function isAllocationEnforcementEnabled(): bool
    {
        return (bool) config('crm_access.allocation_enabled', true);
    }

    /**
     * When true, non-bypass staff only access leads/clients/mail they are assigned to (see class docblock).
     */
    public static function isAssignmentOnlyAccessEnabled(): bool
    {
        return self::isAllocationEnforcementEnabled()
            && (bool) config('crm_access.assignment_only_access_enabled', true);
    }

    public static function isRestrictedPersonAssisting(?Authenticatable $user): bool
    {
        if (! self::isAllocationEnforcementEnabled()) {
            return false;
        }

        if (! $user) {
            return false;
        }

        if ($user instanceof Staff && $user->hasEffectiveSuperAdminPrivileges()) {
            return false;
        }

        return in_array((int) $user->role, self::personAssistingRoleIds(), true);
    }

    public static function isExemptFromAllocation(?Authenticatable $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user instanceof Staff && app(CrmAccessService::class)->hasEffectiveSuperAdminPrivileges($user)) {
            return true;
        }

        $staffId = (int) ($user->id ?? 0);
        if ($staffId > 0 && in_array($staffId, config('crm_access.exempt_staff_ids', []), true)) {
            return true;
        }

        $role = (int) ($user->role ?? 0);
        if (self::isAssignmentOnlyAccessEnabled()) {
            $bypass = config('crm_access.assignment_only_bypass_role_ids', [1]);

            return in_array($role, array_map('intval', (array) $bypass), true);
        }

        return in_array($role, config('crm_access.exempt_role_ids', [1, 17]), true);
    }

    /**
     * Full client-matter visibility: Super Admin, Calling Team (role or department), and Kabir / Ajay / Khusi.
     * Everyone else is limited to matters they are assigned to.
     */
    public static function seesAllClientMatters(?Authenticatable $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user instanceof Staff && $user->hasEffectiveSuperAdminPrivileges()) {
            return true;
        }

        if (in_array((int) ($user->role ?? 0), config('crm_access.matter_scope_bypass_role_ids', [1]), true)) {
            return true;
        }

        if (in_array((int) ($user->role ?? 0), config('crm_access.quick_access_only_role_ids', [14]), true)) {
            return true;
        }

        $teamId = (int) ($user->team ?? 0);
        if ($teamId > 0 && $teamId === self::callingTeamId()) {
            return true;
        }

        return self::isNamedMatterVisibilityException($user);
    }

    private static function callingTeamId(): int
    {
        if (! Schema::hasTable('teams')) {
            return 0;
        }

        return (int) DB::table('teams')->whereRaw('LOWER(TRIM(name)) = ?', ['calling team'])->value('id');
    }

    private static function isNamedMatterVisibilityException(Authenticatable $user): bool
    {
        $first = strtolower(trim((string) ($user->first_name ?? '')));
        $last = strtolower(trim((string) ($user->last_name ?? '')));
        $email = strtolower(trim((string) ($user->email ?? '')));

        $named = [
            ['ajay', 'bansal'],
            ['kabir', 'makkar'],
            ['khushi', 'sangroya'],
            ['khusi', 'sangroya'],
            ['khusi', 'bansal'],
        ];
        foreach ($named as [$expectedFirst, $expectedLast]) {
            if ($first === $expectedFirst && ($last === '' || $last === $expectedLast)) {
                return true;
            }
        }

        if (in_array($first, ['kabir', 'ajay', 'khusi', 'khushi'], true)) {
            return true;
        }

        foreach (['kabir', 'ajay', 'khusi', 'khushi'] as $token) {
            if ($email !== '' && str_contains($email, $token)) {
                return true;
            }
        }

        return false;
    }

    public static function isQuickAccessOnly(?Authenticatable $user): bool
    {
        if (! $user) {
            return false;
        }

        return in_array((int) ($user->role ?? 0), config('crm_access.quick_access_only_role_ids', [14]), true);
    }

    /**
     * @return array{show_quick: bool, show_supervisor: bool}
     */
    public static function crossAccessUiFlags(?Authenticatable $user): array
    {
        if (! self::isAllocationEnforcementEnabled() || ! $user || self::seesAllClientMatters($user)) {
            return ['show_quick' => false, 'show_supervisor' => false];
        }

        if (self::isQuickAccessOnly($user)) {
            $enabled = (bool) ($user->quick_access_enabled ?? false);

            return ['show_quick' => $enabled, 'show_supervisor' => false];
        }

        $quick = (bool) ($user->quick_access_enabled ?? false);

        return ['show_quick' => $quick, 'show_supervisor' => true];
    }

    /**
     * @param  array<string, mixed>  $item  Must include 'cid'
     * @return array<string, mixed>
     */
    public static function enrichGlobalSearchItem(array $item, string $recordType, ?Authenticatable $user = null): array
    {
        $user = self::currentStaff($user);
        $cid = (int) ($item['cid'] ?? 0);
        if ($cid <= 0 || ! $user) {
            $item['locked'] = false;
            $item['record_type'] = $recordType;
            $item['access_ui'] = ['show_quick' => false, 'show_supervisor' => false];

            return $item;
        }

        if (! self::isAllocationEnforcementEnabled()) {
            $item['locked'] = false;
            $item['record_type'] = $recordType;
            $item['access_ui'] = ['show_quick' => false, 'show_supervisor' => false];

            return $item;
        }

        $can = self::canAccessClientOrLead($cid, $user);
        $item['locked'] = ! $can;
        $item['record_type'] = $recordType;
        $item['access_ui'] = $can
            ? ['show_quick' => false, 'show_supervisor' => false]
            : self::crossAccessUiFlags($user);

        if (! $can) {
            $item['name'] = 'Restricted Record';
            if (isset($item['first_name'])) {
                $item['first_name'] = 'Restricted';
            }
            if (isset($item['last_name'])) {
                $item['last_name'] = 'Record';
            }
            if (array_key_exists('email', $item)) {
                $item['email'] = '***@***';
            }
            if (array_key_exists('emails', $item)) {
                $item['emails'] = '***@***';
            }
            if (array_key_exists('phone', $item)) {
                $item['phone'] = '***';
            }
            if (array_key_exists('phones', $item)) {
                $item['phones'] = '***';
            }
            if (array_key_exists('mobile', $item)) {
                $item['mobile'] = '***';
            }
            if (array_key_exists('telephone', $item)) {
                $item['telephone'] = '***';
            }
        }

        return $item;
    }

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    public static function restrictMatterListToAllocatedClients($query, string $cmAlias = 'cm', string $adAlias = 'ad'): void
    {
        $user = self::currentStaff();
        if (! $user || ! self::isAllocationEnforcementEnabled() || self::seesAllClientMatters($user)) {
            return;
        }
        $staffId = (int) $user->id;
        $matterAlias = preg_replace('/[^A-Za-z0-9_]/', '', $cmAlias) ?: 'cm';
        $query->where(function ($q) use ($staffId, $matterAlias) {
            $q->where($matterAlias . '.sel_legal_practitioner', $staffId)
                ->orWhere($matterAlias . '.sel_person_responsible', $staffId)
                ->orWhere($matterAlias . '.sel_person_assisting', $staffId)
                ->orWhereExists(function ($sub) use ($staffId, $matterAlias) {
                    $sub->select(DB::raw('1'))
                        ->from('client_access_grants')
                        ->whereColumn('client_access_grants.admin_id', $matterAlias . '.client_id')
                        ->where('client_access_grants.staff_id', $staffId)
                        ->where('client_access_grants.grant_type', MatterScopedAccess::GRANT_TYPE_MATTER_TEMP)
                        ->where('client_access_grants.status', 'active')
                        ->whereNotNull('client_access_grants.ends_at')
                        ->whereRaw('client_access_grants.ends_at > NOW()')
                        ->where(function ($note) use ($matterAlias) {
                            $note->whereRaw(
                                "client_access_grants.requester_note = CONCAT('matter_id:', {$matterAlias}.id)"
                            )->orWhereRaw(
                                "client_access_grants.requester_note LIKE CONCAT('matter_id:', {$matterAlias}.id, ';role:%')"
                            );
                        });
                });
        });
    }

    /**
     * @return list<string>
     */
    public static function superAdminOnlyLockedClientFileIds(): array
    {
        return config('crm.super_admin_only_client_file_ids', []);
    }

    /**
     * @return list<string> Uppercased trimmed values for case-insensitive SQL matching
     */
    public static function normalizedSuperAdminOnlyLockedClientFileIdsUpper(): array
    {
        $ids = self::superAdminOnlyLockedClientFileIds();
        $out = [];
        foreach ($ids as $id) {
            $u = strtoupper(trim((string) $id));
            if ($u !== '') {
                $out[] = $u;
            }
        }

        return array_values(array_unique($out));
    }

    public static function isSuperAdminOnlyLockedClient(?string $adminType, ?string $clientFileId): bool
    {
        if (($adminType ?? '') !== 'client') {
            return false;
        }
        $file = strtoupper(trim((string) $clientFileId));
        if ($file === '') {
            return false;
        }

        return in_array($file, self::normalizedSuperAdminOnlyLockedClientFileIdsUpper(), true);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Admin>|\Illuminate\Database\Query\Builder  $query
     */
    public static function excludeSuperAdminOnlyLockedClientsFromAdminQuery($query, ?Authenticatable $viewer = null): void
    {
        $viewer = self::currentStaff($viewer);
        if (! $viewer || ($viewer instanceof Staff && $viewer->hasEffectiveSuperAdminPrivileges())) {
            return;
        }

        $upperIds = self::normalizedSuperAdminOnlyLockedClientFileIdsUpper();
        if ($upperIds === []) {
            return;
        }

        $table = method_exists($query, 'getModel')
            ? $query->getModel()->getTable()
            : 'admins';

        $placeholders = implode(',', array_fill(0, count($upperIds), '?'));

        $query->where(function ($q) use ($table, $upperIds, $placeholders) {
            $q->where("{$table}.type", '!=', 'client')
                ->orWhereNull("{$table}.client_id")
                ->orWhereRaw(
                    "UPPER(TRIM({$table}.client_id)) NOT IN ({$placeholders})",
                    $upperIds
                );
        });
    }

    /**
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    public static function applyExcludeSuperAdminOnlyLockedClientsOnAdminJoin($query, string $adminsAlias, ?Authenticatable $viewer = null): void
    {
        $viewer = self::currentStaff($viewer);
        if (! $viewer || ($viewer instanceof Staff && $viewer->hasEffectiveSuperAdminPrivileges())) {
            return;
        }

        $upperIds = self::normalizedSuperAdminOnlyLockedClientFileIdsUpper();
        if ($upperIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($upperIds), '?'));

        $query->where(function ($q) use ($adminsAlias, $upperIds, $placeholders) {
            $q->where("{$adminsAlias}.type", '!=', 'client')
                ->orWhereNull("{$adminsAlias}.client_id")
                ->orWhereRaw(
                    "UPPER(TRIM({$adminsAlias}.client_id)) NOT IN ({$placeholders})",
                    $upperIds
                );
        });
    }

    public static function personAssistingStaffIdOrNull(?Authenticatable $user): ?int
    {
        return self::isRestrictedPersonAssisting($user) ? (int) $user->id : null;
    }

    /**
     * Leads list and Other Parties list.
     * Full list: Super Admin, Calling Team, Kabir, Ajay, Khusi.
     * Everyone else sees rows they own, rows where they are on a matter, or rows with an active grant.
     *
     * @param  Builder<\App\Models\Lead>  $query
     */
    public static function restrictLeadListQuery(Builder $query): void
    {
        $user = self::currentStaff();
        if (! $user || ! self::isAllocationEnforcementEnabled() || self::seesAllClientMatters($user)) {
            return;
        }

        $staffId = (int) $user->id;
        $column = $query->getModel()->qualifyColumn('user_id');
        $query->where(function (Builder $q) use ($column, $staffId) {
            $q->where($column, $staffId)
              ->orWhereExists(function ($sub) use ($staffId) {
                  $sub->select(DB::raw('1'))
                      ->from('client_matters')
                      ->whereColumn('client_matters.client_id', 'admins.id');
                  self::whereClientMatterRowAssignedToStaff($sub, $staffId);
              })
              ->orWhereExists(function ($sub) use ($staffId) {
                  $sub->select(DB::raw('1'))
                      ->from('client_access_grants')
                      ->whereColumn('client_access_grants.admin_id', 'admins.id')
                      ->where('client_access_grants.staff_id', $staffId)
                      ->where('client_access_grants.status', 'active')
                      ->whereNotNull('client_access_grants.ends_at')
                      ->whereRaw('client_access_grants.ends_at > NOW()');
              });
        });
    }

    /**
     * Allocation + active grants (incl. matter_temp) for joined admins rows (global search, raw queries).
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    public static function applyAllocatedClientVisibilityOnAdminJoin($query, string $adminsAlias = 'admins', ?Authenticatable $user = null): void
    {
        $user = self::currentStaff($user);
        if (! $user || ! self::isAllocationEnforcementEnabled() || self::seesAllClientMatters($user)) {
            return;
        }

        if (MatterScopedAccess::isEnabled() && MatterScopedAccess::bypassesMatterTeamEnforcement($user)) {
            return;
        }

        if (self::isExemptFromAllocation($user) && ! MatterScopedAccess::isEnabled()) {
            return;
        }

        $staffId = (int) $user->id;
        $adminIdCol = $adminsAlias . '.id';
        $userIdCol = $adminsAlias . '.user_id';

        $typeCol = $adminsAlias . '.type';
        $query->where(function ($q) use ($staffId, $adminIdCol, $userIdCol, $typeCol) {
            self::applyAssignedClientAdminVisibilityClauses(
                $q,
                $staffId,
                $adminIdCol,
                self::isAssignmentOnlyAccessEnabled() ? null : $userIdCol,
                self::isAssignmentOnlyAccessEnabled() ? $typeCol : null
            );
        });
    }

    /**
     * @param  Builder<\App\Models\Admin>  $query
     */
    public static function restrictAdminEloquentQuery(Builder $query): void
    {
        $user = self::currentStaff();
        self::excludeSuperAdminOnlyLockedClientsFromAdminQuery($query, $user);

        self::applyAllocatedClientVisibilityOnAdminJoin($query, 'admins', $user);
    }

    /**
     * Drop global-search hits the viewer may not open (matter-scoped + allocation).
     *
     * @param  list<array<string, mixed>>  $results
     * @return list<array<string, mixed>>
     */
    public static function filterGlobalSearchResultsForViewer(array $results, ?Authenticatable $user = null): array
    {
        $user = self::currentStaff($user);
        if ($results === [] || ! $user) {
            return $results;
        }

        if (! self::isAllocationEnforcementEnabled()) {
            return $results;
        }

        if (MatterScopedAccess::isEnabled()) {
            if (MatterScopedAccess::bypassesMatterTeamEnforcement($user) || self::seesAllClientMatters($user)) {
                return $results;
            }
        } elseif (self::isExemptFromAllocation($user) || self::seesAllClientMatters($user)) {
            return $results;
        }

        return array_values(array_filter($results, static function (array $item) use ($user): bool {
            $cid = (int) ($item['cid'] ?? 0);
            if ($cid <= 0) {
                return false;
            }

            $encodedId = (string) ($item['id'] ?? '');
            if (preg_match('#/Matter/([^/]+)$#', $encodedId, $matches) === 1) {
                $matterRef = trim((string) ($matches[1] ?? ''));
                if ($matterRef === '') {
                    return false;
                }
                if (MatterScopedAccess::isEnabled()) {
                    return MatterScopedAccess::canAccessMatterByReference($cid, $matterRef, $user);
                }

                return self::canAccessClientOrLead($cid, $user);
            }

            return self::canAccessClientOrLead($cid, $user);
        }));
    }

    public static function canAccessClientOrLead(int $adminId, ?Authenticatable $user = null): bool
    {
        $user = self::currentStaff($user);

        if (! $user) {
            return false;
        }

        $row = Admin::query()
            ->where('id', $adminId)
            ->whereIn('type', ['client', 'lead'])
            ->first(['id', 'type', 'user_id', 'client_id']);

        if (! $row) {
            return false;
        }

        if (self::isSuperAdminOnlyLockedClient($row->type ?? null, $row->client_id ?? null)) {
            return $user instanceof Staff && $user->hasEffectiveSuperAdminPrivileges();
        }

        if (! self::isAllocationEnforcementEnabled()) {
            return true;
        }

        if (self::seesAllClientMatters($user)) {
            if ($user instanceof Staff && self::isExemptFromAllocation($user)) {
                self::logExemptAccessIfNeeded((int) $user->id, $adminId, (string) ($row->type ?? 'client'));
            }

            return true;
        }

        if ($user instanceof Staff) {
            $svc = app(CrmAccessService::class);
            if ($svc->hasActiveGrant($user, $adminId)) {
                return true;
            }

            if (MatterScopedAccess::staffHasActiveMatterTempGrantForClient((int) $user->id, $adminId)) {
                return true;
            }
        }

        return self::userMaySeeByAllocation($adminId, $user, $row);
    }

    /**
     * Write one exempt audit row per calendar day (UTC) per staff + admin combo.
     * Silently swallowed on failure to avoid disrupting the main request.
     */
    private static function logExemptAccessIfNeeded(int $staffId, int $adminId, string $recordType): void
    {
        try {
            $today = Carbon::now('UTC')->toDateString();
            $exists = ClientAccessGrant::query()
                ->where('staff_id', $staffId)
                ->where('admin_id', $adminId)
                ->where('grant_type', 'exempt')
                ->whereDate('created_at', $today)
                ->exists();

            if (! $exists) {
                $rt = in_array($recordType, ['client', 'lead'], true) ? $recordType : 'client';
                ClientAccessGrant::query()->create([
                    'staff_id' => $staffId,
                    'admin_id' => $adminId,
                    'record_type' => $rt,
                    'grant_type' => 'exempt',
                    'access_type' => 'exempt',
                    'status' => 'active',
                    'requested_at' => Carbon::now('UTC'),
                    'starts_at' => Carbon::now('UTC'),
                ]);
            }
        } catch (\Throwable) {
            // Never disrupt the main access check
        }
    }

    /**
     * Limit signature / document listings to records the viewer may access (client_id / lead_id on documents).
     *
     * Uses the model's own table name (via qualifyColumn) so callers that join the documents
     * table under an alias, or that use a different primary table, still get correct SQL.
     *
     * @param  Builder<\App\Models\Document>  $query
     */
    public static function restrictDocumentEloquentQuery(Builder $query, ?Authenticatable $user = null): void
    {
        $user = self::currentStaff($user);
        if (! $user || ! self::isAllocationEnforcementEnabled() || self::seesAllClientMatters($user)) {
            return;
        }

        $staffId = (int) $user->id;
        $model   = $query->getModel();
        $table   = $model->getTable();

        $query->where(function (Builder $outer) use ($staffId, $table) {
            $outer->where(function (Builder $q) use ($table) {
                $q->whereNull("{$table}.client_id")->whereNull("{$table}.lead_id");
            });

            foreach (['client_id', 'lead_id'] as $col) {
                $outer->orWhere(function (Builder $docQ) use ($staffId, $col, $table) {
                    $docQ->whereNotNull("{$table}.{$col}")
                        ->where(function (Builder $accessQ) use ($staffId, $col, $table) {
                            $accessQ->whereExists(function ($sub) use ($staffId, $col, $table) {
                                $sub->select(DB::raw('1'))
                                    ->from('client_matters')
                                    ->whereColumn('client_matters.client_id', "{$table}.{$col}");
                                self::whereClientMatterRowAssignedToStaff($sub, $staffId);
                            })->orWhereExists(function ($sub) use ($staffId, $col, $table) {
                                $sub->select(DB::raw('1'))
                                    ->from('admins')
                                    ->whereColumn('admins.id', "{$table}.{$col}")
                                    ->where('admins.user_id', $staffId);
                            })->orWhereExists(function ($sub) use ($staffId, $col, $table) {
                                $sub->select(DB::raw('1'))
                                    ->from('client_access_grants')
                                    ->whereColumn('client_access_grants.admin_id', "{$table}.{$col}")
                                    ->where('client_access_grants.staff_id', $staffId)
                                    ->where('client_access_grants.status', 'active')
                                    ->whereNotNull('client_access_grants.ends_at')
                                    ->whereRaw('client_access_grants.ends_at > NOW()');
                            });
                        });
                });
            }
        });

        // documents.client_matter_id may be varchar; compare as text so PostgreSQL does not reject bigint = varchar.
        $matterIdText = MatterScopedAccess::matterIdColumnAsTextSql($table, 'client_matter_id');
        $query->where(function (Builder $matterDoc) use ($staffId, $table, $matterIdText) {
            $matterDoc->whereNull("{$table}.client_matter_id")
                ->orWhereRaw("{$matterIdText} IN ('', '0')")
                ->orWhereExists(function ($sub) use ($staffId, $matterIdText) {
                    $sub->select(DB::raw('1'))
                        ->from('client_matters')
                        ->whereRaw("CAST(client_matters.id AS TEXT) = {$matterIdText}")
                        ->where(function ($w) use ($staffId) {
                            $w->where('client_matters.sel_legal_practitioner', $staffId)
                                ->orWhere('client_matters.sel_person_responsible', $staffId)
                                ->orWhere('client_matters.sel_person_assisting', $staffId);
                        });
                })
                ->orWhereExists(function ($sub) use ($staffId, $matterIdText) {
                    $sub->select(DB::raw('1'))
                        ->from('client_access_grants')
                        ->where('client_access_grants.staff_id', $staffId)
                        ->where('client_access_grants.grant_type', MatterScopedAccess::GRANT_TYPE_MATTER_TEMP)
                        ->where('client_access_grants.status', 'active')
                        ->whereNotNull('client_access_grants.ends_at')
                        ->whereRaw('client_access_grants.ends_at > NOW()')
                        ->where(function ($note) use ($matterIdText) {
                            $note->whereRaw("client_access_grants.requester_note = CONCAT('matter_id:', {$matterIdText})")
                                ->orWhereRaw("client_access_grants.requester_note LIKE CONCAT('matter_id:', {$matterIdText}, ';role:%')");
                        });
                });
        });
    }

    /**
     * Check if a staff user may view/access a given Document under allocation rules.
     */
    public static function mayAccessDocument(Document $document, ?Authenticatable $user = null): bool
    {
        $user = self::currentStaff($user);
        if (! $user) {
            return false;
        }

        if (! self::isAllocationEnforcementEnabled() || self::seesAllClientMatters($user)) {
            return true;
        }

        $staffId = (int) $user->id;

        // Creator of the document always has access
        if ($staffId > 0 && ((int) ($document->created_by ?? 0) === $staffId || (int) ($document->user_id ?? 0) === $staffId)) {
            return true;
        }

        // Unattributed documents (no client_id and no lead_id) are accessible to all authenticated staff
        if (empty($document->client_id) && empty($document->lead_id)) {
            return true;
        }

        if (! empty($document->client_matter_id) && \App\Support\MatterScopedAccess::isEnabled()) {
            return \App\Support\MatterScopedAccess::canAccessClientMatter((int) $document->client_matter_id, $user);
        }

        if ($document->exists) {
            $query = Document::query()->whereKey($document->getKey());
            self::restrictDocumentEloquentQuery($query, $user);

            return $query->exists();
        }

        return false;
    }

    /**
     * Bookings list and calendar.
     * Full list: Super Admin, Calling Team, Kabir, Ajay, Khusi.
     * Everyone else sees a booking only when it is linked to a client matter they are on,
     * they own that client, they hold an active grant, or the booking is assigned to them.
     * Website bookings with no CRM client stay hidden from everyone else.
     *
     * @param  Builder<\App\Models\BookingAppointment>  $query
     */
    public static function restrictBookingAppointmentEloquentQuery(Builder $query, ?Authenticatable $user = null): void
    {
        $user = self::currentStaff($user);
        if (! $user || ! self::isAllocationEnforcementEnabled() || self::seesAllClientMatters($user)) {
            return;
        }

        $staffId = (int) $user->id;
        $table   = $query->getModel()->getTable();

        $query->where(function (Builder $outer) use ($staffId, $table) {
            $outer->where("{$table}.user_id", $staffId)
                ->orWhere("{$table}.assigned_by_admin_id", $staffId);

            $outer->orWhere(function (Builder $inner) use ($staffId, $table) {
                $inner->where("{$table}.client_id", '>', 0)
                    ->where(function (Builder $accessQ) use ($staffId, $table) {
                        $accessQ->whereExists(function ($sub) use ($staffId, $table) {
                            $sub->select(DB::raw('1'))
                                ->from('client_matters')
                                ->whereColumn('client_matters.client_id', "{$table}.client_id");
                            self::whereClientMatterRowAssignedToStaff($sub, $staffId);
                        })->orWhereExists(function ($sub) use ($staffId, $table) {
                            $sub->select(DB::raw('1'))
                                ->from('admins')
                                ->whereColumn('admins.id', "{$table}.client_id")
                                ->where('admins.user_id', $staffId);
                        })->orWhereExists(function ($sub) use ($staffId, $table) {
                            $sub->select(DB::raw('1'))
                                ->from('client_access_grants')
                                ->whereColumn('client_access_grants.admin_id', "{$table}.client_id")
                                ->where('client_access_grants.staff_id', $staffId)
                                ->where('client_access_grants.status', 'active')
                                ->whereNotNull('client_access_grants.ends_at')
                                ->whereRaw('client_access_grants.ends_at > NOW()');
                        });
                    });
            });
        });
    }

    /**
     * Abort 403 unless this booking row would pass restrictBookingAppointmentEloquentQuery (same rule as calendar/list API).
     * Keeps write access aligned with what the user can see on the booking calendar.
     */
    public static function abortUnlessMayAccessBookingAppointment(BookingAppointment $appointment, ?Authenticatable $user = null): void
    {
        $query = BookingAppointment::query()->whereKey($appointment->getKey());
        self::restrictBookingAppointmentEloquentQuery($query, $user);
        if ($query->exists()) {
            return;
        }

        if (request()->expectsJson() || request()->ajax()) {
            throw new HttpResponseException(
                response()->json(self::unauthorizedPayload(), 403)
            );
        }

        abort(403, 'Unauthorized');
    }

    /**
     * @param  \App\Models\Admin  $row
     */
    private static function userMaySeeByAllocation(int $adminId, Authenticatable $user, Admin $row): bool
    {
        if (($row->type ?? '') === 'lead') {
            $staffId = (int) $user->id;
            if (self::clientHasAllocatingMatter($adminId, $staffId)) {
                return true;
            }

            return (int) ($row->user_id ?? 0) === $staffId;
        }

        // CRM client (type client): LP / PR / PA on any matter, matter temp grant, or record owner.
        $staffId = (int) $user->id;
        if (self::clientHasAllocatingMatter($adminId, $staffId)) {
            return true;
        }

        if (MatterScopedAccess::isEnabled()
            && MatterScopedAccess::staffHasActiveMatterTempGrantForClient($staffId, $adminId)) {
            return true;
        }

        if (self::isAssignmentOnlyAccessEnabled()) {
            return false;
        }

        return (int) ($row->user_id ?? 0) === $staffId;
    }

    /**
     * True when any client_matters row for this CRM client/lead (admins.id) lists the staff
     * as Legal Practitioner, person responsible, or person assisting.
     */
    private static function clientHasAllocatingMatter(int $clientAdminId, int $staffId): bool
    {
        if ($clientAdminId <= 0 || $staffId <= 0) {
            return false;
        }

        return DB::table('client_matters')
            ->where('client_id', $clientAdminId)
            ->where(function ($q) use ($staffId) {
                $q->where('sel_legal_practitioner', $staffId)
                    ->orWhere('sel_person_responsible', $staffId)
                    ->orWhere('sel_person_assisting', $staffId);
            })
            ->exists();
    }

    /**
     * Limit rows on any table that has a nullable CRM client_id column (staff calendar events, court hearings, etc.).
     *
     * Rows with no client_id remain visible. Linked clients use the same rules as booking appointments.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function restrictEloquentQueryByClientIdColumn(Builder $query, string $clientIdColumn = 'client_id', ?Authenticatable $user = null): void
    {
        $user = self::currentStaff($user);
        if (! $user || ! self::isAllocationEnforcementEnabled() || self::seesAllClientMatters($user)) {
            return;
        }

        $staffId = (int) $user->id;
        $table = $query->getModel()->getTable();
        $col = "{$table}.{$clientIdColumn}";

        $query->where(function (Builder $outer) use ($staffId, $col) {
            $outer->whereNull($col)
                ->orWhere($col, '<=', 0);

            $outer->orWhere(function (Builder $inner) use ($staffId, $col) {
                $inner->where($col, '>', 0)
                    ->where(function (Builder $accessQ) use ($staffId, $col) {
                        $accessQ->whereExists(function ($sub) use ($staffId, $col) {
                            $sub->select(DB::raw('1'))
                                ->from('client_matters')
                                ->whereColumn('client_matters.client_id', $col);
                            self::whereClientMatterRowAssignedToStaff($sub, $staffId);
                        })->orWhereExists(function ($sub) use ($staffId, $col) {
                            $sub->select(DB::raw('1'))
                                ->from('admins')
                                ->whereColumn('admins.id', $col)
                                ->where('admins.user_id', $staffId);
                        })->orWhereExists(function ($sub) use ($staffId, $col) {
                            $sub->select(DB::raw('1'))
                                ->from('client_access_grants')
                                ->whereColumn('client_access_grants.admin_id', $col)
                                ->where('client_access_grants.staff_id', $staffId)
                                ->where('client_access_grants.status', 'active')
                                ->whereNotNull('client_access_grants.ends_at')
                                ->whereRaw('client_access_grants.ends_at > NOW()');
                        });
                    });
            });
        });
    }

    /**
     * SQL visibility: matter team, grants, matter_temp, and (optionally) lead record owner.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $q
     */
    /**
     * Limit email_logs rows to clients/leads the staff may access (assignment-only mode).
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    public static function applyEmailLogLinkedClientVisibilityFilter($query, ?Authenticatable $user = null): void
    {
        $user = self::currentStaff($user);
        if (! $user instanceof Staff || ! self::isAssignmentOnlyAccessEnabled() || self::isExemptFromAllocation($user)) {
            return;
        }

        if (self::seesAllClientMatters($user)) {
            return;
        }

        $staffId = (int) $user->id;
        $table = method_exists($query, 'getModel') ? $query->getModel()->getTable() : 'email_logs';
        $clientCol = $table . '.client_id';

        $query->where(function ($outer) use ($staffId, $clientCol) {
            $outer->whereNull($clientCol)
                ->orWhere($clientCol, '<=', 0)
                ->orWhereExists(function ($sub) use ($staffId, $clientCol) {
                    $sub->select(DB::raw('1'))
                        ->from('admins')
                        ->whereColumn('admins.id', $clientCol)
                        ->where(function ($visibility) use ($staffId) {
                            self::applyAssignedClientAdminVisibilityClauses(
                                $visibility,
                                $staffId,
                                'admins.id',
                                null,
                                'admins.type'
                            );
                        });
                });
        });
    }

    public static function applyAssignedClientAdminVisibilityClauses(
        $q,
        int $staffId,
        string $adminIdColumn,
        ?string $userIdColumn = null,
        ?string $adminTypeColumn = null
    ): void {
        $q->whereExists(function ($sub) use ($staffId, $adminIdColumn) {
            $sub->select(DB::raw('1'))
                ->from('client_matters')
                ->whereColumn('client_matters.client_id', $adminIdColumn);
            self::whereClientMatterRowAssignedToStaff($sub, $staffId);
        })->orWhereExists(function ($sub) use ($staffId, $adminIdColumn) {
            $sub->select(DB::raw('1'))
                ->from('client_access_grants')
                ->whereColumn('client_access_grants.admin_id', $adminIdColumn)
                ->where('client_access_grants.staff_id', $staffId)
                ->where('client_access_grants.status', 'active')
                ->whereNotNull('client_access_grants.ends_at')
                ->whereRaw('client_access_grants.ends_at > NOW()');
        })->orWhereExists(function ($sub) use ($staffId, $adminIdColumn) {
            $sub->select(DB::raw('1'))
                ->from('client_access_grants')
                ->whereColumn('client_access_grants.admin_id', $adminIdColumn)
                ->where('client_access_grants.staff_id', $staffId)
                ->where('client_access_grants.grant_type', MatterScopedAccess::GRANT_TYPE_MATTER_TEMP)
                ->where('client_access_grants.status', 'active')
                ->whereNotNull('client_access_grants.ends_at')
                ->whereRaw('client_access_grants.ends_at > NOW()');
        });

        if ($userIdColumn !== null) {
            $q->orWhere($userIdColumn, $staffId);
        } elseif ($adminTypeColumn !== null && str_ends_with($adminIdColumn, '.id')) {
            $ownerCol = preg_replace('/\.id$/', '.user_id', $adminIdColumn);
            $q->orWhere(function ($leadOnly) use ($adminTypeColumn, $ownerCol, $staffId) {
                $leadOnly->where($adminTypeColumn, 'lead')->where($ownerCol, $staffId);
            });
        }
    }

    /**
     * For whereExists subqueries after ->from('client_matters') (table name must be `client_matters`, not aliased).
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $sub
     */
    private static function whereClientMatterRowAssignedToStaff($sub, int $staffId): void
    {
        $sub->where(function ($w) use ($staffId) {
            $w->where('client_matters.sel_legal_practitioner', $staffId)
                ->orWhere('client_matters.sel_person_responsible', $staffId)
                ->orWhere('client_matters.sel_person_assisting', $staffId);
        });
    }
}
