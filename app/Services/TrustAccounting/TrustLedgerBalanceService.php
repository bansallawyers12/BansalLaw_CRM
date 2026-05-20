<?php

namespace App\Services\TrustAccounting;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shared trust ledger balance helpers (Rule 40 overdraw checks).
 */
class TrustLedgerBalanceService
{
    public static function rowExcludedFromBalance(object $row): bool
    {
        static $hasTrustVoidedAt = null;
        if ($hasTrustVoidedAt === null) {
            $hasTrustVoidedAt = Schema::hasColumn('account_client_receipts', 'trust_voided_at');
        }

        if ($hasTrustVoidedAt && isset($row->trust_voided_at) && $row->trust_voided_at !== null) {
            return true;
        }

        return isset($row->void_fee_transfer) && (int) $row->void_fee_transfer === 1;
    }

    public static function currentFundsHeld(int $clientId, $clientMatterId): float
    {
        $q = DB::table('account_client_receipts')
            ->select('deposit_amount', 'withdraw_amount', 'void_fee_transfer', 'trust_voided_at')
            ->where('client_id', $clientId)
            ->where('receipt_type', 1);

        if ($clientMatterId !== null && $clientMatterId !== '') {
            $q->where('client_matter_id', $clientMatterId);
        } else {
            $q->whereNull('client_matter_id');
        }

        $held = 0.0;
        foreach ($q->get() as $entry) {
            if (self::rowExcludedFromBalance($entry)) {
                continue;
            }
            $held += floatval($entry->deposit_amount) - floatval($entry->withdraw_amount);
        }

        return round($held, 2);
    }

    /**
     * Log Rule 40 overdraw occurrence (transaction allowed but recorded).
     */
    public static function logOverdrawnTransaction(
        int $ledgerRowId,
        string $ledgerType,
        float $priorBalance,
        float $withdrawAmount,
        float $resultingBalance
    ): void {
        TrustLedgerAuditLogger::logForTable(
            'account_client_receipts',
            'overdrawn_transaction_posted',
            $ledgerRowId,
            'balance_amount',
            (string) $priorBalance,
            (string) $resultingBalance,
            $ledgerType . ' withdrawal $' . number_format($withdrawAmount, 2, '.', '')
        );
    }
}
