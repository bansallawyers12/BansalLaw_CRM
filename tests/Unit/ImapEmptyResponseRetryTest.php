<?php

namespace Tests\Unit;

use App\Services\EmailSync\ImapEmptyResponseRetry;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;
use Throwable;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;
use Webklex\PHPIMAP\Exceptions\GetMessagesFailedException;

class ImapEmptyResponseRetryTest extends TestCase
{
    #[Test]
    public function empty_response_exception_is_retryable(): void
    {
        $exception = new GetMessagesFailedException('empty response', 0, new RuntimeException('empty response'));

        $this->assertTrue(ImapEmptyResponseRetry::isRetryable($exception));
    }

    #[Test]
    public function nested_empty_response_message_is_retryable(): void
    {
        $exception = new GetMessagesFailedException(
            'Command failed to process',
            0,
            new RuntimeException("Causes:\n\t- Empty response")
        );

        $this->assertTrue(ImapEmptyResponseRetry::isRetryable($exception));
    }

    #[Test]
    public function connection_failed_exception_is_retryable(): void
    {
        $this->assertTrue(ImapEmptyResponseRetry::isRetryable(
            new ConnectionFailedException('connection failed')
        ));
    }

    #[Test]
    public function folder_and_auth_errors_are_not_retryable(): void
    {
        $this->assertFalse(ImapEmptyResponseRetry::isRetryable(
            new RuntimeException('IMAP folder not found (tried: Sent)')
        ));
        $this->assertFalse(ImapEmptyResponseRetry::isRetryable(
            new RuntimeException('Zoho app password is missing for michael@bansallawyers.com.au')
        ));
    }

    #[Test]
    public function first_successful_fetch_is_returned_without_retry(): void
    {
        $retry = new ImapEmptyResponseRetry(retries: 2, sleepMs: 0);
        $calls = 0;
        $page = [['uid' => 12, 'raw_eml' => 'x', 'subject' => 'Hello', 'is_seen' => true]];

        $result = $retry->fetchOrEmptyPage(function () use (&$calls, $page): array {
            $calls++;

            return $page;
        });

        $this->assertSame(1, $calls);
        $this->assertSame($page, $result);
    }

    #[Test]
    public function empty_response_is_retried_and_later_page_is_returned(): void
    {
        $retry = new ImapEmptyResponseRetry(retries: 2, sleepMs: 0);
        $calls = 0;
        $page = [['uid' => 18, 'raw_eml' => 'x', 'subject' => 'Hello', 'is_seen' => false]];

        $result = $retry->fetchOrEmptyPage(function () use (&$calls, $page): array {
            $calls++;
            if ($calls === 1) {
                throw new GetMessagesFailedException('empty response', 0, new RuntimeException('empty response'));
            }

            return $page;
        });

        $this->assertSame(2, $calls);
        $this->assertSame($page, $result);
    }

    #[Test]
    public function exhausted_empty_response_retries_return_empty_page(): void
    {
        $retry = new ImapEmptyResponseRetry(retries: 2, sleepMs: 0);
        $calls = 0;

        $result = $retry->fetchOrEmptyPage(function () use (&$calls): array {
            $calls++;
            throw new GetMessagesFailedException('empty response', 0, new RuntimeException('empty response'));
        });

        $this->assertSame(3, $calls);
        $this->assertSame([], $result);
    }

    #[Test]
    public function connection_failure_is_retried_and_later_page_is_returned(): void
    {
        $retry = new ImapEmptyResponseRetry(retries: 2, sleepMs: 0, connectionRetries: 2, connectionSleepMs: 0);
        $calls = 0;
        $page = [['uid' => 21, 'raw_eml' => 'x', 'subject' => 'Hello', 'is_seen' => false]];

        $result = $retry->fetchOrEmptyPage(function () use (&$calls, $page): array {
            $calls++;
            if ($calls === 1) {
                throw new ConnectionFailedException('connection failed');
            }

            return $page;
        });

        $this->assertSame(2, $calls);
        $this->assertSame($page, $result);
    }

    #[Test]
    public function exhausted_connection_retries_still_throw(): void
    {
        $retry = new ImapEmptyResponseRetry(retries: 2, sleepMs: 0, connectionRetries: 2, connectionSleepMs: 0);
        $calls = 0;

        try {
            $retry->fetchOrEmptyPage(function () use (&$calls): array {
                $calls++;
                throw new ConnectionFailedException('connection failed');
            });
            $this->fail('Connection failures must still throw after retries.');
        } catch (Throwable $e) {
            $this->assertInstanceOf(ConnectionFailedException::class, $e);
        }

        $this->assertSame(3, $calls);
    }

    #[Test]
    public function non_empty_imap_errors_are_not_swallowed(): void
    {
        $retry = new ImapEmptyResponseRetry(retries: 2, sleepMs: 0);
        $calls = 0;

        try {
            $retry->fetchOrEmptyPage(function () use (&$calls): array {
                $calls++;
                throw new RuntimeException('IMAP folder not found (tried: Sent)');
            });
            $this->fail('Non-empty IMAP errors must still throw.');
        } catch (Throwable $e) {
            $this->assertSame('IMAP folder not found (tried: Sent)', $e->getMessage());
        }

        $this->assertSame(1, $calls);
    }
}
