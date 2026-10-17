<?php

namespace App\Support;

use App\Models\ClientAccessGrant;
use App\Models\ClientMatter;
use App\Models\Staff;
use App\Services\CrmAccess\CrmAccessService;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Matter-level access: LP / PR / PA on client_matters, plus optional temporary grants (client_access_grants).
 */
final class MatterScopedAccess
{
    public const GRANT_TYPE_MATTER_TEMP = 'matter_temp';

    public const ACCESS_TYPE_MATTER = 'matter';

    public const ROLE_LEGAL_PRACTITIONER = 'legal_practitioner';

    public const ROLE_PERSON_RESPONSIBLE = 'person_responsible';

    public const ROLE_PERSON_ASSISTING = 'person_assisting';

    public const DURATION_PERMANENT = 'permanent';

    /**
     * @return list<string>
     */
    public static function allowedMatterDurationKeys(): array
    {
        return array_merge(
            array_keys(config('crm_access.matter_temp_duration_minutes', [])),
            [self::DURATION_PERMANENT]
        );
    }

    public static function isPermanentDuration(?string $durationKey): bool
    {
        return $durationKey === self::DURATION_PERMANENT;
    }

    /**
     * @return array<string, string> role key => display label
     */
    public static function matterTeamRoleLabels(): array
    {
        return [
            self::ROLE_LEGAL_PRACTITIONER => 'Legal practitioner',
            self::ROLE_PERSON_RESPONSIBLE => 'Person responsible',
            self::ROLE_PERSON_ASSISTING => 'Person assisting',
        ];
    }

    public static function isValidMatterTeamRoleKey(?string $roleKey): bool
    {
        return $roleKey !== null && array_key_exists($roleKey, self::matterTeamRoleLabels());
    }

    public static function matterRoleLabel(?string $roleKey): string
    {
        if ($roleKey === null || $roleKey === '') {
            return 'Matter access';
        }

        return self::matterTeamRoleLabels()[$roleKey] ?? 'Matter access';
    }

    public static function matterRoleColumn(string $roleKey): ?string
    {
        return match ($roleKey) {
            self::ROLE_LEGAL_PRACTITIONER => 'sel_legal_practitioner',
            self::ROLE_PERSON_RESPONSIBLE => 'sel_person_responsible',
            self::ROLE_PERSON_ASSISTING => 'sel_person_assisting',
            default => null,
        };
    }

    public static function matterRoleAvatarVariant(string $roleKey): string
    {
        return match ($roleKey) {
            self::ROLE_LEGAL_PRACTITIONER => 'navy',
            self::ROLE_PERSON_RESPONSIBLE => 'blue',
            self::ROLE_PERSON_ASSISTING => 'teal',
            default => 'slate',
        };
    }

    public static function isEnabled(): bool
    {
        return StaffClientVisibility::isAllocationEnforcementEnabled()
            && (bool) config('crm_access.matter_scoped_access_enabled', true);
    }

    /**
     * Only these roles bypass per-matter team checks (default: Super Admin role 1).
     * Other allocation-exempt roles still need LP/PR/PA or an active matter_temp grant per matter.
     */
    public static function bypassesMatterTeamEnforcement(?Authenticatable $user = null): bool
    {
        $user = $user ?? Auth::guard('admin')->user();
        if (! $user instanceof Staff || (int) ($user->status ?? 0) !== 1) {
            return false;
        }

        $role = (int) ($user->role ?? 0);
        $allowed = config('crm_access.matter_scope_bypass_role_ids', [1]);

        return in_array($role, array_map('intval', (array) $allowed), true);
    }

    public static function matterGrantNote(int $clientMatterId, ?string $roleKey = null): string
    {
        $base = 'matter_id:' . max(0, $clientMatterId);
        if ($roleKey !== null && self::isValidMatterTeamRoleKey($roleKey)) {
            return $base . ';role:' . $roleKey;
        }

        return $base;
    }

    public static function parseMatterIdFromGrantNote(?string $note): ?int
    {
        if ($note === null || $note === '') {
            return null;
        }
        if (preg_match('/^matter_id:(\d+)(?:;role:[a-z_]+)?$/', trim($note), $m)) {
            return (int) $m[1];
        }

        return null;
    }

