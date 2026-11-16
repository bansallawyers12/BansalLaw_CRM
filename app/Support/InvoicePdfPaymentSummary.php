<?php

namespace App\Support;

/**
 * Footer rows for invoice PDF totals (pending vs paid).
 */
class InvoicePdfPaymentSummary
{
    /**
     * @param  object|null  $receiptEntry  account_client_receipts row (receipt_type 3)
     * @param  'auto'|'pending'|'paid'  $mode
     * @return array{rows: list<array{label: string, amount: float}>, show_payment_instructions: bool}
     */
    public static function footerRows(
        ?object $receiptEntry,
        float $totalInvoiceAmount,
        float $totalPendingAmount,
        string $mode = 'auto'
    ): array {
        $mode = in_array($mode, ['auto', 'pending', 'paid'], true) ? $mode : 'auto';

        $status = (int) ($receiptEntry->invoice_status ?? 0);
        $balance = round((float) ($receiptEntry->balance_amount ?? $totalPendingAmount), 2);
        $paid = round((float) ($receiptEntry->partial_paid_amount ?? 0), 2);
        $invoiceTotal = round($totalInvoiceAmount, 2);

        if ($mode === 'paid') {
            $paidAmount = $paid > 0 ? $paid : $invoiceTotal;

            return [
                'rows' => [
                    ['label' => 'Amount is Paid:', 'amount' => $paidAmount],
                ],
                'show_payment_instructions' => false,
            ];
        }

        if ($mode === 'pending') {
            $pending = $balance;
            if ($pending <= 0 && $status !== 1) {
                $pending = $totalPendingAmount > 0 ? $totalPendingAmount : $invoiceTotal;
            }

            return [
                'rows' => [
                    ['label' => 'Amount is Pending:', 'amount' => max(0.0, round($pending, 2))],
                ],
                'show_payment_instructions' => true,
            ];
        }

        // auto — follow accounting status on the invoice receipt
        if ($status === 1 || ($balance <= 0.009 && $paid > 0)) {
            $paidAmount = $paid > 0 ? $paid : $invoiceTotal;

            return [
                'rows' => [
                    ['label' => 'Amount is Paid:', 'amount' => $paidAmount],
                ],
                'show_payment_instructions' => false,
            ];
        }

        if ($status === 2 && $paid > 0) {
            return [
                'rows' => [
                    ['label' => 'Amount is Paid:', 'amount' => $paid],
                    ['label' => 'Amount is Pending:', 'amount' => max(0.0, $balance)],
                ],
                'show_payment_instructions' => $balance > 0.009,
            ];
        }

        $pending = $balance > 0 ? $balance : $totalPendingAmount;

        return [
            'rows' => [
                ['label' => 'Amount is Pending:', 'amount' => max(0.0, round((float) $pending, 2))],
            ],
            'show_payment_instructions' => true,
        ];
    }

    public static function normalizeDisplayMode(?string $raw): string
    {
        $mode = strtolower(trim((string) $raw));

        return in_array($mode, ['auto', 'pending', 'paid'], true) ? $mode : 'auto';
    }

    public static function isCustomDisplayMode(string $mode): bool
    {
        return $mode !== 'auto';
    }

    /**
     * URL query param overrides stored invoice preference; default is auto (follow payment status).
     */
    public static function resolveDisplayMode(?string $queryParam, ?object $receiptEntry): string
    {
        if ($queryParam !== null && trim($queryParam) !== '') {
            return self::normalizeDisplayMode($queryParam);
        }

        $stored = trim((string) ($receiptEntry->invoice_pdf_balance_display ?? ''));

        return $stored !== '' ? self::normalizeDisplayMode($stored) : 'auto';
    }
}