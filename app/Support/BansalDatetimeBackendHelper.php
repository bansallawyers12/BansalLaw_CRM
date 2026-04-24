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
     * Attach {@see self::labels()} as timeslot_labels (Bansal payload may omit or differ).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function withTimeslotLabelsFromConfig(array $data): array
    {
        $data['timeslot_labels'] = self::labels();

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultPayload(): array
    {
        return [
            'success' => true,
            'duration' => 30,
            'weeks' => [0, 6],
            'start_time' => '09:00',
            'end_time' => '17:00',
            'timeslot_labels' => self::labels(),
            'disabledtimeslotes' => [],
            'disabledatesarray' => [],
        ];
    }
}