    public static function parseRoleFromGrantNote(?string $note): ?string
    {
        if ($note === null || $note === '') {
            return null;
        }
        if (preg_match('/;role:([a-z_]+)$/', trim($note), $m)) {
            $role = (string) $m[1];

            return self::isValidMatterTeamRoleKey($role) ? $role : null;
        }

        return null;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<ClientAccessGrant>  $query
     * @return \Illuminate\Database\Eloquent\Builder<ClientAccessGrant>
     */
    public static function applyMatterGrantNoteFilter($query, int $clientMatterId, ?string $roleKey = null)
    {
        if ($roleKey !== null && self::isValidMatterTeamRoleKey($roleKey)) {
            return $query->where('requester_note', self::matterGrantNote($clientMatterId, $roleKey));
        }

        $base = 'matter_id:' . max(0, $clientMatterId);

        return $query->where(function ($q) use ($base) {
            $q->where('requester_note', $base)
                ->orWhere('requester_note', 'like', $base . ';role:%');
        });
    }

    public static function assignMatterTeamRole(int $clientMatterId, string $roleKey, int $staffId): void
    {
        $column = self::matterRoleColumn($roleKey);
        if ($column === null || $clientMatterId <= 0 || $staffId <= 0) {
            return;
        }

        if (! Schema::hasColumn('client_matters', $column)) {
            return;
        }

        ClientMatter::query()->where('id', $clientMatterId)->update([$column => $staffId]);
    }

    public static function clearMatterTeamRoleIfStaff(int $clientMatterId, string $roleKey, int $staffId): void
    {
        $column = self::matterRoleColumn($roleKey);
        if ($column === null || $clientMatterId <= 0 || $staffId <= 0) {
            return;
        }

        if (! Schema::hasColumn('client_matters', $column)) {
            return;
        }

        $matter = ClientMatter::query()->where('id', $clientMatterId)->first([$column]);
        if ($matter && (int) ($matter->{$column} ?? 0) === $staffId) {
            ClientMatter::query()->where('id', $clientMatterId)->update([$column => null]);
        }
    }

    public static function clearMatterTeamRoleSlot(int $clientMatterId, string $roleKey): void
    {
        $column = self::matterRoleColumn($roleKey);
        if ($column === null || $clientMatterId <= 0) {
            return;
        }

        if (! Schema::hasColumn('client_matters', $column)) {
            return;
        }

        ClientMatter::query()->where('id', $clientMatterId)->update([$column => null]);
    }

    /**
     * Revoke all active temporary grants for one matter position (any staff).
     */
    public static function revokeActiveTemporaryGrantsForRoleOnMatter(
        int $clientMatterId,
        string $roleKey,
        string $revokeReason
    ): int {
        if ($clientMatterId <= 0 || ! self::isValidMatterTeamRoleKey($roleKey)) {
            return 0;
        }

        $clientAdminId = (int) ClientMatter::query()->where('id', $clientMatterId)->value('client_id');
        if ($clientAdminId <= 0) {
            return 0;
        }

        $now = Carbon::now('UTC');

        $query = ClientAccessGrant::query()
            ->where('admin_id', $clientAdminId)
            ->where('grant_type', self::GRANT_TYPE_MATTER_TEMP)
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '>', $now);

        self::applyMatterGrantNoteFilter($query, $clientMatterId, $roleKey);

        $ids = $query->pluck('id')->all();
        if ($ids === []) {
            return 0;
        }

        return ClientAccessGrant::query()
            ->whereIn('id', $ids)
            ->update([
                'status' => 'revoked',
                'revoked_at' => $now,
                'revoke_reason' => $revokeReason,
            ]);
    }

    /**
     * Super Admin (config: matter_temp_manager_role_ids) or staff with can_manage_matter_team_access may
     * assign or revoke matter team / temporary access for others.
     */
    public static function canManageTemporaryGrants(?Authenticatable $user = null): bool
    {
        $user = $user ?? Auth::guard('admin')->user();

        return $user instanceof Staff && $user->canManageMatterTeamAccess();
    }

