<?php

namespace Tests\Unit;

use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\Email;
use App\Models\EmailLog;
use App\Models\Staff;
use App\Services\EmailSync\IncomingEmailSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UnassignedNavCountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        IncomingEmailSyncService::forgetSyncableMailboxCacheForTesting();
    }

    #[Test]
    public function nav_badge_count_matches_unassigned_only_not_manual_upload_match_rows(): void
    {
        $staff = Staff::factory()->superAdmin()->create(['status' => 1]);
        $mailbox = Email::factory()->create([
            'password' => 'secret-zoho-pass',
            'sync_enabled' => true,
        ]);
        $client = Admin::factory()->create(['type' => 'client', 'is_archived' => 0]);
        $matter = ClientMatter::create([
            'client_id' => $client->id,
            'client_unique_matter_no' => 'NAV_1',
            'matter_status' => 1,
        ]);

        EmailLog::query()->create([
            'subject' => 'Twin for nav',
            'from_mail' => 'a@example.com',
            'message_id' => '<nav-twin@example>',
            'sync_assignment_status' => 'unassigned',
            'synced_email_id' => $mailbox->id,
            'mail_body_type' => 'inbox',
            'type' => 'client',
            'mail_type' => 1,
        ]);

        EmailLog::query()->create([
            'subject' => 'Twin for nav',
            'from_mail' => 'a@example.com',
            'message_id' => '<nav-twin@example>',
            'client_id' => $client->id,
            'client_matter_id' => $matter->id,
            'sync_source' => EmailLog::SYNC_SOURCE_UPLOAD,
            'uploaded_doc_id' => 1,
            'mail_body_type' => 'inbox',
            'type' => 'client',
            'mail_type' => 1,
        ]);

        EmailLog::query()->create([
            'subject' => 'No manual twin',
            'from_mail' => 'b@example.com',
            'message_id' => '<solo@example>',
            'sync_assignment_status' => 'unassigned',
            'synced_email_id' => $mailbox->id,
            'mail_body_type' => 'inbox',
            'type' => 'client',
            'mail_type' => 1,
        ]);

        $breakdown = IncomingEmailSyncService::unassignedInboxCountBreakdown($staff);
        $this->assertSame(2, $breakdown['total']);
        $this->assertSame(1, $breakdown['unassigned_only_count']);
        $this->assertSame(1, $breakdown['manual_upload_match_count']);

        $this->assertSame(2, IncomingEmailSyncService::countUnassignedSyncedInboxMail($staff));
        $this->assertSame(1, IncomingEmailSyncService::countUnassignedSyncedInboxMailForNavBadge($staff));
        $this->assertSame(
            $breakdown['unassigned_only_count'],
            IncomingEmailSyncService::countUnassignedSyncedInboxMailForNavBadge($staff)
        );
    }

    #[Test]
    public function breakdown_manual_match_count_uses_thread_matching_not_only_message_id_twin(): void
    {
        $staff = Staff::factory()->superAdmin()->create(['status' => 1]);
        $mailbox = Email::factory()->create([
            'password' => 'secret-zoho-pass',
            'sync_enabled' => true,
        ]);
        $client = Admin::factory()->create(['type' => 'client', 'is_archived' => 0]);
        $matter = ClientMatter::create([
            'client_id' => $client->id,
            'client_unique_matter_no' => 'BRK_1',
            'matter_status' => 1,
        ]);

        EmailLog::query()->create([
            'subject' => 'Re: Thread subject for breakdown',
            'from_mail' => 'a@example.com',
            'message_id' => '<unassigned-only-thread@example>',
            'sync_assignment_status' => 'unassigned',
            'synced_email_id' => $mailbox->id,
            'mail_body_type' => 'inbox',
            'type' => 'client',
            'mail_type' => 1,
        ]);

        EmailLog::query()->create([
            'subject' => 'Thread subject for breakdown',
            'from_mail' => 'a@example.com',
            'message_id' => '<manual-different-id@example>',
            'client_id' => $client->id,
            'client_matter_id' => $matter->id,
            'sync_source' => EmailLog::SYNC_SOURCE_UPLOAD,
            'uploaded_doc_id' => 1,
            'mail_body_type' => 'inbox',
            'type' => 'client',
            'mail_type' => 1,
        ]);

        EmailLog::query()->create([
            'subject' => 'No manual twin',
            'from_mail' => 'b@example.com',
            'message_id' => '<solo-breakdown@example>',
            'sync_assignment_status' => 'unassigned',
            'synced_email_id' => $mailbox->id,
            'mail_body_type' => 'inbox',
            'type' => 'client',
            'mail_type' => 1,
        ]);

        $breakdown = IncomingEmailSyncService::unassignedInboxCountBreakdown($staff);
        $this->assertSame(2, $breakdown['total']);
        $this->assertSame(1, $breakdown['manual_upload_match_count']);
        $this->assertSame(1, $breakdown['unassigned_only_count']);
        $this->assertSame(
            2,
            IncomingEmailSyncService::countUnassignedSyncedInboxMail($staff, excludeManualUploadTwins: true),
            'Nav-style SQL twin exclusion does not detect thread-only matches'
        );
    }
}
