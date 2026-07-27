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
     * Persist a structured per-run summary (date-wise daily log file).
     *
     * @param  array<string, mixed>  $summary
     */
    public static function logRunSummary(string $source, array $summary, ?string $mailbox = null): void
    {
        $context = [
            'event' => 'inbox_sync_run_summary',
            'source' => $source,
            'mailbox' => $mailbox,
            'sync_range' => $summary['sync_range'] ?? null,
            'total_imported' => (int) ($summary['total_imported'] ?? 0),
            'total_skipped' => (int) ($summary['total_skipped'] ?? 0),
            'total_failed' => (int) ($summary['total_failed'] ?? 0),
            'mailboxes' => $summary['mailboxes'] ?? [],
            'logged_at' => now()->toDateTimeString(),
            'log_date' => now()->format('Y-m-d'),
        ];

        $level = ((int) ($summary['total_failed'] ?? 0)) > 0 ? 'warning' : 'info';
        Log::channel('inbox_sync_runs')->log($level, 'Inbox sync run completed', $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected static function write(string $level, string $event, array $context): void
    {
        Log::channel('inbox_sync')->log($level, $event, array_merge([
            'event' => $event,
            'logged_at' => now()->toDateTimeString(),
            'log_date' => now()->format('Y-m-d'),
        ], $context));
    }
}
