<?php

namespace App\Logging;

use Illuminate\Support\Facades\Log;
use Throwable;

class InboxSyncLogger
{
    public static function info(string $event, array $context = []): void
    {
        self::write('info', $event, $context);
    }

    public static function warning(string $event, array $context = []): void
    {
        self::write('warning', $event, $context);
    }

    public static function error(string $event, array $context = [], ?Throwable $throwable = null): void
    {
        if ($throwable !== null) {
            $context['exception'] = get_class($throwable);
            $context['error'] = $throwable->getMessage();
            $context['trace'] = $throwable->getTraceAsString();

            $previous = $throwable->getPrevious();
            if ($previous instanceof Throwable) {
                $context['previous_error'] = $previous->getMessage();
            }
        }

        self::write('error', $event, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected static function write(string $level, string $event, array $context): void
    {
        Log::channel('inbox_sync')->log($level, $event, array_merge([
            'event' => $event,
            'logged_at' => now()->toDateTimeString(),
        ], $context));
    }
}
