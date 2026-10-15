<?php

namespace Tests\Unit;

use App\Models\Email;
use App\Services\EmailSync\IncomingEmailSyncService;
use App\Services\EmailSync\ZohoImapFetcher;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\Exceptions\GetMessagesFailedException;

class IncomingEmailSyncEmptyResponseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'imap_sync.empty_response_retries' => 2,
            'imap_sync.empty_response_retry_sleep_ms' => 0,
            'imap_sync.connection_failed_retries' => 2,
            'imap_sync.connection_failed_retry_sleep_ms' => 0,
            'imap_sync.max_messages_per_mailbox' => 10,
            'imap_sync.max_incremental_batches' => 3,
        ]);
    }

    #[Test]
    public function empty_response_after_retries_does_not_advance_uid_or_record_folder_error(): void
    {
        $fetcher = $this->mockFolderSessionFetcher();
        $fetcher->shouldReceive('fetchFolderPage')
            ->times(3)
            ->andThrow(new GetMessagesFailedException('empty response', 0, new RuntimeException('empty response')));
        $this->app->instance(ZohoImapFetcher::class, $fetcher);

        $result = $this->syncMailboxFolder($this->mailbox());

        $this->assertSame(100, $result['last_uid']);
        $this->assertSame([], $result['errors']);
        $this->assertSame(0, $result['imported']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame('sent', $result['folder_type']);
    }

    #[Test]
    public function other_imap_failures_still_record_folder_error_without_advancing_uid(): void
    {
        $fetcher = $this->mockFolderSessionFetcher();
        $fetcher->shouldReceive('fetchFolderPage')
            ->once()
            ->andThrow(new RuntimeException('IMAP folder not found (tried: Sent)'));
        $this->app->instance(ZohoImapFetcher::class, $fetcher);

        $result = $this->syncMailboxFolder($this->mailbox());

        $this->assertSame(100, $result['last_uid']);
        $this->assertSame(['SENT: IMAP folder not found (tried: Sent)'], $result['errors']);
        $this->assertSame(0, $result['imported']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function syncMailboxFolder(Email $mailbox): array
    {
        $service = $this->app->make(IncomingEmailSyncService::class);
        $method = new ReflectionMethod(IncomingEmailSyncService::class, 'syncMailboxFolder');
        $method->setAccessible(true);

        return $method->invoke(
            $service,
            $mailbox,
            1,
            ['Sent'],
            100,
            'sent',
            null
        );
    }

    protected function mailbox(): Email
    {
        $mailbox = new Email;
        $mailbox->id = 7;
        $mailbox->email = 'michael@bansallawyers.com.au';

        return $mailbox;
    }

    protected function mockFolderSessionFetcher(): ZohoImapFetcher
    {
        $fetcher = Mockery::mock(ZohoImapFetcher::class);
        $fetcher->shouldReceive('withFolderSession')
            ->once()
            ->andReturnUsing(function ($mailbox, $folders, $callback) {
                return $callback(
                    Mockery::mock(Client::class),
                    new \stdClass,
                    $mailbox
                );
            });

        return $fetcher;
    }
}
