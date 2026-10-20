<?php

namespace App\Services\CrmAccess;

use App\Models\Admin;
use App\Models\ClientAccessGrant;
use App\Models\ClientMatter;
use App\Models\Staff;
use App\Models\StaffRecordGrant;
use App\Support\StaffClientVisibility;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only inventory of everything a staff member can already see.
 * Does not create, revoke, or change access — Super Admin visibility only.
 */
class StaffAccessInventoryService
{
    public function __construct(
        protected StaffAccessGate $gate
    ) {
    }

    /**
     * @return array{
     *   is_exempt:bool,
     *   is_restricted:bool,
     *   counts:array<string,int>,
     *   clients:list<array<string,mixed>>,
     *   leads:list<array<string,mixed>>,
     *   matters:list<array<string,mixed>>,
     *   cross_grants:list<array<string,mixed>>,
     *   restricted_grants:list<array<string,mixed>>,
     *   email_people:list<array<string,mixed>>,
     *   truncated:array<string,bool>
     * }
     */
    public function inventory(Staff $staff, int $listLimit = 80): array
    {
        $staffId = (int) $staff->id;
        $listLimit = max(10, min(200, $listLimit));
        $isExempt = StaffClientVisibility::isExemptFromAllocation($staff);
        $isRestricted = $this->gate->isRestricted($staff);

        $matterSources = $this->matterAssignments($staffId);
        $ownedAdminIds = $this->ownedAdminIds($staffId);
        $crossGrantRows = $this->activeCrossGrants($staffId);
        $restrictedRows = $this->activeRestrictedGrants($staffId);

        $clientMap = [];
        $leadMap = [];
        $matterList = [];
        $emailIds = [];

        foreach ($matterSources as $row) {
            $adminId = (int) ($row->client_id ?? 0);
            $roles = [];
            if ((int) ($row->sel_legal_practitioner ?? 0) === $staffId) {
                $roles[] = 'Legal Practitioner';
            }
            if ((int) ($row->sel_person_responsible ?? 0) === $staffId) {
                $roles[] = 'Person Responsible';
            }
            if ((int) ($row->sel_person_assisting ?? 0) === $staffId) {
                $roles[] = 'Person Assisting';
            }

            $matterList[] = [
                'id' => (int) $row->id,
                'label' => $this->matterLabel($row),
                'client_id' => $adminId,
                'person_type' => (string) ($row->person_type ?? ''),
                'roles' => $roles,
                'source' => 'allocation',
            ];

            if ($adminId > 0) {
                $emailIds[$adminId] = true;
                $source = 'Matter allocation: ' . implode(', ', $roles);
                if (($row->person_type ?? '') === 'lead') {
                    $this->mergePerson($leadMap, $adminId, $row, $source);
                } else {
                    $this->mergePerson($clientMap, $adminId, $row, $source);
                }
            }
        }

        foreach ($ownedAdminIds as $admin) {
            $adminId = (int) $admin->id;
            $emailIds[$adminId] = true;
            $ownerSource = 'Record owner (created by / user_id)';
            if (($admin->type ?? '') === 'lead') {
                $this->mergePerson($leadMap, $adminId, $admin, $ownerSource);
            } else {
                $this->mergePerson($clientMap, $adminId, $admin, $ownerSource);
            }
        }

        $crossList = [];
        foreach ($crossGrantRows as $grant) {
            $adminId = (int) $grant->admin_id;
            $admin = $grant->admin;
            $emailIds[$adminId] = true;
            $recordType = (string) ($grant->record_type ?: ($admin->type ?? 'client'));
            $crossList[] = [
                'id' => (int) $grant->id,
                'admin_id' => $adminId,
                'label' => $this->adminLabel($admin) ?: ('#' . $adminId),
                'grant_type' => (string) ($grant->grant_type ?? ''),
                'access_type' => (string) ($grant->access_type ?? ''),
                'ends_at' => optional($grant->ends_at)?->toDateTimeString(),
                'source' => 'cross_grant',
            ];
            if (($recordType === 'lead') || (($admin->type ?? '') === 'lead')) {
                $this->mergePerson($leadMap, $adminId, $admin, 'Cross-access grant (' . ($grant->grant_type ?: 'active') . ')');
            } else {
                $this->mergePerson($clientMap, $adminId, $admin, 'Cross-access grant (' . ($grant->grant_type ?: 'active') . ')');
            }
        }

        $restrictedList = [];
        foreach ($restrictedRows as $grant) {
            $type = (string) $grant->resource_type;
            $rid = (int) $grant->resource_id;
            $label = '#' . $rid;
            if (in_array($type, [StaffRecordGrant::TYPE_CLIENT, StaffRecordGrant::TYPE_LEAD], true)) {
                $admin = Admin::query()->find($rid, ['id', 'first_name', 'last_name', 'client_id', 'email', 'type']);
                $label = $this->adminLabel($admin) ?: $label;
                $emailIds[$rid] = true;
                if ($type === StaffRecordGrant::TYPE_LEAD) {
                    $this->mergePerson($leadMap, $rid, $admin, 'Restricted grant');
                } else {
                    $this->mergePerson($clientMap, $rid, $admin, 'Restricted grant');
                }
            } elseif (StaffRecordGrant::isMatterResourceType($type)) {
                $matter = ClientMatter::query()
                    ->with(['client:id,first_name,last_name,client_id,type'])
                    ->find($rid, ['id', 'client_id', 'client_unique_matter_no']);
                if ($matter) {
                    $label = trim(($type === StaffRecordGrant::TYPE_CLOSED_MATTER ? 'Closed ' : '')
                        . ($matter->client_unique_matter_no ?? ('Matter #' . $matter->id))
                        . ' — ' . $this->adminLabel($matter->client));
                    if ((int) $matter->client_id > 0) {
                        $emailIds[(int) $matter->client_id] = true;
                    }
                }
            }

            $restrictedList[] = [
                'id' => (int) $grant->id,
                'resource_type' => $type,
                'resource_id' => $rid,
                'label' => $label,
                'scopes' => is_array($grant->scopes) ? $grant->scopes : [],
                'source' => 'restricted_grant',
            ];
        }

        // Restricted mode: email people from gate (grants only). Otherwise all linked people.
        if ($isRestricted && ! $this->gate->hasFullCrmAccess($staff)) {
            $emailIdList = $this->gate->allowedAdminIds($staff);
        } else {
            $emailIdList = array_keys($emailIds);
        }

        $emailPeople = $this->loadAdminSummaries($emailIdList, $listLimit);
        $clients = array_values($clientMap);
        $leads = array_values($leadMap);

        usort($clients, fn ($a, $b) => strcasecmp((string) $a['label'], (string) $b['label']));
        usort($leads, fn ($a, $b) => strcasecmp((string) $a['label'], (string) $b['label']));
        usort($matterList, fn ($a, $b) => strcasecmp((string) $a['label'], (string) $b['label']));

        return [
            'is_exempt' => $isExempt,
            'is_restricted' => $isRestricted,
            'counts' => [
                'clients' => count($clients),
                'leads' => count($leads),
                'matters' => count($matterList),
                'cross_grants' => count($crossList),
                'restricted_grants' => count($restrictedList),
                'email_people' => count($emailIdList),
            ],
            'clients' => array_slice($clients, 0, $listLimit),
            'leads' => array_slice($leads, 0, $listLimit),
            'matters' => array_slice($matterList, 0, $listLimit),
            'cross_grants' => array_slice($crossList, 0, $listLimit),
            'restricted_grants' => array_slice($restrictedList, 0, $listLimit),
            'email_people' => $emailPeople,
            'truncated' => [
                'clients' => count($clients) > $listLimit,
                'leads' => count($leads) > $listLimit,
                'matters' => count($matterList) > $listLimit,
                'cross_grants' => count($crossList) > $listLimit,
                'restricted_grants' => count($restrictedList) > $listLimit,
                'email_people' => count($emailIdList) > $listLimit,
            ],
        ];
    }

