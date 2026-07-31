<?php

namespace App\Services\TrustAccounting;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only queries for practice-wide trust reports (Phase 3).
 * Dates in the UI are Y-m-d; trans_date in DB is dd/mm/yyyy (PostgreSQL TO_DATE).
 */
class TrustReportQueryService
{
    /**
     * Trust ledger rows that count toward balances and journals (excludes voids).
     */
    public static function baseTrustLedgerQuery(): Builder
    {
        $q = DB::table('account_client_receipts')->where('receipt_type', 1);

        $q->where(function ($w) {
            $w->whereNull('void_fee_transfer')->orWhere('void_fee_transfer', '!=', 1);
        });

        if (Schema::hasColumn('account_client_receipts', 'trust_voided_at')) {
            $q->whereNull('trust_voided_at');
        }

        if (Schema::hasColumn('account_client_receipts', 'trust_reversal_of_entry_id')) {
            $q->whereNull('trust_reversal_of_entry_id');
        }

        return $q;
    }

    public static function transDateToDbString(string $ymd): string
    {
        return Carbon::parse($ymd)->format('d/m/Y');
    }

    /**
     * Filter by transaction `trans_date` (dd/mm/yyyy in DB) within an inclusive calendar range.
     *
     * @param  'trans_date'|'account_client_receipts.trans_date'  $transDateColumn
     */
    public static function applyTransDateRange(Builder $q, string $fromYmd, string $toYmd, string $transDateColumn = 'trans_date'): void
    {
        $fromDmY = self::transDateToDbString($fromYmd);
        $toDmY = self::transDateToDbString($toYmd);

        $colExpr = match ($transDateColumn) {
            'account_client_receipts.trans_date' => 'account_client_receipts.trans_date',
            default => 'trans_date',
        };

        $q->whereRaw(
            "TO_DATE({$colExpr}, 'DD/MM/YYYY') BETWEEN TO_DATE(?, 'DD/MM/YYYY') AND TO_DATE(?, 'DD/MM/YYYY')",
            [$fromDmY, $toDmY]
        );
    }

    /**
     * All trust ledger movements on or before as-at date (inclusive).
     *
     * @param  'trans_date'|'account_client_receipts.trans_date'  $transDateColumn
     */
    public static function applyTransDateUpTo(Builder $q, string $asAtYmd, string $transDateColumn = 'trans_date'): void
    {
        $asDmY = self::transDateToDbString($asAtYmd);

        $colExpr = match ($transDateColumn) {
            'account_client_receipts.trans_date' => 'account_client_receipts.trans_date',
            default => 'trans_date',
        };

        $q->whereRaw(
            "TO_DATE({$colExpr}, 'DD/MM/YYYY') <= TO_DATE(?, 'DD/MM/YYYY')",
            [$asDmY]
        );
    }
}
