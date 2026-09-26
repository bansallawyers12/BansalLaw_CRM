<?php

namespace Tests\Unit;

use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\EmailLog;
use App\Models\Matter;
use App\Services\EmailMatchingService;
use App\Services\EmailSync\ManualUploadThreadMatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ManualUploadThreadMatchServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_matches_unassigned_synced_mail_to_manual_upload_matter(): void
    {
        $client = Admin::query()->create([
            'client_id' => 'ZXPA2600075',
            'first_name' => 'Ankur',
            'last_name' => 'Bhatia',
            'email' => 'annkurbhatia@gmail.com',
            'password' => bcrypt('secret'),
            'type' => 'client',
            'status' => 1,
        ]);

        $matterType = Matter::query()->create([
            'title' => 'Civil',
            'status' => 1,
        ]);

        $matter = ClientMatter::query()->create([
            'client_id' => $client->id,
            'sel_matter_id' => $matterType->id,
            'client_unique_matter_no' => 'CIV_1',
        ]);

        EmailLog::query()->create([
            'subject' => 'Re: MAG-CI-260078157 | 21 days will lapse on 19 May 2026',
            'from_mail' => 'annkurbhatia@gmail.com',
            'to_mail' => 'michael@bansallawyers.com.au',
            'client_id' => $client->id,
            'client_matter_id' => $matter->id,
            'conversion_type' => 'conversion_email_fetch',
            'uploaded_doc_id' => 99,
            'type' => 'client',
            'mail_type' => 1,
            'mail_body_type' => 'inbox',
            'received_date' => '2026-07-14 11:12:18',
        ]);

        $unassigned = EmailLog::query()->create([
            'subject' => 'RE: MAG-CI-260078157 | 21 days will lapse on 19 May 2026',
            'from_mail' => 'michael@bansallawyers.com.au',
            'to_mail' => 'annkurbhatia@gmail.com',
            'sync_assignment_status' => 'unassigned',
            'synced_email_id' => 2,
            'imap_uid' => 9999,
            'mailbox_email' => 'michael@bansallawyers.com.au',
            'type' => 'client',
            'mail_type' => 1,
            'mail_body_type' => 'inbox',
            'received_date' => '2026-07-15 09:00:00',
        ]);

        $service = new ManualUploadThreadMatchService(app(EmailMatchingService::class));
        $match = $service->findUniqueMatterMatch($unassigned);

        $this->assertNotNull($match);
        $this->assertSame((int) $client->id, $match['client_id']);
        $this->assertSame((int) $matter->id, $match['client_matter_id']);
        $this->assertSame('manual_upload_thread', $match['matched_by']);
        $this->assertCount(1, $match['matched_manual_emails']);
        $this->assertFalse($match['ambiguous']);
    }

    #[Test]
    public function it_removes_manual_upload_duplicate_when_synced_email_is_assigned(): void
    {
        $client = Admin::factory()->create(['type' => 'client']);
        $matter = ClientMatter::query()->create([
            'client_id' => $client->id,
            'client_unique_matter_no' => 'CIV_2',
            'matter_status' => 1,
        ]);

        $manual = EmailLog::query()->create([
            'subject' => 'Same subject line',
            'from_mail' => 'a@example.com',
            'message_id' => '<same@example>',
            'client_id' => $client->id,
            'client_matter_id' => $matter->id,
            'sync_source' => EmailLog::SYNC_SOURCE_UPLOAD,
            'uploaded_doc_id' => 12,
            'mail_type' => 1,
            'mail_body_type' => 'inbox',
            'type' => 'client',
        ]);

        $assigned = EmailLog::query()->create([
            'subject' => 'Same subject line',
            'from_mail' => 'a@example.com',
            'message_id' => '<same@example>',
            'client_id' => $client->id,
            'client_matter_id' => $matter->id,
            'synced_email_id' => 55,
            'sync_assignment_status' => 'manual_assigned',
            'mail_type' => 1,
            'mail_body_type' => 'inbox',
            'type' => 'client',
        ]);

        $service = app(ManualUploadThreadMatchService::class);
        $removed = $service->removeMatchingManualUploadsAfterAssign($assigned);

        $this->assertContains((int) $manual->id, $removed);
        $this->assertNull(EmailLog::query()->find($manual->id));
        $this->assertNotNull(EmailLog::query()->find($assigned->id));
    }
}
