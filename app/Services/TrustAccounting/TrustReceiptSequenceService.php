<?php

namespace App\Services\TrustAccounting;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Practice-wide sequential trust receipt numbers: TR-{trust_year}-NNNNNN
 * Victorian trust year: 1 April (inclusive) to 31 March (inclusive) of the following calendar year.
 */
class TrustReceiptSequenceService
{
    public static function trustYearStartYearForTransactionDate(string $ddMmYyyy): int
    {
        $d = Carbon::createFromFormat('d/m/Y', $ddMmYyyy)->startOfDay();

        return $d->month >= 4 ? (int) $d->year : (int) $d->year - 1;
    }

    public static function nextTransNo(string $ddMmYyyy): string
    {
        $trustYearStart = self::trustYearStartYearForTransactionDate($ddMmYyyy);

        return DB::transaction(function () use ($trustYearStart) {
            $row = DB::table('trust_practice_sequences')
                ->where('trust_year_start_year', $trustYearStart)
                ->lockForUpdate()
                ->first();

            if (! $row) {
                DB::table('trust_practice_sequences')->insert([
                    'trust_year_start_year' => $trustYearStart,
                    'last_sequence' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $seq = 1;
            } else {
                $seq = (int) $row->last_sequence + 1;
                DB::table('trust_practice_sequences')
                    ->where('trust_year_start_year', $trustYearStart)
                    ->update(['last_sequence' => $seq, 'updated_at' => now()]);
            }

            return 'TR-' . $trustYearStart . '-' . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
        });
    }
}
