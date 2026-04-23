<?php

namespace App\Services\Booking;

use App\Models\BookingAppointment;
use Carbon\Carbon;

/**
 * Lists time labels to disable in the booking UI, based on CRM booking_appointments.
 * If timeslot_full stores a range (e.g. "9:00 AM - 10:00 AM"), only the first/start time is returned.
 * Does not filter by noe_id (nature of enquiry).
 * Shared by {@see \App\Http\Controllers\API\PublicBookingController::getBookedTimeSlotsToDisable}
 * and {@see \App\Http\Controllers\HomeController::getdisableddatetime} (merge with Bansal).
 */
class BookedTimeSlotsToDisableService
{
    /**
     * @return list<string>
     */
    public function getTimeSlotLabelsForDate(Carbon $date, ?int $inpersonAddress = null): array
    {
        $query = BookingAppointment::query()
            ->select(['id', 'appointment_datetime', 'timeslot_full'])
            ->whereNotIn('status', ['pending', 'cancelled', 'no_show'])
            ->whereDate('appointment_datetime', $date->format('Y-m-d'));

        if ($inpersonAddress !== null) {
            $locationMap = [1 => 'adelaide', 2 => 'melbourne'];
            $query->where('location', $locationMap[$inpersonAddress] ?? 'adelaide');
        }

        $rows = $query->orderBy('appointment_datetime')->get();

        $tz = config('app.timezone');
        $out = [];

        foreach ($rows as $row) {
            if (! $row->appointment_datetime) {
                continue;
            }
            if ($row->timeslot_full) {
                $out[] = $this->firstTimeLabelOnly((string) $row->timeslot_full);

                continue;
            }
            $out[] = $row->appointment_datetime->copy()->timezone($tz)->format('g:i A');
        }

        return array_values(array_unique($out));
    }

    /**
     * Use the start of a range only, e.g. "9:00 AM - 10:00 AM" -> "9:00 AM" (for slot matching).
     */
    private function firstTimeLabelOnly(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return $value;
        }

        $delimiters = [' - ', ' – ', ' — ', ' -', '- ', ' to ', ' To ', ' TO '];
        foreach ($delimiters as $sep) {
            if (str_contains($value, $sep)) {
                $parts = explode($sep, $value, 2);

                return trim($parts[0] ?? $value);
            }
        }

        if (preg_match('/^(.+?)\s*[\-–—]\s*.+$/u', $value, $m)) {
            $first = trim($m[1] ?? '');

            return $first !== '' ? $first : $value;
        }

        return $value;
    }

    /**
     * @param  list<string>  $bansal
     * @param  list<string>  $crm
     * @return list<string>
     */
    public function mergeTimeSlotLabelLists(array $bansal, array $crm): array
    {
        return array_values(array_unique(array_merge($bansal, $crm)));
    }

    public static function parseDateInput(string $dateInput): ?Carbon
    {
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dateInput)) {
            $parsed = Carbon::createFromFormat('d/m/Y', $dateInput, config('app.timezone'));

            return $parsed ? $parsed->startOfDay() : null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateInput)) {
            $parsed = Carbon::createFromFormat('Y-m-d', $dateInput, config('app.timezone'));

            return $parsed ? $parsed->startOfDay() : null;
        }

        return null;
    }
}
