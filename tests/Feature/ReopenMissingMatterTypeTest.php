<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\Notification;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReopenMissingMatterTypeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Auth::guard('admin')->logout();

        \Illuminate\Support\Facades\DB::table('user_roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'Admin', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    #[Test]
    public function request_reopen_succeeds_when_matter_type_and_matter_no_are_null()
    {
        $client = Admin::factory()->create(['type' => 'client', 'is_archived' => 0]);
        $staff = Staff::factory()->create(['role' => 1]); // Staff with access

        // Discontinued matter with null sel_matter_id and null client_unique_matter_no
        $clientMatter = ClientMatter::create([
            'client_id' => $client->id,
            'sel_matter_id' => null,
            'client_unique_matter_no' => null,
            'matter_status' => 0, // Discontinued
            'discontinue_reason' => 'Completed',
        ]);

        // Create an admin to receive notifications
        $adminUser = Admin::factory()->create(['role' => 1, 'type' => 'admin']);

        $response = $this->actingAs($staff, 'admin')
            ->postJson('/clients/matter/request-reopen', [
                'matter_id' => $clientMatter->id,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => true, 'message' => 'Reopen request has been sent to admins.']);

        $clientMatter->refresh();
        $this->assertEquals($staff->id, $clientMatter->reopen_requested_by);

        // Verify notification created for admin with fallback title 'Matter'
        $notification = Notification::where('module_id', $clientMatter->id)
            ->where('notification_type', 'Matter Reopen Request')
            ->first();

        $this->assertNotNull($notification);
        $this->assertStringContainsString('requested to reopen Matter', $notification->message);
    }

    #[Test]
    public function reopen_client_matter_succeeds_when_matter_type_is_null()
    {
        $client = Admin::factory()->create(['type' => 'client', 'is_archived' => 0]);
        
        // Superadmin staff
        $superAdmin = Staff::factory()->create(['role' => 1]);

        $clientMatter = ClientMatter::create([
            'client_id' => $client->id,
            'sel_matter_id' => null,
            'client_unique_matter_no' => null,
            'matter_status' => 0, // Discontinued
            'reopen_requested_by' => 999,
        ]);

        $response = $this->actingAs($superAdmin, 'admin')
            ->postJson('/clients/matter/reopen', [
                'matter_id' => $clientMatter->id,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => true, 'message' => 'Matter has been successfully reopened.']);

        $clientMatter->refresh();
        $this->assertEquals(1, $clientMatter->matter_status);
        $this->assertNull($clientMatter->reopen_requested_by);
    }

    #[Test]
    public function closed_matters_list_includes_matters_with_null_matter_type()
    {
        $client = Admin::factory()->create(['type' => 'client', 'is_archived' => 0]);
        $staff = Staff::factory()->create(['role' => 1]);

        $clientMatter = ClientMatter::create([
            'client_id' => $client->id,
            'sel_matter_id' => null,
            'client_unique_matter_no' => 'MATTER_NULL_TYPE',
            'matter_status' => 0, // Discontinued
        ]);

        $response = $this->actingAs($staff, 'admin')
            ->get(route('clients.closedmatterslist'));

        $response->assertStatus(200);
        $response->assertSee('MATTER_NULL_TYPE');
    }
}
