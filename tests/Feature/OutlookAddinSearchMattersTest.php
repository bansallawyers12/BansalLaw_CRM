<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ClientMatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OutlookAddinSearchMattersTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function search_finds_client_by_client_id_even_when_status_is_not_active(): void
    {
        $client = Admin::factory()->create([
            'type' => 'client',
            'client_id' => 'AJAY2600001',
            'status' => 0,
            'is_archived' => 0,
        ]);

        ClientMatter::create([
            'client_id' => $client->id,
            'client_unique_matter_no' => 'FAM_1',
            'matter_status' => 1,
        ]);

        $response = $this->getJson(route('outlook-addin.matters', ['q' => 'AJAY2600001']));

        $response->assertOk()
            ->assertJsonPath('success', true);

        $matters = $response->json('matters');
        $this->assertNotEmpty($matters);
        $this->assertSame('AJAY2600001', $matters[0]['client_ref']);
        $this->assertSame('FAM_1', $matters[0]['matter_no']);
    }

    #[Test]
    public function match_by_sender_email_assigns_open_matter_for_client(): void
    {
        $client = Admin::factory()->create([
            'type' => 'client',
            'email' => 'clientmatter@example.com',
            'client_id' => 'SEND2600001',
            'status' => 0,
            'is_archived' => 0,
        ]);

        ClientMatter::create([
            'client_id' => $client->id,
            'client_unique_matter_no' => 'CIV_2',
            'matter_status' => 1,
        ]);

        $response = $this->postJson(route('outlook-addin.match'), [
            'subject' => 'Test regarding',
            'from_email' => 'clientmatter@example.com',
            'from_name' => 'Test Sender',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('matched', true)
            ->assertJsonPath('best.client_ref', 'SEND2600001')
            ->assertJsonPath('best.matter_no', 'CIV_2');
    }

    #[Test]
    public function search_includes_lead_even_without_matter(): void
    {
        Admin::factory()->create([
            'type' => 'lead',
            'client_id' => 'LEAD2600099',
            'first_name' => 'Lead',
            'last_name' => 'Person',
            'status' => 1,
            'is_archived' => 0,
        ]);

        $response = $this->getJson(route('outlook-addin.matters', ['q' => 'LEAD2600099']));

        $response->assertOk()->assertJsonPath('success', true);

        $matters = $response->json('matters');
        $this->assertNotEmpty($matters);
        $this->assertSame('LEAD2600099', $matters[0]['client_ref']);
        $this->assertSame('lead', $matters[0]['record_type']);
        $this->assertSame(0, (int) $matters[0]['client_matter_id']);
    }

    #[Test]
    public function search_includes_lead_row_even_when_lead_has_open_matter(): void
    {
        $lead = Admin::factory()->create([
            'type' => 'lead',
            'client_id' => 'LEAD2600100',
            'status' => 1,
            'is_archived' => 0,
        ]);

        ClientMatter::create([
            'client_id' => $lead->id,
            'client_unique_matter_no' => 'FAM_9',
            'matter_status' => 1,
        ]);

        $response = $this->getJson(route('outlook-addin.matters', ['q' => 'LEAD2600100']));

        $response->assertOk()->assertJsonPath('success', true);

        $matters = $response->json('matters');
        $leadRows = array_values(array_filter(
            $matters,
            fn (array $row) => ($row['record_type'] ?? '') === 'lead' && (int) ($row['client_matter_id'] ?? 0) === 0
        ));
        $matterRows = array_values(array_filter(
            $matters,
            fn (array $row) => (int) ($row['client_matter_id'] ?? 0) > 0
        ));

        $this->assertNotEmpty($leadRows);
        $this->assertSame('LEAD2600100', $leadRows[0]['client_ref']);
        $this->assertNotEmpty($matterRows);
        $this->assertSame('FAM_9', $matterRows[0]['matter_no']);
    }

    #[Test]
    public function match_by_sender_email_assigns_lead_scoped_target(): void
    {
        Admin::factory()->create([
            'type' => 'lead',
            'email' => 'leadonly@example.com',
            'client_id' => 'LEAD2600101',
            'status' => 1,
            'is_archived' => 0,
        ]);

        $response = $this->postJson(route('outlook-addin.match'), [
            'subject' => 'Enquiry',
            'from_email' => 'leadonly@example.com',
            'from_name' => 'Lead Sender',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('matched', true)
            ->assertJsonPath('best.client_ref', 'LEAD2600101')
            ->assertJsonPath('best.record_type', 'lead')
            ->assertJsonPath('best.client_matter_id', 0);
    }

    #[Test]
    public function search_includes_client_without_any_matter(): void
    {
        Admin::factory()->create([
            'type' => 'client',
            'client_id' => 'NOMT2600001',
            'first_name' => 'NoMatter',
            'last_name' => 'Client',
            'status' => 1,
            'is_archived' => 0,
        ]);

        $response = $this->getJson(route('outlook-addin.matters', ['q' => 'NOMT2600001']));

        $response->assertOk()->assertJsonPath('success', true);

        $matters = $response->json('matters');
        $this->assertNotEmpty($matters);
        $this->assertSame('NOMT2600001', $matters[0]['client_ref']);
        $this->assertSame('client', $matters[0]['record_type']);
        $this->assertSame(0, (int) $matters[0]['client_matter_id']);
    }

    #[Test]
    public function match_by_client_ref_assigns_client_without_matter(): void
    {
        Admin::factory()->create([
            'type' => 'client',
            'client_id' => 'NOMT2600002',
            'status' => 1,
            'is_archived' => 0,
        ]);

        $response = $this->postJson(route('outlook-addin.match'), [
            'subject' => 'Regarding NOMT2600002',
            'from_email' => 'someone@example.com',
            'from_name' => 'Someone',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('matched', true)
            ->assertJsonPath('best.client_ref', 'NOMT2600002')
            ->assertJsonPath('best.record_type', 'client')
            ->assertJsonPath('best.client_matter_id', 0);
    }
}
