<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Weekday + business-hours rules for staff calendar events, reminders, and follow-ups.
 */
final class CalendarScheduleConstraints
{
    public const WEEKEND_MESSAGE = 'Weekends (Saturday and Sunday) are not available. Please select a weekday (Monday–Friday).';

    public const BUSINESS_HOURS_MESSAGE = 'Events can only be booked between 9:00 AM and 6:00 PM.';

    public static function isWeekend(CarbonInterface|string $date): bool
    {
        $carbon = $date instanceof CarbonInterface
            ? $date->copy()->timezone((string) config('app.timezone'))
            : \Carbon\Carbon::parse((string) $date, (string) config('app.timezone'));

        return $carbon->isWeekend();
    }

    public static function weekendMessageIfAny(CarbonInterface|string $date): ?string
    {
        return self::isWeekend($date) ? self::WEEKEND_MESSAGE : null;
    }

    /**
     * Timed staff/personal events must be Mon–Fri and within 9:00–18:00.
     * All-day events must still be Mon–Fri.
     */
    public static function staffEventMessage(
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        bool $isAllDay
    ): ?string {
        if ($message = self::weekendMessageIfAny($startsAt)) {
            return $message;
        }

        if ($isAllDay) {
            return null;
        }

        $tz = (string) config('app.timezone');
        $start = $startsAt->copy()->timezone($tz);
        $end = $endsAt->copy()->timezone($tz);

        $startMinutes = ((int) $start->format('H') * 60) + (int) $start->format('i');
        $endMinutes = ((int) $end->format('H') * 60) + (int) $end->format('i');

        if ($startMinutes < (9 * 60) || $endMinutes > (18 * 60) || $endMinutes <= $startMinutes) {
            return self::BUSINESS_HOURS_MESSAGE;
        }

        return null;
    }
}
