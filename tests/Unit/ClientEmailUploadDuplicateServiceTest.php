<?php

namespace Tests\Unit;

use App\Models\EmailLog;
use App\Services\Email\ClientEmailUploadDuplicateService;
use App\Services\EmailSync\ManualUploadThreadMatchService;
use App\Services\EmailSync\UnassignedEmailUploadMatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientEmailUploadDuplicateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_label_for_sent_on_client_matter(): void
    {
        $service = new ClientEmailUploadDuplicateService(
            $this->createMock(UnassignedEmailUploadMatchService::class),
            $this->createMock(ManualUploadThreadMatchService::class),
        );

        $email = new EmailLog([
            'mail_body_type' => 'sent',
            'client_id' => 60,
            'client_matter_id' => 1,
        ]);

        $this->assertSame('client_sent', $service->locationKey($email));
        $this->assertSame('Client matter → Sent', $service->locationLabel($email));
    }

    public function test_local_allows_duplicate_when_unassigned_synced_match_exists(): void
    {
        $this->app->detectEnvironment(fn () => 'local');

        EmailLog::query()->create([
            'subject' => 'Thread subject',
            'from_mail' => 'sender@example.com',
            'sync_assignment_status' => 'unassigned',
            'synced_email_id' => 55,
            'mail_body_type' => 'inbox',
            'type' => 'client',
            'mail_type' => 1,
        ]);

        $service = app(ClientEmailUploadDuplicateService::class);
        $block = $service->buildManualUploadBlockResponse(
            10,
            20,
            [
                'subject' => 'Thread subject',
                'sender_email' => 'sender@example.com',
                'message_id' => '<msg-1@example.com>',
            ],
            ''
        );

        $this->assertNull($block);
    }

    public function test_production_still_blocks_unassigned_synced_match(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        EmailLog::query()->create([
            'subject' => 'Thread subject',
            'from_mail' => 'sender@example.com',
            'sync_assignment_status' => 'unassigned',
            'synced_email_id' => 56,
            'mail_body_type' => 'inbox',
            'type' => 'client',
            'mail_type' => 1,
        ]);

        $service = app(ClientEmailUploadDuplicateService::class);
        $block = $service->buildManualUploadBlockResponse(
            10,
            20,
            [
                'subject' => 'Thread subject',
                'sender_email' => 'sender@example.com',
            ],
            ''
        );

        $this->assertNotNull($block);
        $this->assertSame('unassigned_match', $block['error_code']);
        $this->assertTrue($block['block_force_upload']);
    }

    public function test_local_allows_duplicate_when_assigned_synced_match_exists_on_matter(): void
    {
        $this->app->detectEnvironment(fn () => 'local');

        EmailLog::query()->create([
            'subject' => 'Assigned sync',
            'from_mail' => 'sender@example.com',
            'client_id' => 10,
            'client_matter_id' => 20,
            'sync_assignment_status' => 'auto_assigned',
            'synced_email_id' => 77,
            'mail_body_type' => 'sent',
            'type' => 'client',
            'mail_type' => 1,
        ]);

        $service = app(ClientEmailUploadDuplicateService::class);
        $block = $service->buildManualUploadBlockResponse(
            10,
            20,
            [
                'subject' => 'Assigned sync',
                'sender_email' => 'sender@example.com',
            ],
            ''
        );

        $this->assertNull($block);
    }
}
