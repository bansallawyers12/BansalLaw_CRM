<?php

namespace App\Logging;

use Carbon\Carbon;
use Throwable;

class EmailOpsLogPruner
{
    /**
     * Delete inbox-sync, inbox-sync-run, and email-upload-errors files older than the retention window.
     *
     * @return array{deleted: int, kept: int, deleted_files: list<string>}
     */
    public static function prune(?int $days = null, ?string $logsDir = null): array
    {
        $days = max(1, $days ?? (int) config('logging.email_ops_retention_days', 7));
        $logsDir = rtrim($logsDir ?? storage_path('logs'), DIRECTORY_SEPARATOR);
        $cutoff = now()->subDays($days)->startOfDay();

        $deleted = [];
        $kept = 0;

        foreach (self::candidatePaths($logsDir) as $path) {
            if (! is_file($path)) {
                continue;
            }

            if (self::shouldDelete($path, $cutoff)) {
                @unlink($path);
                if (! is_file($path)) {
                    $deleted[] = $path;
                }

                continue;
            }

            $kept++;
        }

        return [
            'deleted' => count($deleted),
            'kept' => $kept,
            'deleted_files' => $deleted,
        ];
    }

    /**
     * @return list<string>
     */
    protected static function candidatePaths(string $logsDir): array
    {
        $globs = [
            $logsDir . DIRECTORY_SEPARATOR . 'inbox-sync' . DIRECTORY_SEPARATOR . '*.log',
            $logsDir . DIRECTORY_SEPARATOR . 'inbox-sync-runs' . DIRECTORY_SEPARATOR . '*.log',
            $logsDir . DIRECTORY_SEPARATOR . 'email-upload-errors*.log',
        ];

        $files = [];
        foreach ($globs as $pattern) {
            foreach (glob($pattern) ?: [] as $file) {
                $files[] = $file;
            }
        }

        sort($files);

        return array_values(array_unique($files));
    }

    protected static function shouldDelete(string $path, Carbon $cutoff): bool
    {
        $basename = basename($path);
        if (preg_match('/(\d{4}-\d{2}-\d{2})/', $basename, $matches) === 1) {
            try {
                $fileDate = Carbon::createFromFormat('Y-m-d', $matches[1])->startOfDay();

                return $fileDate->lt($cutoff);
            } catch (Throwable) {
            }
        }

        $mtime = @filemtime($path);

        return $mtime !== false && $mtime < $cutoff->getTimestamp();
    }
}
