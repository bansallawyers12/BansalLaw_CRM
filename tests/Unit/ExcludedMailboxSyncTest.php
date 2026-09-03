<?php

namespace Tests\Unit;

use App\Services\EmailSync\IncomingEmailSyncService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExcludedMailboxSyncTest extends TestCase
{
    #[Test]
    public function michaelsaleh_mailbox_is_excluded_by_default_config(): void
    {
        config([
            'imap_sync.excluded_mailboxes' => ['michaelsaleh.bi@outlook.com'],
        ]);

        $this->assertTrue(
            IncomingEmailSyncService::isMailboxExcluded('michaelsaleh.bi@outlook.com')
        );
        $this->assertTrue(
            IncomingEmailSyncService::isMailboxExcluded('MichaelSaleh.bi@outlook.com')
        );
        $this->assertFalse(
            IncomingEmailSyncService::isMailboxExcluded('someone.else@example.com')
        );
    }

    #[Test]
    public function find_syncable_mailbox_returns_null_for_excluded_address(): void
    {
        config([
            'imap_sync.excluded_mailboxes' => ['michaelsaleh.bi@outlook.com'],
        ]);

        $this->assertNull(
            IncomingEmailSyncService::findSyncableMailbox('michaelsaleh.bi@outlook.com')
        );
    }
}
