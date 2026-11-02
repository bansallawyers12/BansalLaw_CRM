<?php

namespace Tests\Unit;

use App\Models\EmailCalendarLink;
use App\Models\EmailLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailLogManualUploadCalendarListVisibilityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function manual_upload_with_calendar_link_stays_on_mail_list(): void
    {
        if (! Schema::hasTable('email_calendar_links')) {
            $this->markTestSkipped('email_calendar_links table not present');
        }

        $manual = EmailLog::query()->create([
            'subject' => 'Request for Refund - Appointment on 02 Nov 2026 at 1:30 PM - $150',
            'from_mail' => 'rahulbamanairforce@gmail.com',
            'type' => 'lead',
            'mail_type' => 1,
            'mail_body_type' => 'inbox',
            'client_id' => 262,
            'sync_source' => EmailLog::SYNC_SOURCE_UPLOAD,
            'text_preview' => 'Please process the refund for my appointment.',
        ]);

        EmailCalendarLink::query()->create([
            'email_log_id' => $manual->id,
            'calendar_type' => EmailCalendarLink::TYPE_STAFF_EVENT,
            'calendar_id' => 1,
            'event_type' => 'appointment',
            'event_title' => 'Appointment',
            'starts_at' => now()->addDay(),
            'source' => 'email_body',
            'status' => EmailCalendarLink::STATUS_MERGED,
        ]);

        $syncedHidden = EmailLog::query()->create([
            'subject' => 'Appointment reminder from mailbox',
            'from_mail' => 'sync@example.com',
            'type' => 'client',
            'mail_type' => 1,
            'mail_body_type' => 'inbox',
            'client_id' => 10,
            'synced_email_id' => 99,
            'imap_uid' => 88,
            'sync_source' => EmailLog::SYNC_SOURCE_CRON,
            'sync_assignment_status' => 'auto_assigned',
            'text_preview' => 'Synced appointment mail.',
        ]);

        EmailCalendarLink::query()->create([
            'email_log_id' => $syncedHidden->id,
            'calendar_type' => EmailCalendarLink::TYPE_STAFF_EVENT,
            'calendar_id' => 2,
            'event_type' => 'appointment',
            'event_title' => 'Synced appointment',
            'starts_at' => now()->addDays(2),
            'source' => 'email_body',
            'status' => EmailCalendarLink::STATUS_MERGED,
        ]);

        $query = EmailLog::query();
        EmailLog::applyExcludeCalendarInvitesFromMailLists($query);
        $ids = $query->pluck('id')->all();

        $this->assertContains($manual->id, $ids);
        $this->assertNotContains($syncedHidden->id, $ids);
    }

    #[Test]
    public function classic_invite_manual_upload_with_calendar_link_stays_hidden(): void
    {
        if (! Schema::hasTable('email_calendar_links')) {
            $this->markTestSkipped('email_calendar_links table not present');
        }

        $invite = EmailLog::query()->create([
            'subject' => 'Invitation: Team sync tomorrow',
            'from_mail' => 'calendar@example.com',
            'type' => 'client',
            'mail_type' => 1,
            'mail_body_type' => 'inbox',
            'client_id' => 55,
            'sync_source' => EmailLog::SYNC_SOURCE_UPLOAD,
            'text_preview' => 'Please join the meeting.',
        ]);

        EmailCalendarLink::query()->create([
            'email_log_id' => $invite->id,
            'calendar_type' => EmailCalendarLink::TYPE_STAFF_EVENT,
            'calendar_id' => 3,
            'event_type' => 'meeting',
            'event_title' => 'Team sync',
            'starts_at' => now()->addDay(),
            'source' => 'ics',
            'status' => EmailCalendarLink::STATUS_MERGED,
        ]);

        $query = EmailLog::query();
        EmailLog::applyExcludeCalendarInvitesFromMailLists($query);
        $ids = $query->pluck('id')->all();

        $this->assertNotContains($invite->id, $ids);
    }
}
