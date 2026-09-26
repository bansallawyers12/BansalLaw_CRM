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

    #[Test]
    public function nav_badge_count_excludes_unassigned_synced_row_with_manual_upload_twin(): void
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

        $this->assertSame(2, IncomingEmailSyncService::countUnassignedSyncedInboxMail($staff));
        $this->assertSame(1, IncomingEmailSyncService::countUnassignedSyncedInboxMailForNavBadge($staff));
    }
}
