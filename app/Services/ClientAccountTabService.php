<?php

namespace App\Services;

use App\Models\AccountClientReceipt;
use App\Models\ClientLegalForm;
use App\Models\Document;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Loads client detail Account tab data (trust ledger, invoices, office receipts).
 * Also owns trust balance math and shared list-filter dropdowns for account pages.
 */
class ClientAccountTabService
{
    private const FILTER_CACHE_TTL_SECONDS = 300;

    private function filterCacheTtl(): int
    {
        return max(60, (int) config('crm.accounts.filter_cache_seconds', self::FILTER_CACHE_TTL_SECONDS));
    }

    /**
     * @return array{
     *     clientMatterId: int|null,
     *     trustBalance: float,
     *     outstandingBalance: float,
     *     invoicedTotal: float,
     *     costsDisclosure: array<string, mixed>|null,
     *     exceedsDisclosure: bool,
     *     trustRows: Collection<int, object>,
     *     invoiceRows: Collection<int, object>,
     *     officeRows: Collection<int, object>,
     *     documentsById: Collection<int, Document>,
     *     trustHasMore: bool,
     *     invoiceHasMore: bool,
     *     officeHasMore: bool
     * }
     */
    public function build(int $clientId, ?int $clientMatterId): array
    {
        $trustLimit = max(0, (int) config('crm.accounts.tab_trust_row_limit', 100));
        $invoiceLimit = max(0, (int) config('crm.accounts.tab_invoice_row_limit', 50));
        $officeLimit = max(0, (int) config('crm.accounts.tab_office_row_limit', 50));

        [$trustRows, $trustHasMore] = $this->trustLedgerRows($clientId, $clientMatterId, $trustLimit);
        [$invoiceRows, $invoiceHasMore] = $this->latestInvoiceRows($clientId, $clientMatterId, $invoiceLimit);
        [$officeRows, $officeHasMore] = $this->officeReceiptRows($clientId, $clientMatterId, $officeLimit);

        // Totals must use the full ledger, not the display window.
        [$allInvoiceRows] = $invoiceLimit > 0
            ? $this->latestInvoiceRows($clientId, $clientMatterId, 0)
            : [$invoiceRows, false];

        $docIds = $trustRows->pluck('uploaded_doc_id')
            ->merge($officeRows->pluck('uploaded_doc_id'))
            ->filter(fn ($id) => $id !== null && $id !== '' && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $documentsById = empty($docIds)
            ? collect()
            : Document::query()->whereIn('id', $docIds)->get()->keyBy('id');

        $costsDisclosure = $this->disclosurePayload(
            ClientLegalForm::latestCostsDisclosureForMatter($clientId, $clientMatterId)
        );
        $invoicedTotal = $this->invoicedTotal($allInvoiceRows);
        $disclosedTotal = (float) ($costsDisclosure['estimatedTotal'] ?? 0);
        $exceedsDisclosure = $disclosedTotal > 0 && $invoicedTotal > $disclosedTotal;

        return [
            'clientMatterId' => $clientMatterId,
            'trustBalance' => $this->currentFundsHeld($clientId, $clientMatterId),
            'outstandingBalance' => $this->outstandingBalanceFromRows($allInvoiceRows),
            'invoicedTotal' => $invoicedTotal,
            'costsDisclosure' => $costsDisclosure,
            'exceedsDisclosure' => $exceedsDisclosure,
            'trustRows' => $trustRows,
            'invoiceRows' => $invoiceRows,
            'officeRows' => $officeRows,
            'documentsById' => $documentsById,
            'trustHasMore' => $trustHasMore,
            'invoiceHasMore' => $invoiceHasMore,
            'officeHasMore' => $officeHasMore,
        ];
    }

    /**
     * Same rules as legacy ClientAccountsController::currentFundsHeld.
     */
    public function currentFundsHeld(int $clientId, $clientMatterId): float
    {
        $rows = $this->trustLedgerQuery($clientId, $clientMatterId)
            ->select('deposit_amount', 'withdraw_amount', 'void_fee_transfer')
            ->get();

        return $this->trustBalanceFromRows($rows);
    }

    public function isExcludedFromTrustBalance(object $row): bool
    {
        return isset($row->void_fee_transfer) && (int) $row->void_fee_transfer === 1;
    }

    /**
     * Persist running balances for one client funds ledger (single matter or null matter).
     *
     * @return array{final_balance: float, entries_processed: int}
     */
    public function recalculateClientFundBalances(int $clientId, $clientMatterId): array
    {
        $rows = $this->trustLedgerQuery($clientId, $clientMatterId)
            ->orderBy('id', 'asc')
            ->get();

        $running = 0.0;
        foreach ($rows as $ledgerRow) {
            if ($this->isExcludedFromTrustBalance($ledgerRow)) {
                DB::table('account_client_receipts')->where('id', $ledgerRow->id)->update([
                    'balance_amount' => null,
                    'updated_at' => now(),
                ]);

                continue;
            }
            $running += (float) $ledgerRow->deposit_amount - (float) $ledgerRow->withdraw_amount;
            $running = round($running, 2);
            DB::table('account_client_receipts')->where('id', $ledgerRow->id)->update([
                'balance_amount' => $running,
                'updated_at' => now(),
            ]);
        }

        return [
            'final_balance' => $running,
            'entries_processed' => $rows->count(),
        ];
    }

    public function invoicedTotal(Collection $invoiceRows): float
    {
        $total = $invoiceRows
            ->filter(fn (object $row) => (int) ($row->void_invoice ?? 0) !== 1)
            ->sum(fn (object $row) => abs((float) ($row->withdraw_amount ?? 0)));

        return round($total, 2);
    }

    /**
     * Cached client options for trust/invoice/office list filter dropdowns.
     *
     * @return Collection<int, object>
     */
    public function filterClientOptions(): Collection
    {
        return Cache::remember('account_receipt_filter_clients_v1', $this->filterCacheTtl(), function () {
            return DB::table('account_client_receipts as acr')
                ->join('admins', 'admins.id', '=', 'acr.client_id')
                ->select('acr.client_id', 'admins.first_name', 'admins.last_name', 'admins.client_id as client_unique_id')
                ->distinct()
                ->orderBy('admins.first_name', 'asc')
                ->get();
        });
    }

    /**
     * Cached matter options for trust/invoice/office list filter dropdowns.
     *
     * @return Collection<int, object>
     */
    public function filterMatterOptions(): Collection
    {
        return Cache::remember('account_receipt_filter_matters_v1', $this->filterCacheTtl(), function () {
            return DB::table('account_client_receipts as acr')
                ->join('client_matters', 'client_matters.id', '=', 'acr.client_matter_id')
                ->join('admins', 'admins.id', '=', 'acr.client_id')
                ->select('acr.client_matter_id', 'client_matters.client_unique_matter_no', 'admins.client_id as client_unique_id')
                ->distinct()
                ->orderBy('admins.client_id', 'asc')
                ->get();
        });
    }

    public function forgetFilterCaches(): void
    {
        Cache::forget('account_receipt_filter_clients_v1');
        Cache::forget('account_receipt_filter_matters_v1');
    }

    /**
     * Whitelist per-page for account list pages.
     */
    public function resolveListPerPage(int $requested, int $default = 20): int
    {
        $allowed = config('crm.accounts.list_per_page_options', [10, 20, 50, 100, 200, 500]);
        if (! is_array($allowed) || $allowed === []) {
            $allowed = [10, 20, 50, 100, 200, 500];
        }

        return in_array($requested, $allowed, true) ? $requested : $default;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function disclosurePayload(?ClientLegalForm $form): ?array
    {
        if ($form === null) {
            return null;
        }

        $fees = (float) $form->estimated_legal_fees;
        $gst = (float) $form->gst_amount;
        if ($gst <= 0 && $fees > 0) {
            $gst = round($fees * 0.10, 2);
        }

        return [
            'id' => $form->id,
            'formType' => $form->form_type,
            'formTypeLabel' => $form->form_type_label,
            'formDate' => $form->form_date ? $form->form_date->format('d/m/Y') : null,
            'estimatedLegalFees' => $fees,
            'estimatedDisbursements' => (float) $form->estimated_disbursements,
            'estimatedBarristerFees' => (float) $form->estimated_barrister_fees,
            'gstAmount' => $gst,
            'professionalFeesInclGst' => round($fees + $gst, 2),
            'estimatedTotal' => (float) $form->estimated_total,
            'retainerAmount' => (float) $form->retainer_amount,
            'scopeOfWork' => (string) ($form->scope_of_work ?? ''),
        ];
    }

    /**
     * @return array{0: Collection<int, object>, 1: bool}
     */
    protected function trustLedgerRows(int $clientId, ?int $clientMatterId, int $limit = 0): array
    {
        $total = $this->trustLedgerQuery($clientId, $clientMatterId)->count();
        $hasMore = $limit > 0 && $total > $limit;

        if ($hasMore) {
            $rows = $this->trustLedgerQuery($clientId, $clientMatterId)
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->sortBy('id')
                ->values();

            $missingStoredBalance = false;
            foreach ($rows as $row) {
                if ($this->isExcludedFromTrustBalance($row)) {
                    $row->running_balance = null;
                    $row->is_voided_for_balance = true;
                    continue;
                }

                if ($row->balance_amount === null || $row->balance_amount === '') {
                    $missingStoredBalance = true;
                    break;
                }

                $row->running_balance = round((float) $row->balance_amount, 2);
                $row->is_voided_for_balance = false;
            }

            if (! $missingStoredBalance) {
                return [$rows, true];
            }

            // Stale rows without persisted balances: recompute once, then re-read the window.
            $this->recalculateClientFundBalances($clientId, $clientMatterId);
            $rows = $this->trustLedgerQuery($clientId, $clientMatterId)
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->sortBy('id')
                ->values();
        } else {
            $rows = $this->trustLedgerQuery($clientId, $clientMatterId)
                ->orderBy('id', 'asc')
                ->get();
        }

        $running = 0.0;
        if ($hasMore) {
            // Window start balance comes from the oldest displayed row's prior balance.
            $first = $rows->first();
            if ($first && ! $this->isExcludedFromTrustBalance($first) && $first->balance_amount !== null && $first->balance_amount !== '') {
                $running = round(
                    (float) $first->balance_amount
                    - (float) ($first->deposit_amount ?? 0)
                    + (float) ($first->withdraw_amount ?? 0),
                    2
                );
            }
        }

        foreach ($rows as $row) {
            if ($this->isExcludedFromTrustBalance($row)) {
                $row->running_balance = null;
                $row->is_voided_for_balance = true;
                continue;
            }

            $running += (float) ($row->deposit_amount ?? 0) - (float) ($row->withdraw_amount ?? 0);
            $running = round($running, 2);
            $row->running_balance = $running;
            $row->is_voided_for_balance = false;
        }

        return [$rows, $hasMore];
    }

    protected function trustBalanceFromRows(Collection $rows): float
    {
        $held = 0.0;
        foreach ($rows as $entry) {
            if ($this->isExcludedFromTrustBalance($entry)) {
                continue;
            }
            $held += (float) ($entry->deposit_amount ?? 0) - (float) ($entry->withdraw_amount ?? 0);
        }

        return round($held, 2);
    }

    protected function outstandingBalanceFromRows(Collection $invoiceRows): float
    {
        $total = $invoiceRows
            ->filter(fn (object $row) => $this->invoiceContributesToOutstanding($row))
            ->sum(fn (object $row) => (float) ($row->balance_amount ?? 0));

        return round($total, 2);
    }

    protected function invoiceContributesToOutstanding(object $row): bool
    {
        if ((int) ($row->void_invoice ?? 0) === 1) {
            return false;
        }

        $status = (int) ($row->invoice_status ?? 0);

        if (in_array($status, [0, 2], true)) {
            return true;
        }

        return $status === 1 && (float) ($row->balance_amount ?? 0) != 0.0;
    }

    /**
     * Latest invoice row per receipt_id (PostgreSQL DISTINCT ON).
     *
     * @return array{0: Collection<int, object>, 1: bool}
     */
    protected function latestInvoiceRows(int $clientId, ?int $clientMatterId, int $limit = 0): array
    {
        if ($clientMatterId !== null) {
            $rows = DB::select('
                SELECT DISTINCT ON (receipt_id) *
                FROM account_client_receipts
                WHERE client_matter_id = ?
                AND client_id = ?
                AND receipt_type = 3
                ORDER BY receipt_id, id DESC
            ', [$clientMatterId, $clientId]);
        } else {
            $rows = DB::select('
                SELECT DISTINCT ON (receipt_id) *
                FROM account_client_receipts
                WHERE client_matter_id IS NULL
                AND client_id = ?
                AND receipt_type = 3
                ORDER BY receipt_id, id DESC
            ', [$clientId]);
        }

        $collection = collect($rows)->sortByDesc('id')->values();
        $hasMore = $limit > 0 && $collection->count() > $limit;
        if ($hasMore) {
            $collection = $collection->take($limit)->values();
        }

        return [$collection, $hasMore];
    }

    /**
     * @return array{0: Collection<int, object>, 1: bool}
     */
    protected function officeReceiptRows(int $clientId, ?int $clientMatterId, int $limit = 0): array
    {
        $q = DB::table('account_client_receipts')
            ->where('client_id', $clientId)
            ->where('receipt_type', 2)
            ->orderByRaw("CASE WHEN invoice_no IS NULL OR invoice_no = '' THEN 0 ELSE 1 END")
            ->orderByDesc('id');

        $this->applyMatterScope($q, $clientMatterId);

        $total = (clone $q)->count();
        $hasMore = $limit > 0 && $total > $limit;
        if ($limit > 0) {
            $q->limit($limit);
        }

        return [$q->get(), $hasMore];
    }

    protected function trustLedgerQuery(int $clientId, $clientMatterId)
    {
        $q = DB::table('account_client_receipts')
            ->where('client_id', $clientId)
            ->where('receipt_type', AccountClientReceipt::RECEIPT_TYPE_TRUST_LEDGER);

        $this->applyMatterScope($q, $clientMatterId);

        return $q;
    }

    protected function applyMatterScope($query, $clientMatterId): void
    {
        if ($clientMatterId !== null && $clientMatterId !== '') {
            $query->where('client_matter_id', $clientMatterId);
        } else {
            $query->whereNull('client_matter_id');
        }
    }
}
