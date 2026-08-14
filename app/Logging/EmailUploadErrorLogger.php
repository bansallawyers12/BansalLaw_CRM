<?php

namespace App\Logging;

use Throwable;

class EmailUploadErrorLogger
{
    public static function log(string $stage, array $context = [], ?Throwable $throwable = null): void
    {
        $payload = array_merge([
            'stage' => $stage,
            'logged_at' => now()->toDateTimeString(),
        ], $context);

        if ($throwable !== null) {
            $payload['exception'] = get_class($throwable);
            $payload['error'] = $throwable->getMessage();
            $payload['trace'] = $throwable->getTraceAsString();

            $previous = $throwable->getPrevious();
            if ($previous instanceof Throwable) {
                $payload['previous_error'] = $previous->getMessage();
            }
        }

        self::writeToDedicatedLogFile($payload);
    }

    protected static function writeToDedicatedLogFile(array $payload): void
    {
        $logDir = storage_path('logs');

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . DIRECTORY_SEPARATOR . 'email-upload-errors-' . date('Y-m-d') . '.log';
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $line = '[' . date('Y-m-d H:i:s') . '] ERROR: Email upload failed ' . ($encoded ?: '{}') . PHP_EOL;

        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
        EmailOpsLogPruner::prune();
    }
}
