<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ClientContact;
use App\Models\ClientEmail;
use App\Models\ClientMatter;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GlobalClientSearchTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function global_search_returns_only_matching_clients_by_name(): void
    {
        $staff = Staff::factory()->superAdmin()->create();
        $this->actingAs($staff, 'admin');

        $match = Admin::factory()->create([
            'first_name' => 'Samrina',
            'last_name' => 'Kaur',
            'email' => 'samrina.kaur@example.com',
            'client_id' => 'SAMR2600001',
            'type' => 'client',
            'is_archived' => 0,
        ]);

        Admin::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'Geeta',
            'email' => 'testgeetanew09@gmail.com',
            'client_id' => 'TEST2600026',
            'type' => 'client',
            'is_archived' => 0,
        ]);

        $response = $this->getJson('/clients/get-allclients?q=samri');

        $response->assertOk();
        $cids = collect($response->json('items'))->pluck('cid')->map(fn ($id) => (int) $id)->all();

        $this->assertContains($match->id, $cids);
        $this->assertNotContains(
            Admin::query()->where('client_id', 'TEST2600026')->value('id'),
            $cids
        );
    }

    #[Test]
    public function global_search_finds_client_by_email_phone_and_client_id(): void
    {
        $staff = Staff::factory()->superAdmin()->create();
        $this->actingAs($staff, 'admin');

        $client = Admin::factory()->create([
            'first_name' => 'Alpha',
            'last_name' => 'Beta',
            'email' => 'alpha.primary@example.com',
            'phone' => '0399991111',
            'client_id' => 'ALPH2600099',
            'type' => 'client',
            'is_archived' => 0,
        ]);

        ClientEmail::query()->create([
            'client_id' => $client->id,
            'email' => 'alpha.secondary@example.com',
            'email_type' => 1,
        ]);

        ClientContact::query()->create([
            'client_id' => $client->id,
            'phone' => '0412345678',
            'country_code' => '+61',
            'contact_type' => 1,
        ]);

        Admin::factory()->create([
            'first_name' => 'Other',
            'last_name' => 'Person',
            'email' => 'other.person@example.com',
            'client_id' => 'OTHR2600001',
            'type' => 'client',
            'is_archived' => 0,
        ]);

        foreach (['alpha.secondary', '0412345678', 'ALPH2600099'] as $term) {
            $response = $this->getJson('/clients/get-allclients?q=' . urlencode($term));
            $response->assertOk();
            $cids = collect($response->json('items'))->pluck('cid')->map(fn ($id) => (int) $id)->all();
            $this->assertContains($client->id, $cids, "Expected client when searching for [{$term}]");
        }
    }

    #[Test]
    public function global_search_finds_client_by_matter_reference(): void
    {
        $staff = Staff::factory()->superAdmin()->create();
        $this->actingAs($staff, 'admin');

        $client = Admin::factory()->create([
            'first_name' => 'Matter',
            'last_name' => 'Owner',
            'email' => 'matter.owner@example.com',
            'client_id' => 'MATT2600001',
            'type' => 'client',
            'is_archived' => 0,
        ]);

        ClientMatter::query()->create([
            'client_id' => $client->id,
            'client_unique_matter_no' => 'CIV_42',
            'matter_status' => 1,
            'workflow_stage_id' => 1,
        ]);

        $response = $this->getJson('/clients/get-allclients?q=CIV_42');

        $response->assertOk();
        $cids = collect($response->json('items'))->pluck('cid')->map(fn ($id) => (int) $id)->all();
        $this->assertContains($client->id, $cids);
    }

    #[Test]
    public function global_search_finds_client_by_full_name(): void
    {
        $staff = Staff::factory()->superAdmin()->create();
        $this->actingAs($staff, 'admin');

        $match = Admin::factory()->create([
            'first_name' => 'Gurdeep',
            'last_name' => 'Singh',
            'email' => 'gurdeep.singh@example.com',
            'client_id' => 'GURD2600999',
            'type' => 'client',
            'is_archived' => 0,
        ]);

        Admin::factory()->create([
            'first_name' => 'Jugraj',
            'last_name' => 'Singh',
            'email' => 'jugraj.singh@example.com',
            'client_id' => 'JUGR2600015',
            'type' => 'client',
            'is_archived' => 0,
        ]);

        $response = $this->getJson('/clients/get-allclients?q=gur');

        $response->assertOk();
        $cids = collect($response->json('items'))->pluck('cid')->map(fn ($id) => (int) $id)->all();

        $this->assertContains($match->id, $cids);
        $this->assertNotContains(
            Admin::query()->where('client_id', 'JUGR2600015')->value('id'),
            $cids
        );
        $cacheControl = strtolower((string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
    }
}
