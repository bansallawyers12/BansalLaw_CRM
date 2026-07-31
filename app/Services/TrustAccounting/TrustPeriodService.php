<?php

namespace App\Services\TrustAccounting;

use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TrustPeriodService
{
    /** Cached result of Schema::hasTable to avoid repeated information_schema queries. */
    private static ?bool $tableExists = null;

    private static function periodsTableExists(): bool
    {
        if (self::$tableExists === null) {
            self::$tableExists = Schema::hasTable('trust_accounting_periods');
        }

        return self::$tableExists;
    }

    /**
     * @throws RuntimeException if trans_date falls in a locked accounting period
     */
    public static function assertTransDateUnlocked(string $ddMmYyyy): void
    {
        if (! self::periodsTableExists()) {
            return;
        }

        try {
            if (str_contains($ddMmYyyy, '-')) {
                $d = Carbon::parse($ddMmYyyy)->toDateString();
            } else {
                $d = Carbon::createFromFormat('d/m/Y', $ddMmYyyy)->toDateString();
            }
        } catch (\Throwable $e) {
            try {
                $d = Carbon::parse($ddMmYyyy)->toDateString();
            } catch (\Throwable $e2) {
                throw new \RuntimeException('Invalid transaction date format: ' . $ddMmYyyy);
            }
        }

        $locked = DB::table('trust_accounting_periods')
            ->where('status', 'locked')
            ->whereDate('period_start', '<=', $d)
            ->whereDate('period_end', '>=', $d)
            ->exists();

        if ($locked) {
            throw new \RuntimeException(
                'This transaction date falls in a locked trust accounting period. Trust entries cannot be added or voided.'
            );
        }
    }

    /**
     * True if the given row's trans_date (d/m/Y) is locked.
     */
    public static function isLockedForRow(?string $transDateDmY): bool
    {
        if ($transDateDmY === null || $transDateDmY === '') {
            return false;
        }

        if (! self::periodsTableExists()) {
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
