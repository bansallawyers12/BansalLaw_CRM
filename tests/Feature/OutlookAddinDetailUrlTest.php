<?php

namespace Tests\Feature;

use App\Http\Controllers\CRM\OutlookAddinController;
use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\EmailLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OutlookAddinDetailUrlTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function already_exists_detail_url_uses_uuencode_and_opens_emails_tab(): void
    {
        $client = Admin::factory()->create([
            'type' => 'client',
            'client_id' => 'TEST2600026',
            'is_archived' => 0,
        ]);

        $email = EmailLog::query()->create([
            'client_id' => $client->id,
            'client_matter_id' => null,
            'subject' => 'Test regarding',
            'from_mail' => 'sonukumar95061980@gmail.com',
            'type' => 'client',
            'mail_body_type' => 'inbox',
            'mail_type' => 1,
            'sync_source' => EmailLog::SYNC_SOURCE_OUTLOOK_ADDIN,
        ]);

        $controller = app(OutlookAddinController::class);
        $method = new \ReflectionMethod($controller, 'alreadyExistsResponse');
        $method->setAccessible(true);
        /** @var \Illuminate\Http\JsonResponse $response */
        $response = $method->invoke($controller, $email, (int) $client->id, 0);

        $payload = $response->getData(true);
        $detailUrl = (string) ($payload['detail_url'] ?? '');

        $this->assertNotSame('', $detailUrl);
        $this->assertStringContainsString('/clients/detail/', $detailUrl);
        $this->assertStringContainsString('/emails', $detailUrl);
        $this->assertStringContainsString('select_email='.$email->id, $detailUrl);

        // Path is /clients/detail/{encoded}/emails — must decode with ClientsController scheme.
        $parts = explode('/', trim((string) parse_url($detailUrl, PHP_URL_PATH), '/'));
        $encodedSegment = $parts[2] ?? '';
        $expectedEncoded = base64_encode(convert_uuencode((string) $client->id));
        $this->assertSame($expectedEncoded, $encodedSegment);

        $decoded = app(\App\Http\Controllers\Controller::class)->decodeString($encodedSegment);
        $this->assertSame((string) $client->id, (string) $decoded);
    }

    #[Test]
    public function already_exists_detail_url_includes_matter_when_present(): void
    {
        $client = Admin::factory()->create([
            'type' => 'client',
            'client_id' => 'GURM2600071',
            'is_archived' => 0,
        ]);
        $matter = ClientMatter::create([
            'client_id' => $client->id,
            'client_unique_matter_no' => 'FAM_1',
            'matter_status' => 1,
        ]);
        $email = EmailLog::query()->create([
            'client_id' => $client->id,
            'client_matter_id' => $matter->id,
            'subject' => 'Matter mail',
            'from_mail' => 'a@example.com',
            'type' => 'client',
            'mail_body_type' => 'inbox',
            'mail_type' => 1,
        ]);

        $controller = app(OutlookAddinController::class);
        $method = new \ReflectionMethod($controller, 'alreadyExistsResponse');
        $method->setAccessible(true);
        $response = $method->invoke($controller, $email, (int) $client->id, (int) $matter->id);
        $detailUrl = (string) ($response->getData(true)['detail_url'] ?? '');

        $this->assertStringContainsString('/FAM_1/emails', $detailUrl);
        $this->assertStringContainsString('select_email='.$email->id, $detailUrl);
    }
}