    /**
     * Super Admin remove: strip existing access for one resource (allocation, owner,
     * cross-grant, and restricted grant). Does not touch unrelated records.
     *
     * @return array{restricted:int, cross:int, allocation:int, owner:int}
     */
    public function removeExistingAccess(
        Staff $target,
        string $resourceType,
        int $resourceId,
        ?Staff $actor = null,
        ?string $notes = null
    ): array {
        $resourceType = strtolower(trim($resourceType));
        $resourceId = (int) $resourceId;
        $staffId = (int) $target->id;
        $counts = ['restricted' => 0, 'cross' => 0, 'allocation' => 0, 'owner' => 0];

        if ($staffId <= 0 || $resourceId <= 0 || ! in_array($resourceType, StaffRecordGrant::grantableResourceTypes(), true)) {
            return $counts;
        }

        $noteLine = $notes !== null && trim($notes) !== ''
            ? trim($notes)
            : 'Removed via Staff Access picker';

        if (Schema::hasTable('staff_record_grants')) {
            $grants = StaffRecordGrant::query()
                ->active()
                ->where('staff_id', $staffId)
                ->where('resource_type', $resourceType)
                ->where('resource_id', $resourceId)
                ->get();

            $svc = app(StaffRestrictedAccessService::class);
            foreach ($grants as $grant) {
                $svc->revokeGrant($grant, $actor, $noteLine);
                $counts['restricted']++;
            }
        }

        if (in_array($resourceType, [StaffRecordGrant::TYPE_CLIENT, StaffRecordGrant::TYPE_LEAD], true)) {
            if (Schema::hasTable('client_access_grants')) {
                $updated = ClientAccessGrant::query()
                    ->where('staff_id', $staffId)
                    ->where('admin_id', $resourceId)
                    ->where('status', 'active')
                    ->update([
                        'status' => 'revoked',
                        'revoked_at' => now(),
                        'revoke_reason' => $noteLine,
                    ]);
                $counts['cross'] = (int) $updated;
            }

            if (Schema::hasTable('client_matters')) {
                foreach (['sel_legal_practitioner', 'sel_person_responsible', 'sel_person_assisting'] as $col) {
                    $n = DB::table('client_matters')
                        ->where('client_id', $resourceId)
                        ->where($col, $staffId)
                        ->update([$col => null]);
                    $counts['allocation'] += (int) $n;
                }
            }

            if (Schema::hasTable('admins')) {
                $n = Admin::query()
                    ->where('id', $resourceId)
                    ->where('user_id', $staffId)
                    ->update(['user_id' => null]);
                $counts['owner'] = (int) $n;
            }
        } elseif (StaffRecordGrant::isMatterResourceType($resourceType) && Schema::hasTable('client_matters')) {
            foreach (['sel_legal_practitioner', 'sel_person_responsible', 'sel_person_assisting'] as $col) {
                $n = DB::table('client_matters')
                    ->where('id', $resourceId)
                    ->where($col, $staffId)
                    ->update([$col => null]);
                $counts['allocation'] += (int) $n;
            }
        }

        return $counts;
    }

