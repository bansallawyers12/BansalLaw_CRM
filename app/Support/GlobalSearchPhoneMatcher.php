<?php

namespace App\Support;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Digit-aware phone matching for CRM global client/lead search.
 * Phones are often stored without country code (country_code column separate).
 */
class GlobalSearchPhoneMatcher
{
    /**
     * Build digit strings to try against stored phone / country_code+phone.
     *
     * @return list<string>
     */
    public static function searchDigitVariants(string $query): array
    {
        $digits = preg_replace('/\D+/', '', $query) ?? '';
        if (strlen($digits) < 6) {
            return [];
        }

        $variants = [$digits];

        // AU local 04XXXXXXXX → 61XXXXXXXXX
        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $variants[] = '61' . substr($digits, 1);
        }

        // AU mobile without leading 0: 4XXXXXXXX
        if (strlen($digits) === 9 && str_starts_with($digits, '4')) {
            $variants[] = '61' . $digits;
        }

        // Full E.164 AU (+61…) → national digits without country code
        if (strlen($digits) >= 11 && str_starts_with($digits, '61')) {
            $variants[] = substr($digits, 2);
        }

        // Leading 0 stripped national
        if (str_starts_with($digits, '0') && strlen($digits) > 6) {
            $variants[] = ltrim($digits, '0');
        }

        $variants = array_values(array_unique(array_filter(
            $variants,
            static fn (string $v): bool => strlen($v) >= 6
        )));

        return $variants;
    }

    public static function digitsSql(string $expression): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            return "REGEXP_REPLACE(COALESCE({$expression}, ''), '[^0-9]', '', 'g')";
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return "REGEXP_REPLACE(COALESCE({$expression}, ''), '[^0-9]', '')";
        }

        // SQLite / fallback: strip common separators only
        return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE({$expression}, ''), '+', ''), '-', ''), ' ', ''), '(', ''), ')', '')";
    }

    /**
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|QueryBuilder|\Illuminate\Database\Eloquent\Relations\Relation  $query
     * @param  list<string>  $variants
     */
    public static function whereDigitsMatch($query, string $columnExpression, array $variants): void
    {
        if ($variants === []) {
            return;
        }

        $sql = self::digitsSql($columnExpression);
        $query->where(function ($inner) use ($sql, $variants) {
            foreach ($variants as $i => $variant) {
                $method = $i === 0 ? 'whereRaw' : 'orWhereRaw';
                $inner->{$method}("{$sql} LIKE ?", ['%' . $variant . '%']);
            }
        });
    }

    /**
     * Match client_contacts.phone and country_code+phone digit forms.
     *
     * @param  EloquentBuilder<\Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     * @param  list<string>  $variants
     */
    public static function whereContactPhoneDigitsMatch($query, array $variants, string $table = 'client_contacts'): void
    {
        if ($variants === []) {
            return;
        }

        $phoneSql = self::digitsSql("{$table}.phone");
        $combinedSql = self::digitsSql(
            "CONCAT(COALESCE({$table}.country_code, ''), COALESCE({$table}.phone, ''))"
        );

        $query->where(function ($inner) use ($phoneSql, $combinedSql, $variants) {
            foreach ($variants as $i => $variant) {
                $like = '%' . $variant . '%';
                if ($i === 0) {
                    $inner->whereRaw("{$phoneSql} LIKE ?", [$like])
                        ->orWhereRaw("{$combinedSql} LIKE ?", [$like]);
                } else {
                    $inner->orWhereRaw("{$phoneSql} LIKE ?", [$like])
                        ->orWhereRaw("{$combinedSql} LIKE ?", [$like]);
                }
            }
        });
    }
}
