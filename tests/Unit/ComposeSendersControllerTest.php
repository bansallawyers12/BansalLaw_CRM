<?php

namespace Tests\Unit;

use App\Models\Email;
use App\Http\Controllers\CRM\ComposeSendersController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComposeSendersControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_zoho_and_ses_senders_with_default_from_fallback(): void
    {
        config([
            'mail.from.address' => 'noreply@example.com',
            'mail.from.name' => 'Example CRM',
        ]);

        Email::factory()->create([
            'email' => 'staff@example.com',
            'mail_provider' => 'zoho',
            'status' => true,
        ]);

        Email::factory()->create([
            'email' => 'notifications@example.com',
            'mail_provider' => 'ses',
            'status' => true,
        ]);

        $response = app(ComposeSendersController::class)->senders(request());
        $payload = $response->getData(true);

        $this->assertCount(3, $payload['senders']);
        $this->assertSame('staff@example.com', $payload['default_from']);
        $this->assertTrue(collect($payload['senders'])->contains(
            fn (array $sender) => $sender['email'] === 'noreply@example.com' && $sender['provider'] === 'ses'
        ));
    }
}
