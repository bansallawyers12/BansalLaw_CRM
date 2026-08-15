<?php

namespace App\Support;

use Illuminate\Support\Facades\Config;

/**
 * Shared defaults when {@see config('services.bansal_api.fallback_datetime')} is true
 * or for composing responses from /appointments/get-datetime-backend.
 */
final class BansalDatetimeBackendHelper
{
    public static function fallbackEnabled(): bool
    {
        return (bool) Config::get('services.bansal_api.fallback_datetime', false);
    }

    /**
     * Canonical 12h start times shown in the appointment slot picker.
     *
     * @return list<string>
     */
    public static function labels(): array
    {
        return [
            '9:30 AM',
            '11:00 AM',
            '11:30 AM',
            '2:00 PM',
            '2:30 PM',
            '3:00 PM',
            '3:30 PM',
            '4:00 PM',
            '4:30 PM',
            '5:00 PM',
        ];
    }

    /**
     * Set timeslot_labels from the timeslot-labels API when non-empty; else {@see self::labels()}.
     *
     * @param  list<string>|null  $apiLabels
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function withTimeslotLabelsFromApiResponse(array $data, ?array $apiLabels): array
    {
        if (is_array($apiLabels) && $apiLabels !== []) {
            $out = [];
            foreach ($apiLabels as $v) {
                if (is_string($v) || is_numeric($v)) {
                    $s = trim((string) $v);
                    if ($s !== '') {
                        $out[] = $s;
                    }
                }
            }
            if ($out !== []) {
                $data['timeslot_labels'] = array_values($out);

                return $data;
            }
        }

        $data['timeslot_labels'] = self::labels();

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @param  int  $duration  Slot length in minutes (10 / 30 / 60)
     * @return array<string, mixed>
     */
    public static function defaultPayload(int $duration = 30): array
    {
        $duration = in_array($duration, [10, 30, 60], true) ? $duration : 30;
        $isFree = $duration === 10;

        return [
            'success' => true,
            'duration' => $duration,
            'weeks' => [0, 6],
            'start_time' => $isFree ? '10:45' : '09:00',
            'end_time' => $isFree ? '16:00' : '17:00',
            'timeslot_labels' => self::labels(),
            'disabledtimeslotes' => [],
            'disabledatesarray' => [],
        ];
    }
}
