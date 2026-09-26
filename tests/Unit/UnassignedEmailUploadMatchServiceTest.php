<?php

namespace Tests\Unit;

use App\Models\EmailLog;
use App\Services\EmailSync\UnassignedEmailUploadMatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UnassignedEmailUploadMatchServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_finds_unassigned_synced_email_by_message_id(): void
    {
        EmailLog::query()->create([
            'subject' => 'Re: FAM_1 | Court hearing',
            'from_mail' => 'client@example.com',
            'to_mail' => 'lawyer@bansallawyers.com.au',
            'message_id' => '<abc123@mail.example>',
            'sync_assignment_status' => 'unassigned',
            'synced_email_id' => 42,
            'mail_body_type' => 'inbox',
            'type' => 'client',
            'mail_type' => 1,
        ]);

        $service = app(UnassignedEmailUploadMatchService::class);
        $match = $service->findMatch([
            'subject' => 'Re: FAM_1 | Court hearing',
            'message_id' => '<abc123@mail.example>',
            'sender_email' => 'client@example.com',
        ], '', 'inbox');

        $this->assertNotNull($match);
        $this->assertSame('<abc123@mail.example>', $match->message_id);
    }

    #[Test]
    public function it_does_not_match_assigned_client_email(): void
    {
        EmailLog::query()->create([
            'subject' => 'Assigned copy',
            'from_mail' => 'client@example.com',
            'message_id' => '<assigned@mail.example>',
            'client_id' => 99,
            'client_matter_id' => 1,
            'synced_email_id' => 5,
            'mail_body_type' => 'inbox',
            'type' => 'client',
            'mail_type' => 1,
        ]);

        $service = app(UnassignedEmailUploadMatchService::class);
        $match = $service->findMatch([
            'message_id' => '<assigned@mail.example>',
        ], '', 'inbox');

        $this->assertNull($match);
    }

    #[Test]
    public function it_finds_unassigned_twin_for_manual_client_upload(): void
    {
        $unassigned = EmailLog::query()->create([
            'subject' => 'Twin message',
            'from_mail' => 'client@example.com',
            'message_id' => '<twin@example>',
            'sync_assignment_status' => 'unassigned',
            'synced_email_id' => 77,
            'mail_body_type' => 'inbox',
            'type' => 'client',
            'mail_type' => 1,
        ]);

        $manual = EmailLog::query()->create([
            'subject' => 'Twin message',
            'from_mail' => 'client@example.com',
            'message_id' => '<twin@example>',
            'client_id' => 10,
            'client_matter_id' => 20,
            'sync_source' => EmailLog::SYNC_SOURCE_UPLOAD,
            'uploaded_doc_id' => 1,
            'mail_body_type' => 'inbox',
            'type' => 'client',
            'mail_type' => 1,
        ]);

        $service = app(UnassignedEmailUploadMatchService::class);
        $match = $service->findUnassignedMatchForClientEmail($manual);

        $this->assertNotNull($match);
        $this->assertSame((int) $unassigned->id, (int) $match->id);
    }
}
