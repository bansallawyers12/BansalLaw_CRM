<?php

namespace Tests\Unit;

use App\Models\StaffCalendarEvent;
use App\Services\CalendarSync\ZohoCalendarApiClient;
use App\Services\CalendarSync\ZohoCalendarOAuthService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ZohoCalendarEventPayloadTest extends TestCase
{
    #[Test]
    public function builds_eventdata_with_file_prefix_and_utc_times(): void
    {
        config(['zoho_calendar.timezone' => 'Australia/Melbourne']);
        config(['app.timezone' => 'Australia/Melbourne']);

        $event = new StaffCalendarEvent([
            'title' => 'Mention',
            'event_type' => 'court',
            'client_id' => 71,
            'client_matter_id' => null,
            'starts_at' => Carbon::parse('2026-08-05 10:00:00', 'Australia/Melbourne'),
            'ends_at' => Carbon::parse('2026-08-05 11:00:00', 'Australia/Melbourne'),
            'is_all_day' => false,
            'location' => 'FCA Melbourne',
            'notes' => 'Bring file',
        ]);

        $client = new ZohoCalendarApiClient(new ZohoCalendarOAuthService);
        $data = $client->buildEventDataFromStaffEvent($event);

        $this->assertSame('[71] Mention', $data['title']);
        $this->assertSame('Australia/Melbourne', $data['dateandtime']['timezone']);
        $this->assertMatchesRegularExpression('/^\d{8}T\d{6}Z$/', $data['dateandtime']['start']);
        $this->assertMatchesRegularExpression('/^\d{8}T\d{6}Z$/', $data['dateandtime']['end']);
        $this->assertFalse($data['isallday']);
        $this->assertStringContainsString('Bring file', $data['description']);
        $this->assertSame('FCA Melbourne', $data['location']);
    }

    #[Test]
    public function oauth_authorization_url_when_configured(): void
    {
        config([
            'zoho_calendar.client_id' => 'cid-test',
            'zoho_calendar.client_secret' => 'secret',
            'zoho_calendar.redirect_uri' => 'http://localhost/callback',
            'zoho_calendar.accounts_url' => 'https://accounts.zoho.com.au',
            'zoho_calendar.scopes' => 'ZohoCalendar.event.ALL',
        ]);

        $oauth = new ZohoCalendarOAuthService;
        $this->assertTrue($oauth->isConfigured());
        $url = $oauth->authorizationUrl('state123');
        $this->assertStringStartsWith('https://accounts.zoho.com.au/oauth/v2/auth?', $url);
        $this->assertStringContainsString('client_id=cid-test', $url);
        $this->assertStringContainsString('state=state123', $url);
        $this->assertStringContainsString('access_type=offline', $url);
    }
}
