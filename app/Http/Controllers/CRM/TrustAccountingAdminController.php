<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\TrustAccountingPeriod;
use App\Services\TrustAccounting\TrustLedgerAuditLogger;
use App\Services\TrustAccounting\TrustReportQueryService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Trust compliance: period locks, audit log (Phase 2); practice reports (Phase 3).
 * Super-admin effective privileges only (matches void / critical trust actions).
 */
class TrustAccountingAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    private function gateTrustAdmin(): Staff
    {
        $user = auth('admin')->user();
        if (! $user instanceof Staff || ! $user->hasEffectiveSuperAdminPrivileges()) {
            abort(403, 'You do not have permission to manage trust accounting settings.');
        }

        return $user;
    }

    public function periodsIndex(): View
    {
        $this->gateTrustAdmin();

        if (! Schema::hasTable('trust_accounting_periods')) {
            abort(503, 'Trust accounting periods table is not available.');
        }

        $periods = TrustAccountingPeriod::query()
            ->with(['lockedBy', 'unlockedBy'])
            ->orderByDesc('period_start')
            ->orderByDesc('id')
            ->paginate(25);

        return view('crm.trust-accounting.periods', compact('periods'));
    }

    public function periodsStore(Request $request): RedirectResponse
    {
        $staff = $this->gateTrustAdmin();

        if (! Schema::hasTable('trust_accounting_periods')) {
            abort(503, 'Trust accounting periods table is not available.');
        }

        $validated = $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'notes' => 'nullable|string|max:5000',
        ]);

        $start = $validated['period_start'];
        $end = $validated['period_end'];

        $overlap = DB::table('trust_accounting_periods')
            ->where('status', 'locked')
            ->whereDate('period_start', '<=', $end)
            ->whereDate('period_end', '>=', $start)
            ->exists();

        if ($overlap) {
            return redirect()->back()->withInput()->withErrors([
                'period_start' => 'This date range overlaps another locked period. Unlock or adjust the existing period first.',
            ]);
        }

        $id = DB::table('trust_accounting_periods')->insertGetId([
            'period_start' => $start,
            'period_end' => $end,
            'status' => 'locked',
            'locked_at' => now(),
            'locked_by_staff_id' => $staff->id,
            'notes' => $validated['notes'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TrustLedgerAuditLogger::logForTable(
            'trust_accounting_periods',
            'period_locked',
            (int) $id,
            'status',
            null,
            'locked',
            ($start . ' → ' . $end) . ($validated['notes'] ? ' | ' . $validated['notes'] : '')
        );

        return redirect()->route('trust-accounting.periods.index')->with('success', 'Trust accounting period locked. Trust transactions dated in this range can no longer be created, voided, or have metadata changed.');
    }

    public function periodsUnlock(Request $request, TrustAccountingPeriod $period): RedirectResponse
    {
        $staff = $this->gateTrustAdmin();

        if (! $period->isLocked()) {
            return redirect()->route('trust-accounting.periods.index')->with('error', 'This period is not locked.');
        }

        $validated = $request->validate([
            'unlock_reason' => 'required|string|min:10|max:5000',
        ]);

        $period->status = 'unlocked';
        $period->unlocked_at = now();
        $period->unlocked_by_staff_id = $staff->id;
        $period->unlock_reason = $validated['unlock_reason'];
        $period->save();

        TrustLedgerAuditLogger::logForTable(
            'trust_accounting_periods',
            'period_unlocked',
            (int) $period->id,
            'status',
            'locked',
            'unlocked',
            $validated['unlock_reason']
        );

        return redirect()->route('trust-accounting.periods.index')->with('success', 'Period unlocked. Staff can again post or correct trust entries for these dates. This action was recorded in the trust audit log.');
    }

    public function auditLogIndex(Request $request): View
    {
        $this->gateTrustAdmin();

        if (! Schema::hasTable('trust_audit_logs')) {
            abort(503, 'Trust audit log table is not available.');
        }

        $query = DB::table('trust_audit_logs as l')
            ->leftJoin('staff as s', 's.id', '=', 'l.performed_by')
            ->select([
                'l.id',
                'l.created_at',
                'l.table_name',
                'l.row_id',
                'l.event',
                'l.field_name',
                'l.old_value',
                'l.new_value',
                'l.ip_address',
                'l.context',
                'l.performed_by',
                DB::raw("NULLIF(TRIM(CONCAT(COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, ''))), '') as performer_name"),
            ]);

        if ($request->filled('table_name')) {
            $tn = (string) $request->input('table_name');
            if ($tn !== '') {
                $query->where('l.table_name', $tn);
            }
        }

        if ($request->filled('event')) {
            $ev = (string) $request->input('event');
            if ($ev !== '') {
                $query->where('l.event', 'like', '%' . $ev . '%');
            }
        }

        if ($request->filled('row_id')) {
            $rid = (int) $request->input('row_id');
            if ($rid > 0) {
                $query->where('l.row_id', $rid);
            }
        }

        if ($request->filled('from_date')) {
            $query->whereDate('l.created_at', '>=', $request->input('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->whereDate('l.created_at', '<=', $request->input('to_date'));
        }

        $logs = $query->orderByDesc('l.id')->paginate(40)->withQueryString();

        return view('crm.trust-accounting.audit-log', compact('logs'));
    }

    /**
     * Phase 3: hub for practice-wide trust reports (external examination pack).
     */
    public function reportsIndex(): View
    {
        $this->gateTrustAdmin();

        return view('crm.trust-accounting.reports-index');
    }

    /**
     * Practice trial balance: trust funds held per client + matter (non-voided ledger only).
     */
    public function trialBalanceReport(Request $request): View|StreamedResponse
    {
        $this->gateTrustAdmin();

        $validated = $request->validate([
            'as_at' => 'nullable|date',
            'export' => 'nullable|in:csv',
        ]);

        $asAt = $validated['as_at'] ?? Carbon::now()->format('Y-m-d');
        $includeZero = $request->boolean('include_zero');

        $sub = TrustReportQueryService::baseTrustLedgerQuery();
        TrustReportQueryService::applyTransDateUpTo($sub, $asAt);
        $sub->select([
            'client_id',
            'client_matter_id',
            DB::raw('SUM(COALESCE(deposit_amount, 0)::numeric) - SUM(COALESCE(withdraw_amount, 0)::numeric) as balance'),
        ])->groupBy('client_id', 'client_matter_id');

        if (! $includeZero) {
            $sub->havingRaw('ROUND(SUM(COALESCE(deposit_amount, 0::numeric)) - SUM(COALESCE(withdraw_amount, 0::numeric)), 2) != 0');
        }

        $balances = DB::query()->fromSub($sub, 't')
            ->join('admins', 'admins.id', '=', 't.client_id')
            ->leftJoin('client_matters', 'client_matters.id', '=', 't.client_matter_id')
            ->select(
                't.client_id',
                't.client_matter_id',
                't.balance',
                'admins.first_name',
                'admins.last_name',
                'admins.client_id as client_ref',
                'client_matters.client_unique_matter_no'
            )
            ->orderBy('admins.client_id')
            ->orderBy('client_matters.client_unique_matter_no')
            ->get();

        $total = round((float) $balances->sum(fn ($r) => (float) $r->balance), 2);

        if (($validated['export'] ?? '') === 'csv') {
            $filename = 'trust-trial-balance-as-at-' . $asAt . '.csv';

            return response()->streamDownload(function () use ($balances, $asAt, $total) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Trust trial balance (as at ' . $asAt . ')']);
                fputcsv($out, ['Client ref', 'Matter', 'Client name', 'Balance']);
                foreach ($balances as $row) {
                    fputcsv($out, [
                        $row->client_ref,
                        $row->client_unique_matter_no ?? '',
                        trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')),
                        number_format((float) $row->balance, 2, '.', ''),
                    ]);
                }
                fputcsv($out, ['', '', 'Total', number_format($total, 2, '.', '')]);
                fclose($out);
            }, $filename, [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        return view('crm.trust-accounting.trial-balance', compact('balances', 'total', 'asAt', 'includeZero'));
    }

    /**
     * Trust receipts journal: movements with funds in (deposits) in the date range.
     */
    public function receiptsJournalReport(Request $request): View|StreamedResponse
    {
        $this->gateTrustAdmin();

        $defaults = $this->defaultTrustReportMonthRange();

        $validated = $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'client_id' => 'nullable|integer|min:1',
            'export' => 'nullable|in:csv',
        ]);

        $from = $validated['from_date'] ?? $defaults['from'];
        $to = $validated['to_date'] ?? $defaults['to'];
        if ($request->filled('from_date') && ! $request->filled('to_date')) {
            $to = Carbon::parse($from)->endOfMonth()->format('Y-m-d');
        }
        if ($request->filled('to_date') && ! $request->filled('from_date')) {
            $from = Carbon::parse($to)->startOfMonth()->format('Y-m-d');
        }

        if (Carbon::parse($from)->gt(Carbon::parse($to))) {
            return redirect()->route('trust-accounting.reports.receipts-journal', array_merge(
                $request->except(['from_date', 'to_date']),
                ['from_date' => $to, 'to_date' => $from]
            ));
        }

        $q = TrustReportQueryService::baseTrustLedgerQuery();
        TrustReportQueryService::applyTransDateRange($q, $from, $to, 'account_client_receipts.trans_date');
        $q->whereRaw('COALESCE(account_client_receipts.deposit_amount, 0)::numeric > 0');
        if (! empty($validated['client_id'])) {
            $q->where('account_client_receipts.client_id', (int) $validated['client_id']);
        }

        $depositCols = [
            'account_client_receipts.id',
            'account_client_receipts.trans_date',
            'account_client_receipts.trans_no',
            'account_client_receipts.client_fund_ledger_type',
            'account_client_receipts.description',
            'account_client_receipts.deposit_amount',
            'account_client_receipts.payment_method',
        ];
        if (Schema::hasColumn('account_client_receipts', 'payer_name')) {
            $depositCols[] = 'account_client_receipts.payer_name';
            $depositCols[] = 'account_client_receipts.bank_deposit_reference';
            $depositCols[] = 'account_client_receipts.banking_date';
        } else {
            $depositCols[] = DB::raw('NULL::text as payer_name');
            $depositCols[] = DB::raw('NULL::text as bank_deposit_reference');
            $depositCols[] = DB::raw('NULL::text as banking_date');
        }
        $depositCols[] = 'admins.client_id as client_ref';
        $depositCols[] = 'client_matters.client_unique_matter_no';

        $q->join('admins', 'admins.id', '=', 'account_client_receipts.client_id')
            ->leftJoin('client_matters', 'client_matters.id', '=', 'account_client_receipts.client_matter_id')
            ->select($depositCols)
            ->orderByRaw("TO_DATE(account_client_receipts.trans_date, 'DD/MM/YYYY') ASC")
            ->orderBy('account_client_receipts.id', 'asc');

        if (($validated['export'] ?? '') === 'csv') {
            $rows = $q->get();
            $filename = 'trust-receipts-journal-' . $from . '-to-' . $to . '.csv';

            return response()->streamDownload(function () use ($rows) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Trans date', 'Receipt no.', 'Type', 'Client ref', 'Matter', 'Description', 'Amount', 'Method', 'Payer', 'Bank ref', 'Banking date']);
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row->trans_date,
                        $row->trans_no,
                        $row->client_fund_ledger_type,
                        $row->client_ref,
                        $row->client_unique_matter_no ?? '',
                        $row->description,
                        number_format((float) $row->deposit_amount, 2, '.', ''),
                        $row->payment_method,
                        $row->payer_name ?? '',
                        $row->bank_deposit_reference ?? '',
                        $row->banking_date ?? '',
                    ]);
                }
                fclose($out);
            }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $entries = $q->paginate(100)->withQueryString();
        $sumPage = round((float) collect($entries->items())->sum(fn ($r) => (float) $r->deposit_amount), 2);

        return view('crm.trust-accounting.receipts-journal', compact('entries', 'from', 'to', 'sumPage'));
    }

    /**
     * Trust payments journal: movements with funds out (withdrawals) in the date range.
     */
    public function paymentsJournalReport(Request $request): View|StreamedResponse
    {
        $this->gateTrustAdmin();

        $defaults = $this->defaultTrustReportMonthRange();

        $validated = $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date',
            'client_id' => 'nullable|integer|min:1',
            'export' => 'nullable|in:csv',
        ]);

        $from = $validated['from_date'] ?? $defaults['from'];
        $to = $validated['to_date'] ?? $defaults['to'];
        if ($request->filled('from_date') && ! $request->filled('to_date')) {
            $to = Carbon::parse($from)->endOfMonth()->format('Y-m-d');
        }
        if ($request->filled('to_date') && ! $request->filled('from_date')) {
            $from = Carbon::parse($to)->startOfMonth()->format('Y-m-d');
        }

        if (Carbon::parse($from)->gt(Carbon::parse($to))) {
            return redirect()->route('trust-accounting.reports.payments-journal', array_merge(
                $request->except(['from_date', 'to_date']),
                ['from_date' => $to, 'to_date' => $from]
            ));
        }

        $q = TrustReportQueryService::baseTrustLedgerQuery();
        TrustReportQueryService::applyTransDateRange($q, $from, $to, 'account_client_receipts.trans_date');
        $q->whereRaw('COALESCE(account_client_receipts.withdraw_amount, 0)::numeric > 0');
        if (! empty($validated['client_id'])) {
            $q->where('account_client_receipts.client_id', (int) $validated['client_id']);
        }
        $q->join('admins', 'admins.id', '=', 'account_client_receipts.client_id')
            ->leftJoin('client_matters', 'client_matters.id', '=', 'account_client_receipts.client_matter_id')
            ->select([
                'account_client_receipts.id',
                'account_client_receipts.trans_date',
                'account_client_receipts.trans_no',
                'account_client_receipts.client_fund_ledger_type',
                'account_client_receipts.description',
                'account_client_receipts.withdraw_amount',
                'account_client_receipts.payment_method',
                'account_client_receipts.invoice_no',
                'admins.client_id as client_ref',
                'client_matters.client_unique_matter_no',
            ])
            ->orderByRaw("TO_DATE(account_client_receipts.trans_date, 'DD/MM/YYYY') ASC")
            ->orderBy('account_client_receipts.id', 'asc');

        if (($validated['export'] ?? '') === 'csv') {
            $rows = $q->get();
            $filename = 'trust-payments-journal-' . $from . '-to-' . $to . '.csv';

            return response()->streamDownload(function () use ($rows) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Trans date', 'Receipt no.', 'Type', 'Client ref', 'Matter', 'Description', 'Amount', 'Method', 'Invoice ref']);
                foreach ($rows as $row) {
                    fputcsv($out, [
                        $row->trans_date,
                        $row->trans_no,
                        $row->client_fund_ledger_type,
                        $row->client_ref,
                        $row->client_unique_matter_no ?? '',
                        $row->description,
                        number_format((float) $row->withdraw_amount, 2, '.', ''),
                        $row->payment_method,
                        $row->invoice_no ?? '',
                    ]);
                }
                fclose($out);
            }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $entries = $q->paginate(100)->withQueryString();
        $sumPage = round((float) collect($entries->items())->sum(fn ($r) => (float) $r->withdraw_amount), 2);

        return view('crm.trust-accounting.payments-journal', compact('entries', 'from', 'to', 'sumPage'));
    }

    /**
     * @return array{from: string, to: string}
     */
    private function defaultTrustReportMonthRange(): array
    {
        $start = Carbon::now()->startOfMonth()->format('Y-m-d');
        $end = Carbon::now()->endOfMonth()->format('Y-m-d');

        return ['from' => $start, 'to' => $end];
    }
}