    /**
     * Resource IDs this staff already can access for a grantable type
     * (allocation / owner / cross-grant / restricted grant). Used to pre-check the picker.
     *
     * @return list<int>
     */
    public function accessibleResourceIds(int $staffId, string $type): array
    {
        $staffId = (int) $staffId;
        $type = strtolower(trim($type));
        if ($staffId <= 0 || ! in_array($type, StaffRecordGrant::grantableResourceTypes(), true)) {
            return [];
        }

        $ids = [];

        if (in_array($type, [StaffRecordGrant::TYPE_CLIENT, StaffRecordGrant::TYPE_LEAD], true)) {
            $adminType = $type === StaffRecordGrant::TYPE_LEAD ? 'lead' : 'client';

            if (Schema::hasTable('client_matters') && Schema::hasTable('admins')) {
                $fromMatters = DB::table('client_matters as cm')
                    ->join('admins as ad', 'ad.id', '=', 'cm.client_id')
                    ->where('ad.type', $adminType)
                    ->where(function ($q) use ($staffId) {
                        $q->where('cm.sel_legal_practitioner', $staffId)
                            ->orWhere('cm.sel_person_responsible', $staffId)
                            ->orWhere('cm.sel_person_assisting', $staffId);
                    })
                    ->distinct()
                    ->pluck('cm.client_id')
                    ->map(fn ($v) => (int) $v)
                    ->all();
                $ids = array_merge($ids, $fromMatters);
            }

            if (Schema::hasTable('admins')) {
                $owned = Admin::query()
                    ->where('user_id', $staffId)
                    ->where('type', $adminType)
                    ->pluck('id')
                    ->map(fn ($v) => (int) $v)
                    ->all();
                $ids = array_merge($ids, $owned);
            }

            if (Schema::hasTable('client_access_grants')) {
                $cross = ClientAccessGrant::query()
                    ->where('staff_id', $staffId)
                    ->where('status', 'active')
                    ->whereNotNull('ends_at')
                    ->where('ends_at', '>', now())
                    ->where(function ($q) use ($adminType) {
                        $q->where('record_type', $adminType)
                            ->orWhereHas('admin', function ($aq) use ($adminType) {
                                $aq->where('type', $adminType);
                            });
                    })
                    ->pluck('admin_id')
                    ->map(fn ($v) => (int) $v)
                    ->all();
                $ids = array_merge($ids, $cross);
            }
        } elseif (StaffRecordGrant::isMatterResourceType($type)) {
            if (Schema::hasTable('client_matters')) {
                $matterQuery = DB::table('client_matters as cm')
                    ->where(function ($q) use ($staffId) {
                        $q->where('cm.sel_legal_practitioner', $staffId)
                            ->orWhere('cm.sel_person_responsible', $staffId)
                            ->orWhere('cm.sel_person_assisting', $staffId);
                    });

                // Prefer open vs closed when we can detect closed status.
                if (Schema::hasColumn('client_matters', 'matter_status')) {
                    if ($type === StaffRecordGrant::TYPE_CLOSED_MATTER) {
                        $matterQuery->where('cm.matter_status', 0);
                    } else {
                        $matterQuery->where('cm.matter_status', 1);
                    }
                }

                $ids = array_merge(
                    $ids,
                    $matterQuery->pluck('cm.id')->map(fn ($v) => (int) $v)->all()
                );
            }
        }

        if (Schema::hasTable('staff_record_grants')) {
            $restricted = StaffRecordGrant::query()
                ->active()
                ->where('staff_id', $staffId)
                ->where('resource_type', $type)
                ->pluck('resource_id')
                ->map(fn ($v) => (int) $v)
                ->all();
            $ids = array_merge($ids, $restricted);
        }

        return array_values(array_unique(array_filter($ids, static fn (int $id) => $id > 0)));
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    protected function matterAssignments(int $staffId)
    {
        if ($staffId <= 0 || ! Schema::hasTable('client_matters')) {
            return collect();
        }

        return DB::table('client_matters as cm')
            ->leftJoin('admins as ad', 'ad.id', '=', 'cm.client_id')
            ->where(function ($q) use ($staffId) {
                $q->where('cm.sel_legal_practitioner', $staffId)
                    ->orWhere('cm.sel_person_responsible', $staffId)
                    ->orWhere('cm.sel_person_assisting', $staffId);
            })
            ->orderBy('cm.id')
            ->get([
                'cm.id',
                'cm.client_id',
                'cm.client_unique_matter_no',
                'cm.sel_legal_practitioner',
                'cm.sel_person_responsible',
                'cm.sel_person_assisting',
                'ad.first_name',
                'ad.last_name',
                'ad.client_id as file_id',
                'ad.email',
                'ad.type as person_type',
            ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Admin>
     */
    protected function ownedAdminIds(int $staffId)
    {
        if ($staffId <= 0 || ! Schema::hasTable('admins')) {
            return collect();
        }

        return Admin::query()
            ->where('user_id', $staffId)
            ->whereIn('type', ['client', 'lead'])
            ->get(['id', 'first_name', 'last_name', 'client_id', 'email', 'type']);
    }

    /**
     * @return \Illuminate\Support\Collection<int, ClientAccessGrant>
     */
    protected function activeCrossGrants(int $staffId)
    {
        if ($staffId <= 0 || ! Schema::hasTable('client_access_grants')) {
            return collect();
        }

        return ClientAccessGrant::query()
            ->with(['admin:id,first_name,last_name,client_id,email,type'])
            ->where('staff_id', $staffId)
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '>', now())
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, StaffRecordGrant>
     */
    protected function activeRestrictedGrants(int $staffId)
    {
        if ($staffId <= 0 || ! Schema::hasTable('staff_record_grants')) {
            return collect();
        }

        return StaffRecordGrant::query()
            ->active()
            ->where('staff_id', $staffId)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  array<int, array<string, mixed>>  $map
     */
    protected function mergePerson(array &$map, int $adminId, $row, string $source): void
    {
        if ($adminId <= 0) {
            return;
        }

        if (! isset($map[$adminId])) {
            $map[$adminId] = [
                'id' => $adminId,
                'label' => $this->adminLabel($row) ?: ('#' . $adminId),
                'file_id' => is_object($row) ? (string) ($row->client_id ?? $row->file_id ?? '') : '',
                'email' => is_object($row) ? (string) ($row->email ?? '') : '',
                'sources' => [],
            ];
        }

        if (! in_array($source, $map[$adminId]['sources'], true)) {
            $map[$adminId]['sources'][] = $source;
        }
    }

    protected function adminLabel($row): string
    {
        if (! $row) {
            return '';
        }

        $name = trim((string) (($row->first_name ?? '') . ' ' . ($row->last_name ?? '')));
        $file = (string) ($row->client_id ?? $row->file_id ?? '');
        if ($name === '' && $file === '') {
            return '';
        }

        return $file !== '' ? ($name . ' (' . $file . ')') : $name;
    }

    protected function matterLabel(object $row): string
    {
        $code = (string) ($row->client_unique_matter_no ?? '');
        $person = $this->adminLabel($row);

        return trim(($code !== '' ? $code : ('Matter #' . $row->id)) . ($person !== '' ? ' — ' . $person : ''));
    }

    /**
     * @param  list<int>  $ids
     * @return list<array<string,mixed>>
     */
    protected function loadAdminSummaries(array $ids, int $limit): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $id) => $id > 0));
        if ($ids === [] || ! Schema::hasTable('admins')) {
            return [];
        }

        $slice = array_slice($ids, 0, $limit);
        $rows = Admin::query()
            ->whereIn('id', $slice)
            ->get(['id', 'first_name', 'last_name', 'client_id', 'email', 'type'])
            ->keyBy('id');

        $out = [];
        foreach ($slice as $id) {
            $row = $rows->get($id);
            $out[] = [
                'id' => $id,
                'label' => $this->adminLabel($row) ?: ('#' . $id),
                'type' => (string) ($row->type ?? ''),
                'email' => (string) ($row->email ?? ''),
            ];
        }

        return $out;
    }
}
