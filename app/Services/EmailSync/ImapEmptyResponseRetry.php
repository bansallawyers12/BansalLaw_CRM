<?php

namespace App\Services\EmailSync;

use App\Logging\InboxSyncLogger;
use Throwable;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;

/**
 * Retries transient Zoho IMAP failures during inbox sync paging.
 *
 * Empty responses may be treated as an empty page after retries. Connection failures
 * are retried but still fail the folder if they persist. Other errors are rethrown.
 */
class ImapEmptyResponseRetry
{
    public function __construct(
        public readonly int $retries = 2,
        public readonly int $sleepMs = 500,
        public readonly int $connectionRetries = 2,
        public readonly int $connectionSleepMs = 1000,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            max(0, (int) config('imap_sync.empty_response_retries', 2)),
            max(0, (int) config('imap_sync.empty_response_retry_sleep_ms', 500)),
            max(0, (int) config('imap_sync.connection_failed_retries', 2)),
            max(0, (int) config('imap_sync.connection_failed_retry_sleep_ms', 1000)),
        );
    }

    public static function isRetryable(Throwable $e): bool
    {
        return self::isEmptyResponse($e) || self::isConnectionFailure($e);
    }

    public static function isEmptyResponse(Throwable $e): bool
    {
        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            if (str_contains(strtolower($current->getMessage()), 'empty response')) {
                return true;
            }
        }

        return false;
    }

    public static function isConnectionFailure(Throwable $e): bool
    {
        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof ConnectionFailedException) {
                return true;
            }

            if (str_contains(strtolower($current->getMessage()), 'connection failed')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  callable(): list<array<string, mixed>>  $fetch
     * @param  array<string, mixed>  $context
     * @return list<array<string, mixed>>
     */
    public function fetchOrEmptyPage(callable $fetch, array $context = []): array
    {
        $attempt = 0;
        $lastFailure = null;

        while (true) {
            try {
                return $fetch();
            } catch (Throwable $e) {
                if (! self::isRetryable($e)) {
                    throw $e;
                }

                $lastFailure = $e;
                $attempt++;
                $maxAttempts = $this->maxAttemptsFor($e);

                if ($attempt < $maxAttempts) {
                    InboxSyncLogger::warning($this->retryLogEvent($e), array_merge($context, [
                        'attempt' => $attempt,
                        'max_attempts' => $maxAttempts,
                        'error' => $e->getMessage(),
                    ]));

                    $this->pauseBetweenRetries($e);

                    continue;
                }

                break;
            }
        }

        if ($lastFailure !== null && self::isEmptyResponse($lastFailure)) {
            InboxSyncLogger::warning('IMAP empty response after retries; treating as no messages this page', array_merge($context, [
                'attempts' => $this->maxAttemptsFor($lastFailure),
                'error' => $lastFailure->getMessage(),
            ]));

            return [];
        }

        if ($lastFailure !== null) {
            throw $lastFailure;
        }

        return [];
    }

    protected function maxAttemptsFor(?Throwable $e): int
    {
        if ($e !== null && self::isConnectionFailure($e)) {
            return $this->connectionRetries + 1;
        }

        return $this->retries + 1;
    }

    protected function retryLogEvent(Throwable $e): string
    {
        if (self::isConnectionFailure($e)) {
            return 'IMAP connection failed, retrying fetch';
        }

        return 'IMAP empty response, retrying fetch';
    }

    protected function pauseBetweenRetries(Throwable $e): void
    {
        $sleepMs = self::isConnectionFailure($e) ? $this->connectionSleepMs : $this->sleepMs;

        if ($sleepMs <= 0) {
            return;
        }

        usleep($sleepMs * 1000);
    }
}
