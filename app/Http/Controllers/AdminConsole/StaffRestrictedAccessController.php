<?php

namespace App\Http\Controllers\AdminConsole;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\Staff;
use App\Models\StaffAccessAudit;
use App\Models\StaffAccessProfile;
use App\Models\StaffRecordGrant;
use App\Services\CrmAccess\StaffAccessGate;
use App\Services\CrmAccess\StaffAccessInventoryService;
use App\Services\CrmAccess\StaffRestrictedAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * Step 7 — Super Admin UI to manage restricted staff profiles, widgets, and record grants.
 * Step 8 — Audit log viewer for staff_access_audit.
 */
class StaffRestrictedAccessController extends Controller
{
    public function __construct(
        protected StaffRestrictedAccessService $restrictedAccess,
        protected StaffAccessGate $gate,
        protected StaffAccessInventoryService $inventory
    ) {
        $this->middleware('auth:admin');
    }

    public function index(Request $request)
    {
        if ($deny = $this->denyUnlessSuperAdmin()) {
            return $deny;
        }

        $search = trim((string) $request->get('q', ''));
        $modeFilter = trim((string) $request->get('mode', ''));
        $activeGrant = static function ($q) {
            $q->whereNull('revoked_at');
        };

        $query = Staff::query()
            ->with(['usertype', 'accessProfile'])
            ->withCount([
                'recordGrants as active_grants_count' => $activeGrant,
                'recordGrants as clients_grants_count' => function ($q) use ($activeGrant) {
                    $activeGrant($q);
                    $q->where('resource_type', StaffRecordGrant::TYPE_CLIENT);
                },
                'recordGrants as leads_grants_count' => function ($q) use ($activeGrant) {
                    $activeGrant($q);
                    $q->where('resource_type', StaffRecordGrant::TYPE_LEAD);
                },
                'recordGrants as matters_grants_count' => function ($q) use ($activeGrant) {
                    $activeGrant($q);
                    $q->where('resource_type', StaffRecordGrant::TYPE_MATTER);
                },
                'recordGrants as closed_matters_grants_count' => function ($q) use ($activeGrant) {
                    $activeGrant($q);
                    $q->where('resource_type', StaffRecordGrant::TYPE_CLOSED_MATTER);
                },
            ])
            ->orderBy('first_name')
            ->orderBy('last_name');

        if ($search !== '') {
            $like = '%' . strtolower($search) . '%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(first_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
            });
        }

        if (in_array($modeFilter, ['full', 'restricted'], true)) {
            $query->whereHas('accessProfile', function ($q) use ($modeFilter) {
                $q->where('access_mode', $modeFilter);
            });
        } elseif ($modeFilter === 'default') {
            $query->whereDoesntHave('accessProfile');
        }

        $lists = $query->paginate(25)->appends($request->only(['q', 'mode']));
        $featureEnabled = $this->restrictedAccess->isFeatureEnabled();

        return view('AdminConsole.system.staff-access.index', compact('lists', 'search', 'modeFilter', 'featureEnabled'));
    }

    public function edit(int $id)
    {
        if ($deny = $this->denyUnlessSuperAdmin()) {
            return $deny;
        }

        $staff = Staff::query()->with(['usertype', 'accessProfile'])->findOrFail($id);
        $profile = $staff->accessProfile;
        $grants = StaffRecordGrant::query()
            ->where('staff_id', (int) $staff->id)
            ->whereNull('revoked_at')
            ->orderByDesc('id')
            ->get();

        $grantLabels = $this->resolveGrantLabels($grants);
        $accessOverview = $this->restrictedAccess->accessOverview($staff, $this->gate);
        $accessInventory = $this->inventory->inventory($staff, 80);
        $knownWidgets = $this->restrictedAccess->knownDashboardWidgets();
        $knownScopes = $this->restrictedAccess->knownGrantScopes();
        $knownNavModules = $this->restrictedAccess->knownNavModules();
        $knownNavModuleActions = $this->restrictedAccess->knownNavModuleActions();
        $knownAdminConsoleModules = $this->restrictedAccess->knownAdminConsoleModules();
        $featureEnabled = $this->restrictedAccess->isFeatureEnabled();
        $isRestrictedNow = $this->gate->isRestricted($staff);

        return view('AdminConsole.system.staff-access.edit', compact(
            'staff',
            'profile',
            'grants',
            'grantLabels',
            'accessOverview',
            'accessInventory',
            'knownWidgets',
            'knownScopes',
            'knownNavModules',
            'knownNavModuleActions',
            'knownAdminConsoleModules',
            'featureEnabled',
            'isRestrictedNow'
        ));
    }

    /**
     * JSON inventory of existing CRM access (allocation + cross grants + restricted).
     * Read-only — does not change access.
     */
    public function inventoryJson(int $id)
    {
        if ($deny = $this->denyUnlessSuperAdmin(true)) {
            return $deny;
        }

        $staff = Staff::query()->with('accessProfile')->findOrFail($id);
        $limit = (int) request()->integer('limit', 80);

        return response()->json([
            'staff_id' => (int) $staff->id,
            'inventory' => $this->inventory->inventory($staff, $limit),
        ]);
    }

    public function updateProfile(Request $request, int $id)
    {
        if ($deny = $this->denyUnlessSuperAdmin()) {
            return $deny;
        }

        if (! $this->restrictedAccess->isFeatureEnabled()) {
            return back()->with('error', 'Enable CRM_RESTRICTED_ACCESS_ENABLED locally before managing staff access.');
        }

        $staff = Staff::query()->findOrFail($id);
        $actor = Auth::guard('admin')->user();

        $validated = $request->validate([
            'access_mode' => ['required', Rule::in([StaffAccessProfile::MODE_FULL, StaffAccessProfile::MODE_RESTRICTED])],
            'dashboard_widgets' => ['nullable', 'array'],
            'dashboard_widgets.*' => ['string', Rule::in($this->restrictedAccess->knownDashboardWidgets())],
            'nav_modules' => ['nullable', 'array'],
            'admin_console_modules' => ['nullable', 'array'],
            'admin_console_modules.*' => ['string', Rule::in($this->restrictedAccess->knownAdminConsoleModules())],
            'can_view_own_profile' => ['nullable', 'boolean'],
            'can_view_dashboard' => ['nullable', 'boolean'],
        ]);

        $this->restrictedAccess->updateAccessProfile(
            $staff,
            $validated['access_mode'],
            $validated['dashboard_widgets'] ?? [],
            $request->boolean('can_view_own_profile'),
            $request->boolean('can_view_dashboard'),
            $actor instanceof Staff ? $actor : null,
            is_array($request->input('nav_modules')) ? $request->input('nav_modules') : [],
            $validated['admin_console_modules'] ?? []
        );

        return redirect()
            ->route('adminconsole.system.staff-access.edit', $staff->id)
            ->with('success', 'Access profile saved.');
    }

    public function storeGrant(Request $request, int $id)
    {
        if ($deny = $this->denyUnlessSuperAdmin()) {
            return $deny;
        }

        if (! $this->restrictedAccess->isFeatureEnabled()) {
            return back()->with('error', 'Enable CRM_RESTRICTED_ACCESS_ENABLED locally before managing staff access.');
        }

        $staff = Staff::query()->findOrFail($id);
        $actor = Auth::guard('admin')->user();

        $validated = $request->validate([
            // Multi-select basket (preferred).
            'items' => ['nullable', 'array', 'max:100'],
            'items.*.type' => ['required_with:items', Rule::in(StaffRecordGrant::grantableResourceTypes())],
            'items.*.id' => ['required_with:items', 'integer', 'min:1'],
            // Unchecked "already has access" rows → strip existing CRM access.
            'revoke_items' => ['nullable', 'array', 'max:100'],
            'revoke_items.*.type' => ['required_with:revoke_items', Rule::in(StaffRecordGrant::grantableResourceTypes())],
            'revoke_items.*.id' => ['required_with:revoke_items', 'integer', 'min:1'],
            // Single-grant fallback (older forms).
            'resource_type' => ['nullable', Rule::in(StaffRecordGrant::grantableResourceTypes())],
            'resource_id' => ['nullable', 'integer', 'min:1'],
            'scopes' => ['nullable', 'array'],
            'scopes.*' => ['string', Rule::in($this->restrictedAccess->knownGrantScopes())],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $actorStaff = $actor instanceof Staff ? $actor : null;
        $notes = $validated['notes'] ?? null;

        $revokeTargets = [];
        foreach (($validated['revoke_items'] ?? []) as $item) {
            $type = (string) ($item['type'] ?? '');
            $rid = (int) ($item['id'] ?? 0);
            if ($type === '' || $rid <= 0) {
                continue;
            }
            $revokeTargets[$type . ':' . $rid] = ['type' => $type, 'id' => $rid];
        }

        $revoked = 0;
        foreach ($revokeTargets as $target) {
            $counts = $this->inventory->removeExistingAccess(
                $staff,
                $target['type'],
                $target['id'],
                $actorStaff,
                $notes
            );
            if (($counts['restricted'] + $counts['cross'] + $counts['allocation'] + $counts['owner']) > 0) {
                $revoked++;
            }
        }

        $targets = [];
        foreach (($validated['items'] ?? []) as $item) {
            $type = (string) ($item['type'] ?? '');
            $rid = (int) ($item['id'] ?? 0);
            if ($type === '' || $rid <= 0) {
                continue;
            }
            // Do not re-grant something we are also revoking in the same save.
            if (isset($revokeTargets[$type . ':' . $rid])) {
                continue;
            }
            $targets[$type . ':' . $rid] = ['type' => $type, 'id' => $rid];
        }

        if ($targets === [] && ! empty($validated['resource_type']) && ! empty($validated['resource_id'])) {
            $targets[$validated['resource_type'] . ':' . (int) $validated['resource_id']] = [
                'type' => (string) $validated['resource_type'],
                'id' => (int) $validated['resource_id'],
            ];
        }

        if ($targets === [] && $revoked === 0) {
            return back()->with('error', 'Tick records to grant, or untick existing access to remove it.')->withInput();
        }

        $saved = 0;
        $skipped = 0;
        foreach ($targets as $target) {
            if (! $this->resourceExists($target['type'], $target['id'])) {
                $skipped++;
                continue;
            }

            $this->restrictedAccess->grantRecord(
                $staff,
                $target['type'],
                $target['id'],
                $validated['scopes'] ?? [],
                $actorStaff,
                $notes
            );
            $saved++;
        }

        if ($saved === 0 && $revoked === 0) {
            return back()->with('error', 'No grants saved and no existing access removed.')->withInput();
        }

        $parts = [];
        if ($saved > 0) {
            $parts[] = $saved === 1 ? '1 grant saved' : $saved . ' grants saved';
        }
        if ($revoked > 0) {
            $parts[] = $revoked === 1 ? '1 existing access removed' : $revoked . ' existing access removed';
        }
        $msg = implode('. ', $parts) . '.';
        if ($skipped > 0) {
            $msg .= ' ' . $skipped . ' skipped (not found).';
        }

        return redirect()
            ->route('adminconsole.system.staff-access.edit', $staff->id)
            ->with('success', $msg);
    }

    public function revokeGrant(Request $request, int $id, int $grantId)
    {
        if ($deny = $this->denyUnlessSuperAdmin()) {
            return $deny;
        }

        $staff = Staff::query()->findOrFail($id);
        $grant = StaffRecordGrant::query()
            ->where('staff_id', (int) $staff->id)
            ->where('id', $grantId)
            ->firstOrFail();

        $actor = Auth::guard('admin')->user();
        $this->restrictedAccess->revokeGrant(
            $grant,
            $actor instanceof Staff ? $actor : null,
            $request->input('notes')
        );

        return redirect()
            ->route('adminconsole.system.staff-access.edit', $staff->id)
            ->with('success', 'Grant revoked.');
    }

    /**
     * Step 8 — Paginated audit log of profile/grant changes.
     */
    public function audit(Request $request)
    {
        if ($deny = $this->denyUnlessSuperAdmin()) {
            return $deny;
        }

        if (! Schema::hasTable('staff_access_audit')) {
            return view('AdminConsole.system.staff-access.audit', [
                'logs' => collect(),
                'filters' => [],
                'staffOptions' => collect(),
                'actionOptions' => [],
                'tableMissing' => true,
            ]);
        }

        $filters = [
            'target_staff_id' => (int) $request->get('target_staff_id', 0),
            'actor_staff_id' => (int) $request->get('actor_staff_id', 0),
            'action' => trim((string) $request->get('action', '')),
            'from' => trim((string) $request->get('from', '')),
            'to' => trim((string) $request->get('to', '')),
        ];

        $query = StaffAccessAudit::query()
            ->with(['actor:id,first_name,last_name,email', 'target:id,first_name,last_name,email'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($filters['target_staff_id'] > 0) {
            $query->where('target_staff_id', $filters['target_staff_id']);
        }
        if ($filters['actor_staff_id'] > 0) {
            $query->where('actor_staff_id', $filters['actor_staff_id']);
        }
        if ($filters['action'] !== '') {
            $query->where('action', $filters['action']);
        }
        if ($filters['from'] !== '') {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if ($filters['to'] !== '') {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        $logs = $query->paginate(40)->appends($request->only([
            'target_staff_id', 'actor_staff_id', 'action', 'from', 'to',
        ]));

        $staffOptions = Staff::query()
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'email']);

        $actionOptions = StaffAccessAudit::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->all();

        return view('AdminConsole.system.staff-access.audit', [
            'logs' => $logs,
            'filters' => $filters,
            'staffOptions' => $staffOptions,
            'actionOptions' => $actionOptions,
            'tableMissing' => false,
        ]);
    }

    /**
     * Step 8 — Single audit entry detail (before/after JSON).
     */
    public function auditShow(int $auditId)
    {
        if ($deny = $this->denyUnlessSuperAdmin()) {
            return $deny;
        }

        $entry = StaffAccessAudit::query()
            ->with(['actor:id,first_name,last_name,email', 'target:id,first_name,last_name,email'])
            ->findOrFail($auditId);

        return view('AdminConsole.system.staff-access.audit-show', compact('entry'));
    }

    /**
     * Browse / filter clients, leads, matters for the multi-grant picker.
     * Empty q returns a page of recent/alphabetical records (browse mode).
     */
    public function searchResources(Request $request)
    {
        if ($deny = $this->denyUnlessSuperAdmin(true)) {
            return $deny;
        }

        $type = strtolower(trim((string) $request->get('type', 'client')));
        if (! in_array($type, StaffRecordGrant::grantableResourceTypes(), true)) {
            $type = StaffRecordGrant::TYPE_CLIENT;
        }

        $q = trim((string) $request->get('q', ''));
        $page = max(1, (int) $request->get('page', 1));
        $perPage = min(100, max(10, (int) $request->get('per_page', 40)));
        $staffId = (int) $request->get('staff_id', 0);

        // Includes allocation, owner, cross-access, and restricted grants — pre-check picker rows.
        $alreadyGranted = $staffId > 0
            ? $this->inventory->accessibleResourceIds($staffId, $type)
            : [];
        $alreadyLookup = array_fill_keys($alreadyGranted, true);

        $results = [];
        $total = 0;

        if (StaffRecordGrant::isMatterResourceType($type)) {
            $closedStages = ClientMatter::closedWorkflowStageNames();
            $query = ClientMatter::query()
                ->with(['client:id,first_name,last_name,client_id,type', 'workflowStage:id,name']);

            if ($type === StaffRecordGrant::TYPE_CLOSED_MATTER) {
                $query->where(function ($outer) use ($closedStages) {
                    $outer->where('matter_status', 0)
                        ->orWhereHas('workflowStage', function ($wq) use ($closedStages) {
                            $wq->whereRaw(
                                'LOWER(TRIM(name)) IN (' . implode(',', array_fill(0, count($closedStages), '?')) . ')',
                                $closedStages
                            );
                        });
                });
            } else {
                $query->where('matter_status', 1)
                    ->where(function ($outer) use ($closedStages) {
                        $outer->whereDoesntHave('workflowStage')
                            ->orWhereHas('workflowStage', function ($wq) use ($closedStages) {
                                $wq->whereRaw(
                                    'LOWER(TRIM(name)) NOT IN (' . implode(',', array_fill(0, count($closedStages), '?')) . ')',
                                    $closedStages
                                );
                            });
                    });
            }

            if ($q !== '') {
                $like = '%' . strtolower($q) . '%';
                $query->where(function ($outer) use ($like) {
                    $outer->whereRaw('LOWER(client_unique_matter_no) LIKE ?', [$like])
                        ->orWhereHas('client', function ($cq) use ($like) {
                            $cq->whereRaw('LOWER(client_id) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(first_name) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(last_name) LIKE ?', [$like]);
                        });
                });
            }

            $total = (clone $query)->count();
            if ($alreadyGranted !== []) {
                $placeholders = implode(',', array_fill(0, count($alreadyGranted), '?'));
                $query->orderByRaw('CASE WHEN client_matters.id IN (' . $placeholders . ') THEN 0 ELSE 1 END', $alreadyGranted);
            }
            $rows = $query
                ->orderByDesc('updated_at')
                ->forPage($page, $perPage)
                ->get(['id', 'client_id', 'client_unique_matter_no', 'matter_status', 'workflow_stage_id']);

            foreach ($rows as $row) {
                $client = $row->client;
                $clientName = trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? ''));
                $prefix = $type === StaffRecordGrant::TYPE_CLOSED_MATTER ? 'Closed ' : '';
                $id = (int) $row->id;
                $results[] = [
                    'id' => $id,
                    'label' => trim($prefix . ($row->client_unique_matter_no ?? ('Matter #' . $row->id)) . ' — ' . $clientName . ' (' . ($client->client_id ?? '') . ')'),
                    'type' => $type,
                    'granted' => isset($alreadyLookup[$id]),
                ];
            }
        } else {
            $adminType = $type === StaffRecordGrant::TYPE_LEAD ? 'lead' : 'client';
            $query = Admin::query()
                ->where('type', $adminType)
                ->whereNull('is_deleted');

            if ($q !== '') {
                $like = '%' . strtolower($q) . '%';
                $query->where(function ($outer) use ($like) {
                    $outer->whereRaw('LOWER(client_id) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(first_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
                });
            }

            $total = (clone $query)->count();
            if ($alreadyGranted !== []) {
                $placeholders = implode(',', array_fill(0, count($alreadyGranted), '?'));
                $query->orderByRaw('CASE WHEN admins.id IN (' . $placeholders . ') THEN 0 ELSE 1 END', $alreadyGranted);
            }
            $rows = $query
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->forPage($page, $perPage)
                ->get(['id', 'first_name', 'last_name', 'client_id', 'email', 'type']);

            foreach ($rows as $row) {
                $name = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));
                $id = (int) $row->id;
                $results[] = [
                    'id' => $id,
                    'label' => trim($name . ' — ' . ($row->client_id ?? '') . ' (' . ($row->email ?? '') . ')'),
                    'type' => $adminType,
                    'granted' => isset($alreadyLookup[$id]),
                ];
            }
        }

        $lastPage = max(1, (int) ceil($total / $perPage));

        return response()->json([
            'results' => $results,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => $lastPage,
            'has_more' => $page < $lastPage,
            'already_count' => count($alreadyGranted),
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, StaffRecordGrant>  $grants
     * @return array<int, string>
     */
    protected function resolveGrantLabels($grants): array
    {
        $labels = [];
        $clientIds = [];
        $matterIds = [];

        foreach ($grants as $grant) {
            if (in_array($grant->resource_type, [StaffRecordGrant::TYPE_CLIENT, StaffRecordGrant::TYPE_LEAD], true)) {
                $clientIds[] = (int) $grant->resource_id;
            } elseif (StaffRecordGrant::isMatterResourceType((string) $grant->resource_type)) {
                $matterIds[] = (int) $grant->resource_id;
            }
        }

        $admins = $clientIds === []
            ? collect()
            : Admin::query()->whereIn('id', array_unique($clientIds))->get(['id', 'first_name', 'last_name', 'client_id', 'type'])->keyBy('id');

        $matters = $matterIds === []
            ? collect()
            : ClientMatter::query()
                ->with(['client:id,first_name,last_name,client_id'])
                ->whereIn('id', array_unique($matterIds))
                ->get(['id', 'client_id', 'client_unique_matter_no'])
                ->keyBy('id');

        foreach ($grants as $grant) {
            if (in_array($grant->resource_type, [StaffRecordGrant::TYPE_CLIENT, StaffRecordGrant::TYPE_LEAD], true)) {
                $row = $admins->get((int) $grant->resource_id);
                $labels[$grant->id] = $row
                    ? trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '') . ' (' . ($row->client_id ?? ('#' . $row->id)) . ')')
                    : (ucfirst($grant->resource_type) . ' #' . $grant->resource_id);
            } else {
                $row = $matters->get((int) $grant->resource_id);
                $closedPrefix = $grant->resource_type === StaffRecordGrant::TYPE_CLOSED_MATTER ? 'Closed ' : '';
                if ($row) {
                    $client = $row->client;
                    $labels[$grant->id] = trim(
                        $closedPrefix
                        . ($row->client_unique_matter_no ?? ('Matter #' . $row->id))
                        . ' — '
                        . trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? ''))
                    );
                } else {
                    $labels[$grant->id] = $closedPrefix . 'Matter #' . $grant->resource_id;
                }
            }
        }

        return $labels;
    }

    protected function resourceExists(string $type, int $id): bool
    {
        if (StaffRecordGrant::isMatterResourceType($type)) {
            $matter = ClientMatter::query()
                ->with('workflowStage')
                ->where('id', $id)
                ->first();

            if (! $matter) {
                return false;
            }

            $isClosed = ClientMatter::isClosed($matter);

            return $type === StaffRecordGrant::TYPE_CLOSED_MATTER ? $isClosed : ! $isClosed;
        }

        $adminType = $type === StaffRecordGrant::TYPE_LEAD ? 'lead' : 'client';

        return Admin::query()->where('id', $id)->where('type', $adminType)->exists();
    }

    /**
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse|null
     */
    protected function denyUnlessSuperAdmin(bool $json = false)
    {
        $actor = Auth::guard('admin')->user();
        if ($actor instanceof Staff && $this->gate->hasFullCrmAccess($actor)) {
            return null;
        }

        $message = 'Unauthorized: Only Super Admin can manage restricted staff access.';

        if ($json) {
            return response()->json(['success' => false, 'message' => $message], 403);
        }

        return Redirect::to('/dashboard')->with('error', $message);
    }
}
