<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\Matter;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ListAllMattersWRTSelClientTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_only_active_matters_for_the_selected_client(): void
    {
        $staff = Staff::factory()->superAdmin()->create(['status' => 1]);
        $clientA = Admin::factory()->create(['type' => 'client', 'is_archived' => 0, 'client_id' => 'CLI_A']);
        $clientB = Admin::factory()->create(['type' => 'client', 'is_archived' => 0, 'client_id' => 'CLI_B']);
        $matterType = Matter::query()->create(['title' => 'Civil', 'status' => 1]);

        $matterA = ClientMatter::query()->create([
            'client_id' => $clientA->id,
            'sel_matter_id' => $matterType->id,
            'client_unique_matter_no' => 'CIV_A1',
            'matter_status' => 1,
        ]);

        ClientMatter::query()->create([
            'client_id' => $clientB->id,
            'sel_matter_id' => $matterType->id,
            'client_unique_matter_no' => 'CIV_B1',
            'matter_status' => 1,
        ]);

        ClientMatter::query()->create([
            'client_id' => $clientA->id,
            'sel_matter_id' => $matterType->id,
            'client_unique_matter_no' => 'CIV_A_CLOSED',
            'matter_status' => 0,
        ]);

        $this->actingAs($staff, 'admin')
            ->postJson(route('clients.listAllMattersWRTSelClient'), [
                'client_id' => $clientA->id,
            ])
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonCount(1, 'clientMatetrs')
            ->assertJsonPath('clientMatetrs.0.id', $matterA->id);

        $this->actingAs($staff, 'admin')
            ->postJson(route('clients.listAllMattersWRTSelClient'), [
                'client_id' => 'CLI_A',
            ])
            ->assertOk()
            ->assertJsonCount(1, 'clientMatetrs');
    }

    #[Test]
    public function it_returns_empty_when_client_has_no_active_matters(): void
    {
        $staff = Staff::factory()->superAdmin()->create(['status' => 1]);
        $client = Admin::factory()->create(['type' => 'lead', 'is_archived' => 0]);

        $this->actingAs($staff, 'admin')
            ->postJson(route('clients.listAllMattersWRTSelClient'), [
                'client_id' => $client->id,
            ])
            ->assertOk()
            ->assertJsonPath('status', false)
            ->assertJsonPath('clientMatetrs', []);
    }
}
