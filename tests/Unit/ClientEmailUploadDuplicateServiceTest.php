<?php

namespace Tests\Unit;

use App\Models\EmailLog;
use App\Services\Email\ClientEmailUploadDuplicateService;
use App\Services\EmailSync\ManualUploadThreadMatchService;
use App\Services\EmailSync\UnassignedEmailUploadMatchService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
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

    #[Test]
    public function sent_upload_is_not_blocked_when_same_thread_subject_but_different_recipient_and_date(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        EmailLog::query()->create([
            'subject' => 'Potential briefs | Rahul Saini and Gunvir Singh',
            'from_mail' => 'michael@bansallawyers.com.au',
            'to_mail' => 'jonathan@example.com',
            'client_id' => 10,
            'client_matter_id' => 20,
            'fetch_mail_sent_time' => Carbon::parse('2026-10-02 14:19:00', 'Australia/Melbourne'),
            'mail_body_type' => 'sent',
            'type' => 'client',
            'mail_type' => 1,
        ]);

        $service = app(ClientEmailUploadDuplicateService::class);
        $block = $service->buildManualUploadBlockResponse(
            10,
            20,
            [
                'subject' => 'URGENT | Potential briefs | Rahul Saini and Gunvir Singh',
                'sender_email' => 'michael@bansallawyers.com.au',
                'to_recipients' => ['foleys@foleys.com.au'],
                'sent_date' => '2026-10-12 16:23:00',
            ],
            '',
            'sent'
        );

        $this->assertNull($block);
    }

    #[Test]
    public function sent_upload_is_still_blocked_when_subject_from_to_and_sent_time_match(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        EmailLog::query()->create([
            'subject' => 'Brief for counsel',
            'from_mail' => 'michael@bansallawyers.com.au',
            'to_mail' => 'foleys@foleys.com.au',
            'client_id' => 10,
            'client_matter_id' => 20,
            'fetch_mail_sent_time' => Carbon::parse('2026-10-12 16:23:00', 'Australia/Melbourne'),
            'mail_body_type' => 'sent',
            'type' => 'client',
            'mail_type' => 1,
        ]);

        $service = app(ClientEmailUploadDuplicateService::class);
        $block = $service->buildManualUploadBlockResponse(
            10,
            20,
            [
                'subject' => 'Brief for counsel',
                'sender_email' => 'michael@bansallawyers.com.au',
                'to_recipients' => ['foleys@foleys.com.au'],
                'sent_date' => '2026-10-12 16:23:00',
            ],
            '',
            'sent'
        );

        $this->assertNotNull($block);
        $this->assertSame('existing_email', $block['error_code']);
    }

    #[Test]
    public function inbox_thread_subject_match_still_blocks_on_matter(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        EmailLog::query()->create([
            'subject' => 'Re: FAM_1 | Court hearing',
            'from_mail' => 'client@example.com',
            'client_id' => 10,
            'client_matter_id' => 20,
            'mail_body_type' => 'inbox',
            'type' => 'client',
            'mail_type' => 1,
        ]);

        $service = app(ClientEmailUploadDuplicateService::class);
        $block = $service->buildManualUploadBlockResponse(
            10,
            20,
            [
                'subject' => 'Re: Re: FAM_1 | Court hearing',
                'sender_email' => 'client@example.com',
            ],
            '',
            'inbox'
        );

        $this->assertNotNull($block);
        $this->assertSame('existing_email', $block['error_code']);
    }

    #[Test]
    public function inbox_upload_is_not_blocked_when_subject_from_and_time_match_but_recipient_differs(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        EmailLog::query()->create([
            'subject' => 'test vip',
            'from_mail' => 'Vipul_Devlop.bi@outlook.com',
            'to_mail' => 'viplucmca@yahoo.co.in',
            'client_id' => 10,
            'client_matter_id' => 20,
            'fetch_mail_sent_time' => Carbon::parse('2026-10-13 22:40:00', 'Australia/Melbourne'),
            'mail_body_type' => 'inbox',
            'type' => 'client',
            'mail_type' => 1,
        ]);

        $service = app(ClientEmailUploadDuplicateService::class);
        $block = $service->buildManualUploadBlockResponse(
            10,
            20,
            [
                'subject' => 'test vip',
                'sender_email' => 'Vipul_Devlop.bi@outlook.com',
                'to_recipients' => ['other.person@example.com'],
                'sent_date' => '2026-10-13 22:40:00',
            ],
            '',
            'inbox'
        );

        $this->assertNull($block);
    }

    #[Test]
    public function inbox_upload_is_still_blocked_when_subject_from_to_and_sent_time_match(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        EmailLog::query()->create([
            'subject' => 'test vip',
            'from_mail' => 'Vipul_Devlop.bi@outlook.com',
            'to_mail' => 'viplucmca@yahoo.co.in',
            'client_id' => 10,
            'client_matter_id' => 20,
            'fetch_mail_sent_time' => Carbon::parse('2026-10-13 22:40:00', 'Australia/Melbourne'),
            'mail_body_type' => 'inbox',
            'type' => 'client',
            'mail_type' => 1,
        ]);

        $service = app(ClientEmailUploadDuplicateService::class);
        $block = $service->buildManualUploadBlockResponse(
            10,
            20,
            [
                'subject' => 'test vip',
                'sender_email' => 'Vipul_Devlop.bi@outlook.com',
                'to_recipients' => ['viplucmca@yahoo.co.in'],
                'sent_date' => '2026-10-13 22:40:00',
            ],
            '',
            'inbox'
        );

        $this->assertNotNull($block);
        $this->assertSame('existing_email', $block['error_code']);
    }

    #[Test]
    public function inbox_thread_match_allows_upload_when_recipient_differs(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        EmailLog::query()->create([
            'subject' => 'Re: FAM_1 | Court hearing',
            'from_mail' => 'client@example.com',
            'to_mail' => 'matter@example.com',
            'client_id' => 10,
            'client_matter_id' => 20,
            'mail_body_type' => 'inbox',
            'type' => 'client',
            'mail_type' => 1,
        ]);

        $service = app(ClientEmailUploadDuplicateService::class);
        $block = $service->buildManualUploadBlockResponse(
            10,
            20,
            [
                'subject' => 'Re: Re: FAM_1 | Court hearing',
                'sender_email' => 'client@example.com',
                'to_recipients' => ['other.person@example.com'],
            ],
            '',
            'inbox'
        );

        $this->assertNull($block);
    }

    #[Test]
    public function inbox_upload_is_not_blocked_when_message_id_matches_but_recipient_differs(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        EmailLog::query()->create([
            'subject' => 'test vip',
            'from_mail' => 'Vipul_Devlop.bi@outlook.com',
            'to_mail' => 'viplucmca@yahoo.co.in',
            'message_id' => '<same-message@example.com>',
            'client_id' => 10,
            'client_matter_id' => 20,
            'mail_body_type' => 'inbox',
            'type' => 'client',
            'mail_type' => 1,
        ]);

        $service = app(ClientEmailUploadDuplicateService::class);
        $block = $service->buildManualUploadBlockResponse(
            10,
            20,
            [
                'subject' => 'test vip',
                'sender_email' => 'Vipul_Devlop.bi@outlook.com',
                'message_id' => '<same-message@example.com>',
                'to_recipients' => ['other.person@example.com'],
            ],
            '',
            'inbox'
        );

        $this->assertNull($block);
    }

    #[Test]
    public function inbox_upload_is_still_blocked_when_message_id_and_recipient_match(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        EmailLog::query()->create([
            'subject' => 'test vip',
            'from_mail' => 'Vipul_Devlop.bi@outlook.com',
            'to_mail' => 'viplucmca@yahoo.co.in',
            'message_id' => '<same-message@example.com>',
            'client_id' => 10,
            'client_matter_id' => 20,
            'mail_body_type' => 'inbox',
            'type' => 'client',
            'mail_type' => 1,
        ]);

        $service = app(ClientEmailUploadDuplicateService::class);
        $block = $service->buildManualUploadBlockResponse(
            10,
            20,
            [
                'subject' => 'test vip',
                'sender_email' => 'Vipul_Devlop.bi@outlook.com',
                'message_id' => '<same-message@example.com>',
                'to_recipients' => ['viplucmca@yahoo.co.in'],
            ],
            '',
            'inbox'
        );

        $this->assertNotNull($block);
        $this->assertSame('existing_email', $block['error_code']);
    }

    #[Test]
    public function inbox_upload_is_still_blocked_when_message_id_matches_and_recipient_is_missing(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        EmailLog::query()->create([
            'subject' => 'test vip',
            'from_mail' => 'Vipul_Devlop.bi@outlook.com',
            'message_id' => '<same-message@example.com>',
            'client_id' => 10,
            'client_matter_id' => 20,
            'mail_body_type' => 'inbox',
            'type' => 'client',
            'mail_type' => 1,
        ]);

        $service = app(ClientEmailUploadDuplicateService::class);
        $block = $service->buildManualUploadBlockResponse(
            10,
            20,
            [
                'subject' => 'test vip',
                'sender_email' => 'Vipul_Devlop.bi@outlook.com',
                'message_id' => '<same-message@example.com>',
            ],
            '',
            'inbox'
        );

        $this->assertNotNull($block);
        $this->assertSame('existing_email', $block['error_code']);
    }

    #[Test]
    public function inbox_upload_is_still_blocked_when_file_hash_matches(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        EmailLog::query()->create([
            'subject' => 'test vip',
            'from_mail' => 'Vipul_Devlop.bi@outlook.com',
            'to_mail' => 'viplucmca@yahoo.co.in',
            'file_hash' => 'abc123samefile',
            'client_id' => 10,
            'client_matter_id' => 20,
            'mail_body_type' => 'inbox',
            'type' => 'client',
            'mail_type' => 1,
        ]);

        $service = app(ClientEmailUploadDuplicateService::class);
        $block = $service->buildManualUploadBlockResponse(
            10,
            20,
            [
                'subject' => 'test vip',
                'sender_email' => 'Vipul_Devlop.bi@outlook.com',
                'to_recipients' => ['other.person@example.com'],
            ],
            'abc123samefile',
            'inbox'
        );

        $this->assertNotNull($block);
        $this->assertSame('existing_email', $block['error_code']);
    }
}