    /**
     * Active temporary matter grant on any matter for this client file (opens client list + matter URLs).
     */
    public static function staffHasActiveMatterTempGrantForClient(int $staffId, int $clientAdminId): bool
    {
        if ($staffId <= 0 || $clientAdminId <= 0 || ! self::isEnabled()) {
            return false;
        }

        $now = Carbon::now('UTC');

        return ClientAccessGrant::query()
            ->where('staff_id', $staffId)
            ->where('admin_id', $clientAdminId)
            ->where('grant_type', self::GRANT_TYPE_MATTER_TEMP)
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '>', $now)
            ->exists();
    }

    public static function staffIsAssignedToMatterRow(object $matter, int $staffId): bool
    {
        if ($staffId <= 0) {
            return false;
        }

        return (int) ($matter->sel_legal_practitioner ?? 0) === $staffId
            || (int) ($matter->sel_person_responsible ?? 0) === $staffId
            || (int) ($matter->sel_person_assisting ?? 0) === $staffId;
    }

    /**
     * @return list<int>
     */
    public static function staffIdsOnMatterRow(object $matter): array
    {
        $ids = [
            (int) ($matter->sel_legal_practitioner ?? 0),
            (int) ($matter->sel_person_responsible ?? 0),
            (int) ($matter->sel_person_assisting ?? 0),
        ];

        if (Schema::hasColumn('client_matters', 'user_id')) {
            $matterUserId = (int) ($matter->user_id ?? 0);
            if ($matterUserId > 0) {
                $ids[] = $matterUserId;
            }
        }

        return array_values(array_unique(array_filter($ids, static fn (int $id) => $id > 0)));
    }

    /**
     * Remove one staff member from a matter completely: revoke temp grants and clear LP/PR/PA slots.
     */
    public static function purgeStaffFromMatter(int $clientMatterId, int $staffId, string $revokeReason): void
    {
        if ($clientMatterId <= 0 || $staffId <= 0) {
            return;
        }

        self::revokeActiveTemporaryGrantsForStaffOnMatter($staffId, $clientMatterId, $revokeReason);

        $matter = ClientMatter::query()->where('id', $clientMatterId)->first([
            'id',
            'sel_legal_practitioner',
            'sel_person_responsible',
            'sel_person_assisting',
            'user_id',
        ]);
        if (! $matter) {
            return;
        }

        $updates = [];
        foreach (['sel_legal_practitioner', 'sel_person_responsible', 'sel_person_assisting'] as $column) {
            if (! Schema::hasColumn('client_matters', $column)) {
                continue;
            }
            if ((int) ($matter->{$column} ?? 0) === $staffId) {
                $updates[$column] = null;
            }
        }

        if (Schema::hasColumn('client_matters', 'user_id') && (int) ($matter->user_id ?? 0) === $staffId) {
            $updates['user_id'] = null;
        }

        if ($updates !== []) {
            ClientMatter::query()->where('id', $clientMatterId)->update($updates);
        }
    }

    public static function staffIsAssignedToMatterId(int $clientMatterId, int $staffId): bool
    {
        if ($clientMatterId <= 0 || $staffId <= 0) {
            return false;
        }

        $row = ClientMatter::query()
            ->where('id', $clientMatterId)
            ->first(['id', 'sel_legal_practitioner', 'sel_person_responsible', 'sel_person_assisting']);

        return $row && self::staffIsAssignedToMatterRow($row, $staffId);
    }

    public static function hasActiveTemporaryMatterGrant(int $staffId, int $clientMatterId, int $clientAdminId): bool
    {
        if ($staffId <= 0 || $clientMatterId <= 0 || $clientAdminId <= 0) {
            return false;
        }

        $now = Carbon::now('UTC');

        $query = ClientAccessGrant::query()
            ->where('staff_id', $staffId)
            ->where('admin_id', $clientAdminId)
            ->where('grant_type', self::GRANT_TYPE_MATTER_TEMP)
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '>', $now);

        self::applyMatterGrantNoteFilter($query, $clientMatterId);

        return $query->exists();
    }

    /**
     * Active temporary grants for one matter (for admin UI).
     *
     * @return EloquentCollection<int, ClientAccessGrant>
     */
    public static function activeTemporaryGrantsForMatter(int $clientMatterId): EloquentCollection
    {
        if ($clientMatterId <= 0) {
            return new EloquentCollection();
        }

        $clientAdminId = (int) ClientMatter::query()->where('id', $clientMatterId)->value('client_id');
        if ($clientAdminId <= 0) {
            return new EloquentCollection();
        }

        $now = Carbon::now('UTC');

        $query = ClientAccessGrant::query()
            ->with(['staff:id,first_name,last_name,email'])
            ->where('admin_id', $clientAdminId)
            ->where('grant_type', self::GRANT_TYPE_MATTER_TEMP)
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '>', $now);

        self::applyMatterGrantNoteFilter($query, $clientMatterId);

        return $query->orderBy('ends_at')->get();
    }

    public static function durationLabel(?string $durationKey): string
    {
        return match ($durationKey) {
            '1_hour' => '1 hour',
            '1_day' => '1 day',
            '1_week' => '1 week',
            self::DURATION_PERMANENT => 'Permanent',
            default => 'Temporary',
        };
    }

    public static function canAccessClientMatter(int $clientMatterId, ?Authenticatable $user = null): bool
    {
        $user = $user ?? Auth::guard('admin')->user();
        if (! $user instanceof Staff) {
            return false;
        }

        if (! self::isEnabled()) {
            return StaffClientVisibility::canAccessClientOrLead(
                (int) (ClientMatter::query()->where('id', $clientMatterId)->value('client_id') ?? 0),
                $user
            );
        }

        if (self::bypassesMatterTeamEnforcement($user) || StaffClientVisibility::seesAllClientMatters($user)) {
            return true;
        }

        $matter = ClientMatter::query()
            ->where('id', $clientMatterId)
            ->first(['id', 'client_id', 'sel_legal_practitioner', 'sel_person_responsible', 'sel_person_assisting', 'user_id']);

        if (! $matter) {
            return false;
        }

        $staffId = (int) $user->id;
        $clientAdminId = (int) $matter->client_id;

        if (self::staffIsAssignedToMatterRow($matter, $staffId)) {
            return true;
        }

        if (self::hasActiveTemporaryMatterGrant($staffId, $clientMatterId, $clientAdminId)) {
            return true;
        }

        return false;
    }

    public static function canAccessMatterByReference(int $clientAdminId, string $matterReference, ?Authenticatable $user = null): bool
    {
        $matterReference = trim($matterReference);
        if ($clientAdminId <= 0 || $matterReference === '') {
            return false;
        }

        if (! self::isEnabled()) {
            return StaffClientVisibility::canAccessClientOrLead($clientAdminId, $user);
        }

        $matterId = ClientMatter::query()
            ->where('client_id', $clientAdminId)
            ->where('client_unique_matter_no', $matterReference)
            ->value('id');

        if (! $matterId) {
            return false;
        }

        return self::canAccessClientMatter((int) $matterId, $user);
    }

    public static function abortUnlessCanAccessMatterByReference(int $clientAdminId, string $matterReference, ?Authenticatable $user = null): void
    {
        if (self::canAccessMatterByReference($clientAdminId, $matterReference, $user)) {
            return;
        }

        if (request()->expectsJson() || request()->ajax()) {
            throw new HttpResponseException(
                response()->json(StaffClientVisibility::unauthorizedPayload(), 403)
            );
        }

        abort(403, 'You do not have access to this matter.');
    }

    /**
     * @param  EloquentCollection<int, ClientMatter>|Collection<int, ClientMatter>  $matters
     * @return EloquentCollection<int, ClientMatter>|Collection<int, ClientMatter>
     */
    public static function filterMattersForViewer($matters, ?Authenticatable $user = null)
    {
        $user = $user ?? Auth::guard('admin')->user();
        if (! self::isEnabled() || ! $user || self::bypassesMatterTeamEnforcement($user) || StaffClientVisibility::seesAllClientMatters($user)) {
            return $matters;
        }

        return $matters->filter(function ($matter) use ($user) {
            return self::canAccessClientMatter((int) $matter->id, $user);
        })->values();
    }

    /**
     * Hide client_matters rows the viewer is not assigned to.
     * No-op for Super Admin, Calling Team, Kabir, Ajay, and Khusi.
     *
     * @param  \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder  $query
     */
    public static function constrainMatterRows($query, string $table = 'client_matters', ?Authenticatable $user = null): void
    {
        $user = $user ?? Auth::guard('admin')->user();
        if (! self::isEnabled() || ! $user || self::bypassesMatterTeamEnforcement($user) || StaffClientVisibility::seesAllClientMatters($user)) {
            return;
        }

        if (! $user instanceof Staff) {
            $query->whereRaw('1 = 0');

            return;
        }

        $staffId = (int) $user->id;
        $query->where(function ($q) use ($staffId, $table) {
            $q->where($table.'.sel_legal_practitioner', $staffId)
                ->orWhere($table.'.sel_person_responsible', $staffId)
                ->orWhere($table.'.sel_person_assisting', $staffId)
                ->orWhereExists(function ($sub) use ($staffId, $table) {
                    $sub->select(DB::raw('1'))
                        ->from('client_access_grants')
                        ->whereColumn('client_access_grants.admin_id', $table.'.client_id')
                        ->where('client_access_grants.staff_id', $staffId)
                        ->where('client_access_grants.grant_type', self::GRANT_TYPE_MATTER_TEMP)
                        ->where('client_access_grants.status', 'active')
                        ->whereNotNull('client_access_grants.ends_at')
                        ->whereRaw('client_access_grants.ends_at > NOW()')
                        ->where(function ($note) use ($table) {
                            $note->whereRaw("client_access_grants.requester_note = CONCAT('matter_id:', {$table}.id)")
                                ->orWhereRaw("client_access_grants.requester_note LIKE CONCAT('matter_id:', {$table}.id, ';role:%')");
                        });
                });
        });
    }

    /**
     * Matter IDs on this client the viewer may open (assigned or temporary grant).
     *
     * @return list<int>
     */
    public static function accessibleMatterIdsForClient(int $clientAdminId, ?Authenticatable $user = null): array
    {
        $user = $user ?? Auth::guard('admin')->user();
        if (! $user instanceof Staff || $clientAdminId <= 0) {
            return [];
        }

        if (! self::isEnabled() || self::bypassesMatterTeamEnforcement($user) || StaffClientVisibility::seesAllClientMatters($user)) {
            return ClientMatter::query()
                ->where('client_id', $clientAdminId)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $staffId = (int) $user->id;
        $assigned = ClientMatter::query()
            ->where('client_id', $clientAdminId)
            ->where(function ($q) use ($staffId) {
                $q->where('sel_legal_practitioner', $staffId)
                    ->orWhere('sel_person_responsible', $staffId)
                    ->orWhere('sel_person_assisting', $staffId);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $granted = ClientAccessGrant::query()
            ->where('staff_id', $staffId)
            ->where('admin_id', $clientAdminId)
            ->where('grant_type', self::GRANT_TYPE_MATTER_TEMP)
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '>', Carbon::now('UTC'))
            ->pluck('requester_note')
            ->map(fn ($note) => self::parseMatterIdFromGrantNote($note))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique(array_merge($assigned, $granted)));
    }

    /**
     * Revoke active temporary matter grants for one staff member on one matter.
     */
    public static function revokeActiveTemporaryGrantsForStaffOnMatter(
        int $staffId,
        int $clientMatterId,
        string $revokeReason
    ): int {
        if ($staffId <= 0 || $clientMatterId <= 0) {
            return 0;
        }

        $clientAdminId = (int) ClientMatter::query()->where('id', $clientMatterId)->value('client_id');
        if ($clientAdminId <= 0) {
            return 0;
        }

        $now = Carbon::now('UTC');

        $query = ClientAccessGrant::query()
            ->where('staff_id', $staffId)
            ->where('admin_id', $clientAdminId)
            ->where('grant_type', self::GRANT_TYPE_MATTER_TEMP)
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '>', $now);

        self::applyMatterGrantNoteFilter($query, $clientMatterId);

        $grants = $query->get(['id', 'staff_id', 'requester_note']);
        if ($grants->isEmpty()) {
            return 0;
        }

        $ids = $grants->pluck('id')->all();
        $updated = ClientAccessGrant::query()
            ->whereIn('id', $ids)
            ->update([
                'status' => 'revoked',
                'revoked_at' => $now,
                'revoke_reason' => $revokeReason,
            ]);

        foreach ($grants as $grant) {
            $matterId = self::parseMatterIdFromGrantNote($grant->requester_note);
            $roleKey = self::parseRoleFromGrantNote($grant->requester_note);
            if ($matterId !== null && $roleKey !== null) {
                self::clearMatterTeamRoleIfStaff($matterId, $roleKey, (int) $grant->staff_id);
            }
        }

        return (int) $updated;
    }

    /**
     * Revoke active temporary grants for one staff member, matter, and team role.
     */
    public static function revokeActiveTemporaryGrantsForStaffRoleOnMatter(
        int $staffId,
        int $clientMatterId,
        string $roleKey,
        string $revokeReason
    ): int {
        if ($staffId <= 0 || $clientMatterId <= 0 || ! self::isValidMatterTeamRoleKey($roleKey)) {
            return 0;
        }

        $clientAdminId = (int) ClientMatter::query()->where('id', $clientMatterId)->value('client_id');
        if ($clientAdminId <= 0) {
            return 0;
        }

        $now = Carbon::now('UTC');

        $query = ClientAccessGrant::query()
            ->where('staff_id', $staffId)
            ->where('admin_id', $clientAdminId)
            ->where('grant_type', self::GRANT_TYPE_MATTER_TEMP)
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '>', $now);

        self::applyMatterGrantNoteFilter($query, $clientMatterId, $roleKey);

        $grants = $query->get(['id', 'staff_id', 'requester_note']);
        $updated = 0;
        if ($grants->isNotEmpty()) {
            $updated = ClientAccessGrant::query()
                ->whereIn('id', $grants->pluck('id')->all())
                ->update([
                    'status' => 'revoked',
                    'revoked_at' => $now,
                    'revoke_reason' => $revokeReason,
                ]);
        }

        self::clearMatterTeamRoleIfStaff($clientMatterId, $roleKey, $staffId);

        return (int) $updated;
    }
}
