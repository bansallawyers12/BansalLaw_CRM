<?php

namespace Tests\Unit;

use App\Models\Email;
use App\Services\MailRoutingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailRoutingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected MailRoutingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MailRoutingService();
    }

    public function test_it_routes_staff_email_addresses_to_zoho(): void
    {
        Email::factory()->create([
            'email' => 'michael@example.com',
            'mail_provider' => 'zoho',
            'status' => true,
        ]);

        $this->assertSame('zoho', $this->service->resolveMailerName('michael@example.com'));
    }

    public function test_it_routes_no_reply_addresses_to_ses(): void
    {
        $this->assertSame('ses', $this->service->resolveMailerName('noreply@example.com'));
        $this->assertSame('ses', $this->service->resolveMailerName('no-reply@example.com'));
    }

    public function test_it_routes_explicit_ses_accounts_to_ses(): void
    {
        Email::factory()->create([
            'email' => 'notifications@example.com',
            'mail_provider' => 'ses',
            'status' => true,
        ]);

        $this->assertSame('ses', $this->service->resolveMailerName('notifications@example.com'));
    }

    public function test_it_routes_legacy_sendgrid_accounts_to_ses(): void
    {
        Email::factory()->create([
            'email' => 'legacy@example.com',
            'mail_provider' => 'sendgrid',
            'status' => true,
        ]);

        $this->assertSame('ses', $this->service->resolveMailerName('legacy@example.com'));
    }

    public function test_it_routes_inactive_zoho_accounts_to_ses(): void
    {
        Email::factory()->inactive()->create([
            'email' => 'inactive@example.com',
            'mail_provider' => 'zoho',
        ]);

        $this->assertSame('ses', $this->service->resolveMailerName('inactive@example.com'));
    }

    public function test_it_forces_system_mailer_when_requested(): void
    {
        Email::factory()->create([
            'email' => 'admin@example.com',
            'mail_provider' => 'zoho',
            'status' => true,
        ]);

        $this->assertSame('ses', $this->service->resolveMailerName('admin@example.com', true));
    }

    public function test_it_defaults_empty_from_to_ses(): void
    {
        $this->assertSame('ses', $this->service->resolveMailerName(null));
        $this->assertSame('ses', $this->service->resolveMailerName(''));
    }
}
