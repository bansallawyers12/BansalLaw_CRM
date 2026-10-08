<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Invoice work dates may be one day or a range stored as a display string.
 * Ledger filters and PDF header/due date still need a single dd/mm/yyyy.
 */
class InvoiceWorkDate
{
    /**
     * @var array<string, int>
     */
    private const MONTHS = [
        'jan' => 1,
        'january' => 1,
        'feb' => 2,
        'february' => 2,
        'mar' => 3,
        'march' => 3,
        'apr' => 4,
        'april' => 4,
        'may' => 5,
        'jun' => 6,
        'june' => 6,
        'jul' => 7,
        'july' => 7,
        'aug' => 8,
        'august' => 8,
        'sep' => 9,
        'sept' => 9,
        'september' => 9,
        'oct' => 10,
        'october' => 10,
        'nov' => 11,
        'november' => 11,
        'dec' => 12,
        'december' => 12,
    ];

    public static function startDate(?string $transDate): string
    {
        $transDate = trim((string) $transDate);
        if ($transDate === '') {
            return '';
        }

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $transDate, $match)) {
            return sprintf('%02d/%02d/%s', (int) $match[3], (int) $match[2], $match[1]);
        }

        if (preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{4})/', $transDate, $match)) {
            return sprintf('%02d/%02d/%s', (int) $match[1], (int) $match[2], $match[3]);
        }

        if (preg_match('/(\d{1,2})-(\d{1,2})-(\d{4})/', $transDate, $match)) {
            return sprintf('%02d/%02d/%s', (int) $match[1], (int) $match[2], $match[3]);
        }

        if (preg_match_all('/(\d{1,2})\s+([A-Za-z]+)(?:\s+(\d{4}))?/', $transDate, $matches, PREG_SET_ORDER)) {
            $year = null;
            foreach (array_reverse($matches) as $part) {
                if (! empty($part[3])) {
                    $year = (int) $part[3];
                    break;
                }
            }

            foreach ($matches as $part) {
                $month = self::MONTHS[strtolower($part[2])] ?? null;
                if ($month === null) {
                    continue;
                }
                $partYear = ! empty($part[3]) ? (int) $part[3] : $year;
                if ($partYear) {
                    return sprintf('%02d/%02d/%d', (int) $part[1], $month, $partYear);
                }
            }
        }

        return $transDate;
    }

    /**
     * Normalize a line work date for storage (dd/mm/yyyy or a formatted range string).
     */
    public static function formatLineDateForStorage(?string $transDate): string
    {
        $transDate = trim((string) $transDate);
        if ($transDate === '') {
            return '';
        }

        if (preg_match_all('/(\d{1,2}\/\d{1,2}\/\d{4})/', $transDate, $slashMatches) && count($slashMatches[1]) >= 2) {
            $start = self::normalizeSingleDatePart($slashMatches[1][0]);
            $end = self::normalizeSingleDatePart($slashMatches[1][1]);
            if ($start !== '' && $end !== '') {
                return $start.' – '.$end;
            }
        }

        $rangeParts = preg_split('/\s+[–—]\s+|\s+-\s+|\s+to\s+/iu', $transDate);
        if (is_array($rangeParts) && count($rangeParts) >= 2) {
            $start = self::normalizeSingleDatePart(trim((string) $rangeParts[0]));
            $end = self::normalizeSingleDatePart(trim((string) $rangeParts[1]));
            if ($start !== '' && $end !== '') {
                return $start.' – '.$end;
            }
        }

        return self::normalizeSingleDatePart($transDate);
    }

    private static function normalizeSingleDatePart(string $part): string
    {
        $part = trim($part);
        if ($part === '') {
            return '';
        }

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $part, $match)) {
            return sprintf('%02d/%02d/%s', (int) $match[3], (int) $match[2], $match[1]);
        }

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $part, $match)) {
            return sprintf('%02d/%02d/%s', (int) $match[1], (int) $match[2], $match[3]);
        }

        if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $part, $match)) {
            return sprintf('%02d/%02d/%s', (int) $match[1], (int) $match[2], $match[3]);
        }

        $normalized = self::startDate($part);
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $normalized, $match)) {
            return sprintf('%02d/%02d/%s', (int) $match[1], (int) $match[2], $match[3]);
        }

        return $part;
    }

    /**
     * YYYYMMDD integer for chronological sorting (uses range start date when applicable).
     */
    public static function chronologicalSortKey(?string $transDate): int
    {
        $start = self::startDate($transDate);
        if (! preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $start, $match)) {
            return PHP_INT_MAX;
        }

        return (int) $match[3] * 10000 + (int) $match[2] * 100 + (int) $match[1];
    }

    /**
     * Sort invoice lines by work date (asc), then id for stable ordering.
     *
     * @param  iterable<int, object>  $lines
     * @return Collection<int, object>
     */
    public static function sortInvoiceLines(iterable $lines): Collection
    {
        return collect($lines)->sort(function ($a, $b) {
            $dateCmp = self::chronologicalSortKey($a->trans_date ?? null)
                <=> self::chronologicalSortKey($b->trans_date ?? null);
            if ($dateCmp !== 0) {
                return $dateCmp;
            }

            return (int) ($a->id ?? 0) <=> (int) ($b->id ?? 0);
        })->values();
    }

    public static function headerDate(?string $transDate): string
    {
        $start = self::startDate($transDate);

        return $start !== '' ? $start : 'N/A';
    }

    public static function dueDate(?string $transDate, int $days = 15): string
    {
        $start = self::startDate($transDate);
        if (! preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $start, $match)) {
            return 'N/A';
        }

        try {
            return Carbon::createFromDate((int) $match[3], (int) $match[2], (int) $match[1])
                ->addDays($days)
                ->format('d/m/Y');
        } catch (Throwable) {
            return 'N/A';
        }
    }
}
