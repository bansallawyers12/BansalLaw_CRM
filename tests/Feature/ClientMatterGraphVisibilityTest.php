<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\Staff;
use App\Models\StaffClientVisibility;
use App\Services\SignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ClientMatterGraphVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\DB::table('user_roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'Admin', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Staff', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    #[Test]
    public function get_client_matters_blocks_unauthorized_staff()
    {
        $client = Admin::factory()->create(['type' => 'client', 'is_archived' => 0]);
        $unauthorizedStaff = Staff::factory()->create(['role' => 2]); // Non-admin staff, no visibility assignment

        ClientMatter::create([
            'client_id' => $client->id,
            'matter_status' => 1,
            'client_unique_matter_no' => 'MATTER-100',
        ]);

        $response = $this->actingAs($unauthorizedStaff, 'admin')
            ->get(route('clients.getClientMatters', ['clientId' => $client->id]));

        $response->assertStatus(403);
    }

    #[Test]
    public function get_client_matters_allows_authorized_staff()
    {
        $authorizedStaff = Staff::factory()->create(['role' => 2]);
        $client = Admin::factory()->create(['type' => 'client', 'user_id' => $authorizedStaff->id, 'is_archived' => 0]);

        ClientMatter::create([
            'client_id' => $client->id,
            'matter_status' => 1,
            'client_unique_matter_no' => 'MATTER-200',
        ]);

        $response = $this->actingAs($authorizedStaff, 'admin')
            ->get(route('clients.getClientMatters', ['clientId' => $client->id]));

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertCount(1, $response->json('matters'));
    }

    #[Test]
    public function signature_service_suggest_association_filters_unauthorized_entities()
    {
        $unauthorizedStaff = Staff::factory()->create(['role' => 2]);
        $client = Admin::factory()->create([
            'type' => 'client',
            'email' => 'privateclient@example.com',
            'is_archived' => 0,
        ]);

        $this->actingAs($unauthorizedStaff, 'admin');

        $service = app(SignatureService::class);
        $result = $service->suggestAssociation('privateclient@example.com');

        $this->assertNull($result);
    }
}
