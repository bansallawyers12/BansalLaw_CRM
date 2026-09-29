<?php

namespace App\Logging;

use Carbon\Carbon;
use Throwable;

class OutlookAddinLogger
{
    /**
     * Default retention window in days (current date + last 10 days kept, older deleted).
     */
    public const RETENTION_DAYS = 10;

    /**
     * Log a successful Outlook Add-in operation.
     */
    public static function success(string $action, array $context = []): void
    {
        self::write('SUCCESS', $action, $context);
    }

    /**
     * Log an error or failure in the Outlook Add-in.
     */
    public static function error(string $action, array $context = [], ?Throwable $throwable = null): void
    {
        if ($throwable !== null) {
            $context['exception'] = get_class($throwable);
            $context['error'] = $throwable->getMessage();
            $context['file'] = $throwable->getFile();
            $context['line'] = $throwable->getLine();
            $context['trace'] = $throwable->getTraceAsString();

            $prev = $throwable->getPrevious();
            if ($prev instanceof Throwable) {
                $context['previous_error'] = $prev->getMessage();
            }
        }

        self::write('ERROR', $action, $context);
    }

    /**
     * Log an informational / debugging event.
     */
    public static function info(string $action, array $context = []): void
    {
        self::write('INFO', $action, $context);
    }

    /**
     * Write formatted log line to date-wise log files and trigger automatic pruning.
     */
    public static function write(string $level, string $action, array $context = [], ?string $customLogsDir = null): void
    {
        $logsDir = rtrim($customLogsDir ?? storage_path('logs/outlook-addin'), DIRECTORY_SEPARATOR);

        if (!is_dir($logsDir)) {
            @mkdir($logsDir, 0755, true);
        }

        $dateStr = date('Y-m-d');
        $timeStr = date('Y-m-d H:i:s');
        $levelUpper = strtoupper(trim($level));

        $payload = array_merge([
            'timestamp' => $timeStr,
            'level' => $levelUpper,
            'action' => $action,
        ], $context);

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $line = "[{$timeStr}] [{$levelUpper}] [{$action}] " . ($jsonPayload ?: '{}') . PHP_EOL;

        // 1. Primary unified daily log file: outlook-addin-YYYY-MM-DD.log
        $mainFile = $logsDir . DIRECTORY_SEPARATOR . "outlook-addin-{$dateStr}.log";
        @file_put_contents($mainFile, $line, FILE_APPEND | LOCK_EX);

        // 2. Dedicated error log if level is ERROR: outlook-addin-errors-YYYY-MM-DD.log
        if ($levelUpper === 'ERROR') {
            $errorFile = $logsDir . DIRECTORY_SEPARATOR . "outlook-addin-errors-{$dateStr}.log";
            @file_put_contents($errorFile, $line, FILE_APPEND | LOCK_EX);
        }

        // 3. Dedicated success log if level is SUCCESS: outlook-addin-success-{$dateStr}.log
        if ($levelUpper === 'SUCCESS') {
            $successFile = $logsDir . DIRECTORY_SEPARATOR . "outlook-addin-success-{$dateStr}.log";
            @file_put_contents($successFile, $line, FILE_APPEND | LOCK_EX);
        }

        // Automatic pruning of files older than retention days (current date to last 10 days kept)
        self::prune(self::RETENTION_DAYS, $logsDir);
    }

    /**
     * Delete log files in the outlook-addin log directory older than $days (defaults to 10).
     * Only the current date and the last 10 days of files are kept; older files are deleted automatically.
     *
     * @return array{deleted: int, kept: int, deleted_files: list<string>}
     */
    public static function prune(int $days = self::RETENTION_DAYS, ?string $logsDir = null): array
    {
        $days = max(1, $days);
        $logsDir = rtrim($logsDir ?? storage_path('logs/outlook-addin'), DIRECTORY_SEPARATOR);

        if (!is_dir($logsDir)) {
            return ['deleted' => 0, 'kept' => 0, 'deleted_files' => []];
        }

        // Cutoff: Anything strictly before 10 days ago (start of that day) is deleted
        $cutoff = Carbon::now()->subDays($days)->startOfDay();

        $deleted = [];
        $kept = 0;

        $files = glob($logsDir . DIRECTORY_SEPARATOR . '*.log') ?: [];

        foreach ($files as $path) {
            if (!is_file($path)) {
                continue;
            }

            if (self::shouldDelete($path, $cutoff)) {
                @unlink($path);
                if (!is_file($path)) {
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
     * Determine whether a log file is older than the cutoff threshold.
     */
    protected static function shouldDelete(string $path, Carbon $cutoff): bool
    {
        $basename = basename($path);

        // Match YYYY-MM-DD in filename (e.g. outlook-addin-2026-09-13.log or outlook-addin-errors-2026-09-13.log)
        if (preg_match('/(\d{4}-\d{2}-\d{2})/', $basename, $matches) === 1) {
            try {
                $fileDate = Carbon::createFromFormat('Y-m-d', $matches[1])->startOfDay();
                return $fileDate->lt($cutoff);
            } catch (Throwable) {
                // If date parsing fails, fall back to mtime check below
            }
        }

        $mtime = @filemtime($path);
        return $mtime !== false && $mtime < $cutoff->getTimestamp();
    }
}
