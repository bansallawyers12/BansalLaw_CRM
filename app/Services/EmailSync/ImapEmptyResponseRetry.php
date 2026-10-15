<?php

namespace App\Services\EmailSync;

use App\Logging\InboxSyncLogger;
use Throwable;

/**
 * Retries Zoho IMAP FETCH/SEARCH failures that Webklex reports as "empty response".
 *
 * A remaining empty response is treated as an empty page (no UID advance). Other IMAP
 * errors are rethrown so existing mailbox error handling stays unchanged.
 */
class ImapEmptyResponseRetry
{
    public function __construct(
        public readonly int $retries = 2,
        public readonly int $sleepMs = 500,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            max(0, (int) config('imap_sync.empty_response_retries', 2)),
            max(0, (int) config('imap_sync.empty_response_retry_sleep_ms', 500)),
        );
    }

    public static function isRetryable(Throwable $e): bool
    {
        for ($current = $e; $current !== null; $current = $current->getPrevious()) {
            if (str_contains(strtolower($current->getMessage()), 'empty response')) {
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
        $attempts = $this->retries + 1;
        $lastEmpty = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return $fetch();
            } catch (Throwable $e) {
                if (! self::isRetryable($e)) {
                    throw $e;
                }

                $lastEmpty = $e;

                if ($attempt < $attempts) {
                    InboxSyncLogger::warning('IMAP empty response, retrying fetch', array_merge($context, [
                        'attempt' => $attempt,
                        'max_attempts' => $attempts,
                        'error' => $e->getMessage(),
                    ]));

                    $this->pauseBetweenRetries();

                    continue;
                }
            }
        }

        InboxSyncLogger::warning('IMAP empty response after retries; treating as no messages this page', array_merge($context, [
            'attempts' => $attempts,
            'error' => $lastEmpty?->getMessage(),
        ]));

        return [];
    }

    protected function pauseBetweenRetries(): void
    {
        if ($this->sleepMs <= 0) {
            return;
        }

        usleep($this->sleepMs * 1000);
    }
}
