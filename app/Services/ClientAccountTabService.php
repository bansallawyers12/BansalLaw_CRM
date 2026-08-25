<?php

namespace App\Services;

use App\Models\AccountClientReceipt;
use App\Models\ClientLegalForm;
use App\Models\Document;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Loads client detail Account tab data (trust ledger, invoices, office receipts).
 * Keeps balance math and matter-scoped queries out of the Blade view.
 */
class ClientAccountTabService
{
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
     *     documentsById: Collection<int, Document>
     * }
     */
    public function build(int $clientId, ?int $clientMatterId): array
    {
        $trustRows = $this->trustLedgerRows($clientId, $clientMatterId);
        $invoiceRows = $this->latestInvoiceRows($clientId, $clientMatterId);
        $officeRows = $this->officeReceiptRows($clientId, $clientMatterId);

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
        $invoicedTotal = $this->invoicedTotal($invoiceRows);
        $disclosedTotal = (float) ($costsDisclosure['estimatedTotal'] ?? 0);
        $exceedsDisclosure = $disclosedTotal > 0 && $invoicedTotal > $disclosedTotal;

        return [
            'clientMatterId' => $clientMatterId,
            'trustBalance' => $this->trustBalanceFromRows($trustRows),
            'outstandingBalance' => $this->outstandingBalanceFromRows($invoiceRows),
            'invoicedTotal' => $invoicedTotal,
            'costsDisclosure' => $costsDisclosure,
            'exceedsDisclosure' => $exceedsDisclosure,
            'trustRows' => $trustRows,
            'invoiceRows' => $invoiceRows,
            'officeRows' => $officeRows,
            'documentsById' => $documentsById,
        ];
    }

    /**
     * Same rules as ClientAccountsController::currentFundsHeld / trustLedgerRowExcludedFromBalance.
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

    public function invoicedTotal(Collection $invoiceRows): float
    {
        $total = $invoiceRows
            ->filter(fn (object $row) => (int) ($row->void_invoice ?? 0) !== 1)
            ->sum(fn (object $row) => abs((float) ($row->withdraw_amount ?? 0)));

        return round($total, 2);
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
     * @return Collection<int, object>
     */
    protected function trustLedgerRows(int $clientId, ?int $clientMatterId): Collection
    {
        $rows = $this->trustLedgerQuery($clientId, $clientMatterId)
            ->orderBy('id', 'asc')
            ->get();

        $running = 0.0;
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

        return $rows;
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

    protected function outstandingBalance(int $clientId, ?int $clientMatterId): float
    {
        return $this->outstandingBalanceFromRows(
            $this->latestInvoiceRows($clientId, $clientMatterId)
        );
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
     * @return Collection<int, object>
     */
    protected function latestInvoiceRows(int $clientId, ?int $clientMatterId): Collection
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

        return collect($rows);
    }

    /**
     * @return Collection<int, object>
     */
    protected function officeReceiptRows(int $clientId, ?int $clientMatterId): Collection
    {
        $q = DB::table('account_client_receipts')
            ->where('client_id', $clientId)
            ->where('receipt_type', 2)
            ->orderByRaw("CASE WHEN invoice_no IS NULL OR invoice_no = '' THEN 0 ELSE 1 END")
            ->orderBy('id', 'desc');

        $this->applyMatterScope($q, $clientMatterId);

        return $q->get();
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
