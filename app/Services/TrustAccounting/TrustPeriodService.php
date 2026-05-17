<?php

namespace App\Services\TrustAccounting;

use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TrustPeriodService
{
    /**
     * @throws RuntimeException if trans_date falls in a locked accounting period
     */
    public static function assertTransDateUnlocked(string $ddMmYyyy): void
    {
        if (! Schema::hasTable('trust_accounting_periods')) {
            return;
        }

        $d = Carbon::createFromFormat('d/m/Y', $ddMmYyyy)->toDateString();

        $locked = DB::table('trust_accounting_periods')
            ->where('status', 'locked')
            ->whereDate('period_start', '<=', $d)
            ->whereDate('period_end', '>=', $d)
            ->exists();

        if ($locked) {
            throw new RuntimeException(
                'This transaction date falls in a locked trust accounting period. Trust entries cannot be added or voided.'
            );
        }
    }

    /**
     * True if the given row's trans_date (d/m/Y) is locked.
     */
    public static function isLockedForRow(?string $transDateDmY): bool
    {
        if ($transDateDmY === null || $transDateDmY === '' || ! Schema::hasTable('trust_accounting_periods')) {
            return false;
        }

        try {
            self::assertTransDateUnlocked($transDateDmY);

            return false;
        } catch (RuntimeException) {
            return true;
        }
    }
}
