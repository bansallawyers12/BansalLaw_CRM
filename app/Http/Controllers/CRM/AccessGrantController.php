<?php

namespace App\Http\Controllers\CRM;

use App\Console\Commands\CacheAccessGrantGlobalCounts;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Branch;
use App\Models\ClientAccessGrant;
use App\Models\Team;
use App\Services\CrmAccess\CrmAccessDeniedException;
use App\Services\CrmAccess\CrmAccessService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccessGrantController extends Controller
{
    public function __construct(
        protected CrmAccessService $crmAccess
    ) {}

    public function meta(): JsonResponse
    {
        $user = $this->requireStaff();

        return response()->json([
            'branches' => Branch::query()->orderBy('office_name')->get(['id', 'office_name']),
            'quick_reasons' => collect(config('crm_access.quick_reason_options', []))
                ->map(fn (string $label, string $code) => ['code' => $code, 'label' => $label])
                ->values(),
            'staff_office_id' => $user->office_id,
            'ui' => [
                'show_quick' => \App\Support\StaffClientVisibility::crossAccessUiFlags($user)['show_quick'],
                'show_supervisor' => \App\Support\StaffClientVisibility::crossAccessUiFlags($user)['show_supervisor'],
                'quick_only_role' => \App\Support\StaffClientVisibility::isQuickAccessOnly($user),
            ],
        ]);
    }

    public function quick(Request $request): JsonResponse
    {
        $user = $this->requireStaff();

        $data = $request->validate([
            'admin_id' => ['required', 'integer', 'min:1'],
            'record_type' => ['required', Rule::in(['client', 'lead'])],
            'office_id' => ['required', 'integer', 'min:1'],
            'reason_code' => ['required', 'string', 'max:50'],
        ]);

        if (! Admin::query()->where('id', $data['admin_id'])->where('type', $data['record_type'])->exists()) {
            return response()->json(['message' => 'Record not found.'], 422);
        }

        try {
            $grant = $this->crmAccess->requestQuickGrant(
                $user,
                (int) $data['admin_id'],
                $data['record_type'],
                (int) $data['office_id'],
                null,
                $data['reason_code']
            );
        } catch (CrmAccessDeniedException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'grant' => $grant,
            'message' => 'Quick access granted.',
        ]);
    }

    public function supervisor(Request $request): JsonResponse
    {
        $user = $this->requireStaff();

        $data = $request->validate([
            'admin_id' => ['required', 'integer', 'min:1'],
            'record_type' => ['required', Rule::in(['client', 'lead'])],
            'office_id' => ['required', 'integer', 'min:1'],
            'reason_code' => ['required', 'string', Rule::in(array_keys(config('crm_access.quick_reason_options', [])))],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        if (! Admin::query()->where('id', $data['admin_id'])->where('type', $data['record_type'])->exists()) {
            return response()->json(['message' => 'Record not found.'], 422);
        }

        try {
            $grant = $this->crmAccess->requestSupervisorGrant(
                $user,
                (int) $data['admin_id'],
                $data['record_type'],
                (int) $data['office_id'],
                (string) $data['reason_code'],
                (string) ($data['note'] ?? '')
            );
        } catch (CrmAccessDeniedException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'grant' => $grant,
            'message' => 'Supervisor request submitted.',
        ]);
    }

    public function queuePage()
    {
        $user = $this->requireStaff();
        if (! $this->crmAccess->isApprover($user)) {
            abort(403, 'Not authorized.');
        }

        return view('crm.access.queue', [
            'dataUrl' => route('crm.access.queue.data'),
        ]);
    }

    public function queueData(): JsonResponse
    {
        $user = $this->requireStaff();
        if (! $this->crmAccess->isApprover($user)) {
            abort(403, 'Not authorized.');
        }

        $items = ClientAccessGrant::query()
            ->with(['staff:id,first_name,last_name,email', 'admin:id,first_name,last_name,client_id,type'])
            ->where('status', 'pending')
            ->where('grant_type', 'supervisor_approved')
            // Approvers should not see (or act on) their own requests in the approvals queue.
            // Requesters can still track these in "My grants".
            ->where('staff_id', '!=', (int) $user->id)
            ->orderByDesc('requested_at')
            ->limit(200)
            ->get();

        return response()->json(['items' => $items]);
    }

    /**
     * Compact pending queue for header dropdown (approvers).
     */
    public function queueMini(): JsonResponse
    {
        $user = $this->requireStaff();
        if (! $this->crmAccess->isApprover($user)) {
            abort(403, 'Not authorized.');
        }

        $base = ClientAccessGrant::query()
            ->with(['staff:id,first_name,last_name,email', 'admin:id,first_name,last_name,client_id,type'])
            ->where('status', 'pending')
            ->where('grant_type', 'supervisor_approved')
            ->where('staff_id', '!=', (int) $user->id)
            ->orderByDesc('requested_at');

        $pendingCount = (clone $base)->count();
        $items = (clone $base)->limit(15)->get();

        return response()->json([
            'pending_count' => (int) $pendingCount,
            'items' => $items,
        ]);
    }

    public function approve(ClientAccessGrant $grant): JsonResponse
    {
        $user = $this->requireStaff();
        try {
            $grant = $this->crmAccess->approveGrant($user, (int) $grant->id);
        } catch (CrmAccessDeniedException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        Cache::forget(CacheAccessGrantGlobalCounts::CACHE_KEY);

        return response()->json(['grant' => $grant, 'message' => 'Approved.']);
    }

    public function reject(Request $request, ClientAccessGrant $grant): JsonResponse
    {
        $user = $this->requireStaff();
        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);
        try {
            $grant = $this->crmAccess->rejectGrant($user, (int) $grant->id, (string) ($data['reason'] ?? ''));
        } catch (CrmAccessDeniedException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        Cache::forget(CacheAccessGrantGlobalCounts::CACHE_KEY);

        return response()->json(['grant' => $grant, 'message' => 'Rejected.']);
    }

    public function myGrantsPage()
    {
        $this->requireStaff();

        return view('crm.access.my_grants');
    }

    public function myGrantsData(): JsonResponse
    {
        $user = $this->requireStaff();

        $items = ClientAccessGrant::query()
            ->where('staff_id', (int) $user->id)
            ->orderByDesc('requested_at')
            ->limit(100)
            ->get();

        return response()->json(['items' => $items]);
    }

    public function dashboardPage()
    {
        $user = $this->requireStaff();
        if (! $this->crmAccess->isApprover($user)) {
            abort(403, 'Not authorized.');
        }

        $grantPlaceholder = 999999999;
        $tz = config('app.timezone');
        $now = Carbon::now($tz);

        return view('crm.access.dashboard', [
            'dataUrl' => route('crm.access.dashboard.data'),
            'summaryUrl' => route('crm.access.dashboard.summary'),
            'statsUrl' => route('crm.access.dashboard.stats'),
            'exportUrl' => route('crm.access.dashboard.export'),
            'queueUrl' => route('crm.access.queue.data'),
            'presetRanges' => [
                'today' => [$now->toDateString(), $now->toDateString()],
                'yesterday' => (function () use ($now) {
                    $d = $now->copy()->subDay();

                    return [$d->toDateString(), $d->toDateString()];
                })(),
                'this_week' => [$now->copy()->startOfWeek()->toDateString(), $now->copy()->endOfWeek()->toDateString()],
                'this_month' => [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()],
            ],
            'approveUrlTpl' => str_replace((string) $grantPlaceholder, '__ID__', route('crm.access.approve', ['grant' => $grantPlaceholder])),
            'rejectUrlTpl' => str_replace((string) $grantPlaceholder, '__ID__', route('crm.access.reject', ['grant' => $grantPlaceholder])),
            'branches' => Branch::query()->orderBy('office_name')->get(['id', 'office_name']),
            'teams' => Team::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function dashboardStats(): JsonResponse
    {
        $user = $this->requireStaff();
        if (! $this->crmAccess->isApprover($user)) {
            abort(403, 'Not authorized.');
        }

        $cached = Cache::get(CacheAccessGrantGlobalCounts::CACHE_KEY);
        if ($cached === null) {
            $cached = [
                'pending_count' => ClientAccessGrant::query()->where('status', 'pending')->count(),
                'active_count' => ClientAccessGrant::query()->where('status', 'active')->count(),
            ];
            Cache::put(CacheAccessGrantGlobalCounts::CACHE_KEY, $cached, CacheAccessGrantGlobalCounts::TTL_SECONDS);
        }

        return response()->json($cached);
    }

    public function dashboardSummary(Request $request): JsonResponse
    {
        $user = $this->requireStaff();
        if (! $this->crmAccess->isApprover($user)) {
            abort(403, 'Not authorized.');
        }

        $base = $this->validatedDashboardGrantQuery($request, false);
        $total = (clone $base)->count();
        $distinctRecords = (clone $base)->selectRaw('COUNT(DISTINCT admin_id) as c')->value('c') ?? 0;

        $quickCount = (clone $base)->where('grant_type', 'quick')->count();
        $supervisorCount = (clone $base)->where('grant_type', 'supervisor_approved')->count();
        $exemptCount = (clone $base)->where('grant_type', 'exempt')->count();

        return response()->json([
            'filters' => [
                'matching_rows' => $total,
                'distinct_records' => (int) $distinctRecords,
                'grant_type_quick' => $quickCount,
                'grant_type_supervisor_approved' => $supervisorCount,
                'grant_type_exempt' => $exemptCount,
            ],
        ]);
    }

    public function dashboardData(Request $request): JsonResponse
    {
        $user = $this->requireStaff();
        if (! $this->crmAccess->isApprover($user)) {
            abort(403, 'Not authorized.');
        }

        $base = $this->validatedDashboardGrantQuery($request, true);
        $perPage = min(max((int) $request->input('per_page', 50), 1), 100);

        $paginator = (clone $base)
            ->with(['staff:id,first_name,last_name,email', 'admin:id,first_name,last_name,type', 'approvedBy:id,first_name,last_name'])
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'rows' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    public function dashboardExport(Request $request): StreamedResponse
    {
        $user = $this->requireStaff();
        if (! $this->crmAccess->isApprover($user)) {
            abort(403, 'Not authorized.');
        }

        $base = $this->validatedDashboardGrantQuery($request, false);
        $filename = 'client_access_grants_' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($base) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'id', 'staff_id', 'admin_id', 'record_type', 'grant_type', 'access_type', 'status',
                'approved_by_staff_id', 'quick_reason_code', 'office_id', 'team_id', 'requested_at', 'approved_at', 'starts_at', 'ends_at',
                'revoked_at', 'revoke_reason', 'requester_note',
            ]);

            $q = clone $base;
            $q->orderBy('id')->chunkById(500, function ($chunk) use ($out) {
                foreach ($chunk as $g) {
                    fputcsv($out, [
                        $g->id,
                        $g->staff_id,
                        $g->admin_id,
                        $g->record_type,
                        $g->grant_type,
                        $g->access_type,
                        $g->status,
                        $g->approved_by_staff_id,
                        $g->quick_reason_code,
                        $g->office_id,
                        $g->team_id,
                        optional($g->requested_at)?->toIso8601String(),
                        optional($g->approved_at)?->toIso8601String(),
                        optional($g->starts_at)?->toIso8601String(),
                        optional($g->ends_at)?->toIso8601String(),
                        optional($g->revoked_at)?->toIso8601String(),
                        $g->revoke_reason,
                        $g->requester_note,
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return Builder<ClientAccessGrant>
     */
    protected function validatedDashboardGrantQuery(Request $request, bool $forPaginatedList): Builder
    {
        $this->validateDashboardFilterInputs($request);
        $this->assertDashboardNarrowingFilters($request);

        if ($forPaginatedList) {
            $request->validate([
                'page' => ['sometimes', 'integer', 'min:1'],
                'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            ]);
        }

        return $this->buildDashboardFilteredQuery($request);
    }

    protected function validateDashboardFilterInputs(Request $request): void
    {
        $request->validate([
            'staff_id' => ['nullable', 'integer', 'min:1'],
            'admin_id' => ['nullable', 'integer', 'min:1'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'office_id' => ['nullable', 'integer', 'min:1'],
            'team_id' => ['nullable', 'integer', 'min:1'],
            'grant_type' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', 'string', 'max:32'],
        ]);
    }

    protected function assertDashboardNarrowingFilters(Request $request): void
    {
        $hasDateRange = $request->filled('date_from') && $request->filled('date_to');
        $hasNarrowingId = $request->filled('staff_id')
            || $request->filled('office_id')
            || $request->filled('team_id')
            || $request->filled('admin_id');

        if (! $hasDateRange && ! $hasNarrowingId) {
            throw ValidationException::withMessages([
                'filters' => ['Set a date range (from and to) or choose staff, office, team, or record ID.'],
            ]);
        }

        if ($hasDateRange && (string) $request->date_from > (string) $request->date_to) {
            throw ValidationException::withMessages([
                'date_to' => ['The end date must be on or after the start date.'],
            ]);
        }
    }

    /**
     * @return Builder<ClientAccessGrant>
     */
    protected function buildDashboardFilteredQuery(Request $request): Builder
    {
        $q = ClientAccessGrant::query();

        if ($request->filled('staff_id')) {
            $q->where('staff_id', (int) $request->staff_id);
        }
        if ($request->filled('admin_id')) {
            $q->where('admin_id', (int) $request->admin_id);
        }
        if ($request->filled('date_from')) {
            $q->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $q->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('office_id')) {
            $q->where('office_id', (int) $request->office_id);
        }
        if ($request->filled('team_id')) {
            $q->where('team_id', (int) $request->team_id);
        }
        if ($request->filled('grant_type')) {
            $q->where('grant_type', $request->grant_type);
        }
        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }

        return $q;
    }

    protected function requireStaff(): \App\Models\Staff
    {
        $user = Auth::guard('admin')->user();
        if (! $user instanceof \App\Models\Staff) {
            abort(401);
        }

        return $user;
    }
}
