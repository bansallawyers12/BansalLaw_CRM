<?php

namespace App\Services\TrustAccounting;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Practice-wide sequential trust numbering.
 *
 * Two independent sequences, each with its own counter per Victorian trust year:
 *   TR-{year}-NNNNNN  – trust receipts (deposits, fee-transfers, refunds, reversals)
 *   TJ-{year}-NNNNNN  – trust journal entries
 *
 * Victorian trust year: 1 April – 31 March (e.g. year 2025 = Apr 2025 – Mar 2026).
 * Keeping receipts and journals in separate sequences prevents gaps in the TR-* run
 * that would need to be explained during an external examination.
 */
class TrustReceiptSequenceService
{
    public const TYPE_RECEIPT = 'TR';
    public const TYPE_JOURNAL = 'TJ';

    public static function trustYearStartYearForTransactionDate(string $ddMmYyyy): int
    {
        $d = Carbon::createFromFormat('d/m/Y', $ddMmYyyy)->startOfDay();

        return $d->month >= 4 ? (int) $d->year : (int) $d->year - 1;
    }

    /**
     * @param  string $ddMmYyyy   Transaction date in d/m/Y format.
     * @param  string $type       One of self::TYPE_RECEIPT ('TR') or self::TYPE_JOURNAL ('TJ').
     */
    public static function nextTransNo(string $ddMmYyyy, string $type = self::TYPE_RECEIPT): string
    {
        $trustYearStart = self::trustYearStartYearForTransactionDate($ddMmYyyy);
        $prefix = ($type === self::TYPE_JOURNAL) ? self::TYPE_JOURNAL : self::TYPE_RECEIPT;

        return DB::transaction(function () use ($trustYearStart, $prefix) {
            $row = DB::table('trust_practice_sequences')
                ->where('trust_year_start_year', $trustYearStart)
                ->where('sequence_type', $prefix)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                DB::table('trust_practice_sequences')->insertOrIgnore([
                    'sequence_type'        => $prefix,
                    'trust_year_start_year' => $trustYearStart,
                    'last_sequence'        => 1,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);

                $row = DB::table('trust_practice_sequences')
                    ->where('trust_year_start_year', $trustYearStart)
                    ->where('sequence_type', $prefix)
                    ->lockForUpdate()
                    ->first();

                if ($row && (int) $row->last_sequence > 1) {
                    $seq = (int) $row->last_sequence + 1;
                    DB::table('trust_practice_sequences')
                        ->where('trust_year_start_year', $trustYearStart)
                        ->where('sequence_type', $prefix)
                        ->update(['last_sequence' => $seq, 'updated_at' => now()]);
                } else {
                    $seq = 1;
                }
            } else {
                $seq = (int) $row->last_sequence + 1;
                DB::table('trust_practice_sequences')
                    ->where('trust_year_start_year', $trustYearStart)
                    ->where('sequence_type', $prefix)
                    ->update(['last_sequence' => $seq, 'updated_at' => now()]);
            }

            return $prefix . '-' . $trustYearStart . '-' . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
        });
    }
}
