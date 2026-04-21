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
            'disabledtimeslotes' => [],
            'disabledatesarray' => [],
        ];
    }
}
