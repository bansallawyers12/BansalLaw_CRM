<?php

namespace App\Support;

/**
 * Tax invoice charge-type labels stored in payment_type.
 * Includes legacy aliases so older invoices still group on PDFs.
 */
class InvoiceChargeTypes
{
    /**
     * Options shown in create/edit invoice dropdowns (value === label).
     */
    public static function options(): array
    {
        return [
            'Professional Fees',
            'Barrister Fees',
            'Court Fees',
            'VOI Charges',
            'Disbursements',
            'Government Fees',
            'Surcharge',
            'Other Costs',
            'Discount',
        ];
    }

    /**
     * PDF / Hubdoc section groups: key => [title, payment_type values including legacy].
     */
    public static function groups(): array
    {
        return [
            'professional_fees' => [
                'title' => 'Professional Fees',
                'types' => ['Professional Fees', 'Professional Fee'],
            ],
            'barrister_fees' => [
                'title' => 'Barrister Fees',
                'types' => ['Barrister Fees'],
            ],
            'court_fees' => [
                'title' => 'Court Fees',
                'types' => ['Court Fees'],
            ],
            'voi_charges' => [
                'title' => 'VOI Charges',
                'types' => ['VOI Charges'],
            ],
            'disbursements' => [
                'title' => 'Disbursements',
                'types' => ['Disbursements', 'Disbursement'],
            ],
            'government_fees' => [
                'title' => 'Government Fees',
                'types' => ['Government Fees', 'Department Charges'],
            ],
            'surcharge' => [
                'title' => 'Surcharge',
                'types' => ['Surcharge'],
            ],
            'other_costs' => [
                'title' => 'Other Costs',
                'types' => ['Other Costs', 'Other Cost'],
            ],
            'discount' => [
                'title' => 'Discount',
                'types' => ['Discount'],
            ],
        ];
    }

    /**
     * Map a stored legacy payment_type to the current dropdown value.
     */
    public static function normalize(?string $paymentType): string
    {
        $map = [
            'Professional Fee' => 'Professional Fees',
            'Department Charges' => 'Government Fees',
            'Other Cost' => 'Other Costs',
            'Disbursement' => 'Disbursements',
        ];

        $paymentType = trim((string) $paymentType);

        return $map[$paymentType] ?? $paymentType;
    }

    /**
     * Load invoice lines grouped for PDF / Hubdoc rendering.
     *
     * @return array<string, array{title: string, types: array<int, string>, lines: \Illuminate\Support\Collection, count: int}>
     */
    public static function loadGroupedLines(int $receiptId, ?int $clientId = null): array
    {
        $grouped = [];

        foreach (self::groups() as $key => $group) {
            $query = \App\Models\AccountAllInvoiceReceipt::query()
                ->where('receipt_type', 3)
                ->where('receipt_id', $receiptId)
                ->whereIn('payment_type', $group['types']);

            if ($clientId !== null) {
                $query->where('client_id', $clientId);
            }

            $lines = $query->get();

            $grouped[$key] = [
                'title' => $group['title'],
                'types' => $group['types'],
                'lines' => $lines,
                'count' => $lines->count(),
            ];
        }

        return $grouped;
    }
}
