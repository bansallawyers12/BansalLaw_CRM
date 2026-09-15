<?php

namespace App\Support;

use Carbon\Carbon;
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
