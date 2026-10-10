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
}
