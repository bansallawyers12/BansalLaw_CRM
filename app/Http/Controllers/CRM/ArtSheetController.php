<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\ClientArtReference;
use App\Models\ActivitiesLog;
use App\Support\StaffClientVisibility;
use App\Traits\ClientAuthorization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ArtSheetController extends Controller
{
    use ClientAuthorization;

    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    /**
     * Display the ART Sheet - List view
     */
    public function index(Request $request)
    {
        if (! $this->hasModuleAccess('20') || ! $this->canAccessCrmSheet('art')) {
            abort(403, 'Unauthorized');
        }

        $perPage = (int) $request->get('per_page', 50);
        $allowedPerPage = [10, 25, 50, 100, 200];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 50;
        }

        if (!$request->has('agent') || $request->input('agent') === '') {
            $request->merge(['agent' => 'me']);
        }

        $query = $this->buildBaseQuery($request);
        $query = $this->applyFilters($query, $request);
        $query = $this->applySorting($query, $request);

        $rows = $query->paginate($perPage)->appends($request->except('page'));

        $rows->getCollection()->transform(function ($row) {
            $payments = $this->calculatePaymentsForMatter($row->client_id, $row->matter_internal_id);
            $row->total_payment = $payments['total'];
            $row->pending_payment = $payments['pending'];
            return $row;
        });

        $activeFilterCount = $this->countActiveFilters($request);
        $agents = $this->getArtAgents();
        $statusOptions = $this->getStatusOptions();

        return view('crm.clients.sheets.art', compact('rows', 'perPage', 'activeFilterCount', 'agents', 'statusOptions'));
    }

    /**
     * Display the ART Sheet - Insights view
     */
    public function insights(Request $request)
    {
        // Restrict to admin and super admin only (roles 1, 12)
        if (!in_array(Auth::user()->role ?? 0, [1, 12])) {
            return redirect()->back()->with('error', 'Only admin and super admin can view insights.');
        }

        if (! $this->hasModuleAccess('20') || ! $this->canAccessCrmSheet('art')) {
            abort(403, 'Unauthorized');
        }

        $baseQuery = $this->buildBaseQuery($request);
        $baseQuery = $this->applyFilters($baseQuery, $request);
        $allRecords = $baseQuery->get();

        $insights = $this->calculateInsights($allRecords);
        $activeFilterCount = $this->countActiveFilters($request);

        return view('crm.clients.sheets.art-insights', compact('insights', 'activeFilterCount'));
    }

    /**
     * Build base query: latest ART matter per client, left join client_art_references
     */
    protected function buildBaseQuery(Request $request)
    {
        $driver = DB::connection()->getDriverName();
        $latestArtMatterSql = "
            SELECT DISTINCT ON (cm.client_id)
                cm.client_id,
                cm.client_unique_matter_no,
                cm.id AS matter_id,
                cm.other_reference,
                cm.department_reference,
                cm.sel_migration_agent,
                cm.sel_person_responsible,
                cm.sel_person_assisting,
                cm.office_id,
                cm.deadline
            FROM client_matters cm
            INNER JOIN matters m ON m.id = cm.sel_matter_id
            WHERE cm.matter_status = 1
              AND (
                  LOWER(COALESCE(m.nick_name, '')) = 'art'
                  OR LOWER(COALESCE(m.title, '')) LIKE '%art%'
                  OR LOWER(COALESCE(m.title, '')) LIKE '%administrative appeals%'
                  OR LOWER(COALESCE(m.title, '')) LIKE '%tribunal%'
              )
            ORDER BY cm.client_id, cm.id DESC
        ";

        if ($driver === 'mysql') {
            $latestArtMatterSql = "
                SELECT cm.client_id, cm.client_unique_matter_no, cm.id AS matter_id,
                       cm.other_reference, cm.department_reference, cm.sel_migration_agent,
                       cm.sel_person_responsible, cm.sel_person_assisting, cm.office_id,
                       cm.deadline
                FROM client_matters cm
                INNER JOIN matters m ON m.id = cm.sel_matter_id
                INNER JOIN (
                    SELECT client_id, MAX(id) AS max_id FROM client_matters cm2
                    INNER JOIN matters m2 ON m2.id = cm2.sel_matter_id
                    WHERE cm2.matter_status = 1
                      AND (LOWER(COALESCE(m2.nick_name, '')) = 'art'
                           OR LOWER(COALESCE(m2.title, '')) LIKE '%art%'
                           OR LOWER(COALESCE(m2.title, '')) LIKE '%administrative appeals%'
                           OR LOWER(COALESCE(m2.title, '')) LIKE '%tribunal%')
                    GROUP BY client_id
                ) latest ON latest.client_id = cm.client_id AND latest.max_id = cm.id
                WHERE cm.matter_status = 1
                  AND (LOWER(COALESCE(m.nick_name, '')) = 'art'
                       OR LOWER(COALESCE(m.title, '')) LIKE '%art%'
                       OR LOWER(COALESCE(m.title, '')) LIKE '%administrative appeals%'
                       OR LOWER(COALESCE(m.title, '')) LIKE '%tribunal%')
            ";
        }

        $query = DB::table(DB::raw('(' . $latestArtMatterSql . ') AS latest_art_matter'))
            ->leftJoin('client_art_references as art', function ($join) {
                $join->on('art.client_id', '=', 'latest_art_matter.client_id')
                    ->on('art.client_matter_id', '=', 'latest_art_matter.matter_id');
            })
            ->join('admins', 'latest_art_matter.client_id', '=', 'admins.id')
            ->leftJoin('staff as agents', 'latest_art_matter.sel_migration_agent', '=', 'agents.id');

        // Person Assisting role: rows where this staff is MA / PR / PA on the matter
        if ($paId = StaffClientVisibility::personAssistingStaffIdOrNull(Auth::user())) {
            $query->where(function ($q) use ($paId) {
                $q->where('latest_art_matter.sel_migration_agent', $paId)
                    ->orWhere('latest_art_matter.sel_person_responsible', $paId)
                    ->orWhere('latest_art_matter.sel_person_assisting', $paId);
            });
        }

        $query->select(
                'art.id as art_id',
                'art.is_pinned',
                'art.submission_last_date',
                'art.status_of_file',
                'art.hearing_time',
                'art.member_name',
                'art.outcome',
                'art.comments',
                'latest_art_matter.client_id',
                'admins.client_id as crm_ref',
                'admins.first_name',
                'admins.last_name',
                'latest_art_matter.client_unique_matter_no as matter_id',
                'latest_art_matter.matter_id as matter_internal_id',
                'latest_art_matter.deadline',
                'latest_art_matter.other_reference',
                'latest_art_matter.department_reference',
                'latest_art_matter.office_id',
                DB::raw("CONCAT(COALESCE(agents.first_name, ''), ' ', COALESCE(agents.last_name, '')) as agent_name")
            )
            ->where('admins.is_archived', 0)
            ->whereIn('admins.type', ['client', 'lead'])
            ->whereNull('admins.is_deleted');

        return $query;
    }

    /**
     * Calculate total and pending payments for a client matter
     */
    protected function calculatePaymentsForMatter($clientId, $matterInternalId)
    {
        if (!$clientId || !$matterInternalId) {
            return ['total' => '0.00', 'pending' => '0.00'];
        }

        // Payment received = Client Fund Ledger (Deposits only) + Office Receipts
        // Show total for client (sheet displays one row per client with their matter)
        $total = (float) DB::table('account_client_receipts')
            ->where('client_id', $clientId)
            ->where(function ($q) {
                $q->where(function ($q1) {
                    // Client fund: only Deposits (exclude Fee Transfers which have deposit_amount=0)
                    $q1->where('receipt_type', 1)
                        ->where(function ($q2) {
                            $q2->where('client_fund_ledger_type', 'Deposit')
                                ->orWhereNull('client_fund_ledger_type');
                        });
                })->orWhere(function ($q2) {
                    $q2->where('receipt_type', 2)->where('save_type', 'final'); // Office receipts (finalized only)
                });
            })
            ->where(function ($q) {
                $q->whereNull('void_fee_transfer')->orWhere('void_fee_transfer', '!=', 1);
            })
            ->where(function ($q) {
                $q->whereNull('void_invoice')->orWhere('void_invoice', '!=', 1);
            })
            ->sum(DB::raw('COALESCE(deposit_amount, 0)'));

        $pending = (float) DB::table('account_client_receipts')
            ->where('client_id', $clientId)
            ->where('receipt_type', 3)
            ->where(function ($q) {
                $q->whereNull('void_fee_transfer')->orWhere('void_fee_transfer', '!=', 1);
            })
            ->sum(DB::raw('COALESCE(balance_amount, 0)'));

        return [
            'total' => number_format($total, 2),
            'pending' => number_format($pending, 2),
        ];
    }

    protected function applyFilters($query, Request $request)
    {
        if ($request->filled('status')) {
            $query->where('art.status_of_file', $request->input('status'));
        }

        if ($request->filled('from_date')) {
            try {
                $fromDate = Carbon::createFromFormat('d/m/Y', $request->input('from_date'))->startOfDay();
                $query->whereRaw('art.submission_last_date >= ?', [$fromDate]);
            } catch (\Exception $e) {
                // ignore invalid date
            }
        }

        if ($request->filled('to_date')) {
            try {
                $toDate = Carbon::createFromFormat('d/m/Y', $request->input('to_date'))->endOfDay();
                $query->whereRaw('art.submission_last_date <= ?', [$toDate]);
            } catch (\Exception $e) {
                // ignore invalid date
            }
        }

        if ($request->filled('agent')) {
            $agentId = $request->input('agent') === 'me' ? Auth::id() : $request->input('agent');
            if ($agentId) {
                $query->where(function ($q) use ($agentId) {
                    $q->where('latest_art_matter.sel_migration_agent', $agentId)
                        ->orWhere('latest_art_matter.sel_person_responsible', $agentId)
                        ->orWhere('latest_art_matter.sel_person_assisting', $agentId);
                });
            }
        }

        if ($request->filled('search')) {
            $search = '%' . strtolower($request->input('search')) . '%';
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(admins.first_name) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(admins.last_name) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(admins.client_id) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(latest_art_matter.other_reference) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(latest_art_matter.department_reference) LIKE ?', [$search]);
            });
        }

        // Office filter
        if ($request->filled('office')) {
            $offices = is_array($request->input('office')) ? $request->input('office') : [$request->input('office')];
            $query->whereIn('latest_art_matter.office_id', $offices);
        }

        return $query;
    }

    protected function applySorting($query, Request $request)
    {
        $driver = DB::connection()->getDriverName();
        // First priority: pinned items (is_pinned DESC) - pinned items on top
        $query->orderByRaw("CASE WHEN COALESCE(art.is_pinned, false) = true THEN 1 ELSE 0 END DESC");
        // Secondary: nearest deadline first, nulls last
        $query->orderByRaw($driver === 'mysql'
            ? 'latest_art_matter.deadline IS NULL ASC, latest_art_matter.deadline ASC'
            : 'latest_art_matter.deadline ASC NULLS LAST');

        $sortField = $request->get('sort', 'submission_date');
        $sortDirection = $request->get('direction', 'desc');
        if (!in_array(strtolower($sortDirection), ['asc', 'desc'])) {
            $sortDirection = 'desc';
        }
        $dir = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';

        $sortableFields = [
            'crm_ref' => 'admins.client_id',
            'other_reference' => 'latest_art_matter.other_reference',
            'client_name' => 'admins.first_name',
            'submission_date' => 'art.submission_last_date',
            'deadline' => 'latest_art_matter.deadline',
            'status' => 'art.status_of_file',
            'agent_name' => 'agents.first_name',
        ];

        $actualSortField = $sortableFields[$sortField] ?? 'art.submission_last_date';
        $query->orderBy($actualSortField, $dir);

        return $query;
    }

    protected function countActiveFilters(Request $request)
    {
        $filters = ['status', 'from_date', 'to_date', 'agent', 'search', 'office'];
        $count = 0;
        foreach ($filters as $filter) {
            if ($request->filled($filter)) {
                $count++;
            }
        }
        return $count;
    }

    protected function getArtAgents()
    {
        $artCondition = "cm.matter_status = 1 AND (
            LOWER(COALESCE(m.nick_name, '')) = 'art'
            OR LOWER(COALESCE(m.title, '')) LIKE '%art%'
            OR LOWER(COALESCE(m.title, '')) LIKE '%administrative appeals%'
            OR LOWER(COALESCE(m.title, '')) LIKE '%tribunal%'
        )";
        $allIds = DB::table('client_matters as cm')
            ->join('matters as m', 'cm.sel_matter_id', '=', 'm.id')
            ->whereRaw($artCondition)
            ->select('cm.sel_migration_agent')
            ->whereNotNull('cm.sel_migration_agent')
            ->distinct()
            ->pluck('sel_migration_agent')
            ->merge(
                DB::table('client_matters as cm')
                    ->join('matters as m', 'cm.sel_matter_id', '=', 'm.id')
                    ->whereRaw($artCondition)
                    ->select('cm.sel_person_responsible')
                    ->whereNotNull('cm.sel_person_responsible')
                    ->distinct()
                    ->pluck('sel_person_responsible')
            )
            ->merge(
                DB::table('client_matters as cm')
                    ->join('matters as m', 'cm.sel_matter_id', '=', 'm.id')
                    ->whereRaw($artCondition)
                    ->select('cm.sel_person_assisting')
                    ->whereNotNull('cm.sel_person_assisting')
                    ->distinct()
                    ->pluck('sel_person_assisting')
            )
            ->unique()
            ->filter()
            ->values();
        $agents = \App\Models\Staff::where('status', 1)
            ->whereIn('id', $allIds)
            ->orderBy('first_name')->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name']);
        $currentUser = Auth::user();
        if ($currentUser && $agents->pluck('id')->doesntContain($currentUser->id)) {
            $agents->push($currentUser);
            $agents = $agents->sortBy(fn ($a) => trim(($a->first_name ?? '') . ' ' . ($a->last_name ?? '')))->values();
        }
        return $agents;
    }

    protected function getStatusOptions()
    {
        return [
            'submission_pending' => 'Submission Pending',
            'submission_done' => 'Submission Done',
            'hearing_invitation_sent' => 'Hearing Invitation Sent',
            'waiting_for_hearing' => 'Waiting for Hearing',
            'hearing' => 'Hearing',
            'decided' => 'Decided',
            'withdrawn' => 'Withdrawn',
        ];
    }

    protected function calculateInsights($records)
    {
        $insights = [
            'total_records' => $records->count(),
            'by_status' => [],
            'by_agent' => [],
            'recent_submissions_7d' => 0,
            'recent_submissions_30d' => 0,
            'submissions_by_month' => [],
        ];

        if ($records->isEmpty()) {
            return $insights;
        }

        $insights['by_status'] = $records->groupBy('status_of_file')->map->count()->toArray();

        $insights['by_agent'] = $records->groupBy('agent_name')->map->count()->toArray();

        $now = Carbon::now();
        $insights['recent_submissions_7d'] = $records->filter(function ($record) use ($now) {
            return $record->submission_last_date && Carbon::parse($record->submission_last_date)->greaterThanOrEqualTo($now->copy()->subDays(7));
        })->count();

        $insights['recent_submissions_30d'] = $records->filter(function ($record) use ($now) {
            return $record->submission_last_date && Carbon::parse($record->submission_last_date)->greaterThanOrEqualTo($now->copy()->subDays(30));
        })->count();

        for ($i = 5; $i >= 0; $i--) {
            $month = $now->copy()->subMonths($i);
            $monthLabel = $month->format('M Y');
            $count = $records->filter(function ($record) use ($month) {
                return $record->submission_last_date &&
                    Carbon::parse($record->submission_last_date)->format('Y-m') === $month->format('Y-m');
            })->count();
            $insights['submissions_by_month'][$monthLabel] = $count;
        }

        return $insights;
    }

    /**
     * Toggle pin status for an ART matter in the sheet.
     */
    public function togglePin(Request $request)
    {
        if (! $this->hasModuleAccess('20') || ! $this->canAccessCrmSheet('art')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $clientId = $request->input('client_id');
        $matterInternalId = $request->input('matter_internal_id');

        if (!$clientId || !$matterInternalId) {
            return response()->json(['success' => false, 'message' => 'Missing required parameters'], 400);
        }

        try {
            $reference = DB::table('client_art_references')
                ->where('client_id', $clientId)
                ->where('client_matter_id', $matterInternalId)
                ->first();

            if ($reference) {
                $newPinStatus = !($reference->is_pinned ?? false);
                DB::table('client_art_references')
                    ->where('client_id', $clientId)
                    ->where('client_matter_id', $matterInternalId)
                    ->update([
                        'is_pinned' => $newPinStatus,
                        'updated_by' => Auth::id(),
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('client_art_references')->insert([
                    'client_id' => $clientId,
                    'client_matter_id' => $matterInternalId,
                    'status_of_file' => 'submission_pending',
                    'is_pinned' => true,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $newPinStatus = true;
            }

            return response()->json([
                'success' => true,
                'is_pinned' => $newPinStatus,
                'message' => $newPinStatus ? 'Item pinned to top' : 'Item unpinned'
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error updating pin status: ' . $e->getMessage()], 500);
        }
    }
}
