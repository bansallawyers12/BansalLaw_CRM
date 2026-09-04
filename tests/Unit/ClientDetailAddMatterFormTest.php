<?php

namespace Tests\Unit;

use App\Models\Admin;
use App\Services\ClientDetailService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientDetailAddMatterFormTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function build_view_payload_includes_add_matter_form_for_converted_client(): void
    {
        $client = Admin::create([
            'first_name' => 'Company',
            'last_name' => 'Client',
            'email' => 'add_matter_client_' . uniqid('', true) . '@example.com',
            'password' => bcrypt('password123'),
            'type' => 'client',
            'lead_status' => 'converted',
            'is_company' => 1,
            'status' => 1,
            'client_id' => 'TEST' . substr(uniqid(), -6),
        ]);

        $payload = app(ClientDetailService::class)->buildViewPayload(
            (int) $client->id,
            null,
            'personaldetails',
            $client
        );

        $this->assertIsArray($payload['matterFormForLead'] ?? null);
        $this->assertArrayHasKey('mattersForAdd', $payload['matterFormForLead']);
        $this->assertFalse($payload['__crmEditLeadType']);
    }

    #[Test]
    public function build_view_payload_includes_add_matter_form_for_lead(): void
    {
        $lead = Admin::create([
            'first_name' => 'Lead',
            'last_name' => 'Person',
            'email' => 'add_matter_lead_' . uniqid('', true) . '@example.com',
            'password' => bcrypt('password123'),
            'type' => 'lead',
            'status' => 1,
            'client_id' => 'LEAD' . substr(uniqid(), -6),
        ]);

        $payload = app(ClientDetailService::class)->buildViewPayload(
            (int) $lead->id,
            null,
            'overview',
            $lead
        );

        $this->assertIsArray($payload['matterFormForLead'] ?? null);
        $this->assertArrayHasKey('mattersForAdd', $payload['matterFormForLead']);
        $this->assertTrue($payload['__crmEditLeadType']);
    }

    #[Test]
    public function build_view_payload_skips_add_matter_form_on_non_overview_tabs(): void
    {
        $client = Admin::create([
            'first_name' => 'Other',
            'last_name' => 'Tab',
            'email' => 'add_matter_tab_' . uniqid('', true) . '@example.com',
            'password' => bcrypt('password123'),
            'type' => 'client',
            'status' => 1,
            'client_id' => 'TAB' . substr(uniqid(), -6),
        ]);

        $payload = app(ClientDetailService::class)->buildViewPayload(
            (int) $client->id,
            null,
            'activityfeed',
            $client
        );

        $this->assertNull($payload['matterFormForLead'] ?? null);
    }
}
