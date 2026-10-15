<?php

namespace App\Logging;

use Carbon\Carbon;

/**
 * Formats a line for scheduler appendOutputTo files so System Breakdown can parse timestamps.
 */
final class CronAppendLogFormatter
{
    public static function format(string $level, string $message): string
    {
        $timestamp = Carbon::now((string) config('app.timezone', 'UTC'))->format('Y-m-d H:i:s');
        $level = strtoupper($level);

        return "[{$timestamp}] cron.{$level}: {$message}";
    }
}
