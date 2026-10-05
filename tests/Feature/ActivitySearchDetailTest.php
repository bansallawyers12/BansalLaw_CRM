<?php

namespace Tests\Feature;

use App\Models\ActivitiesLog;
use App\Models\Admin;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActivitySearchDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Auth::guard('admin')->logout();

        \Illuminate\Support\Facades\DB::table('user_roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'Admin', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Staff', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function createSuperAdmin(): Staff
    {
        return Staff::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'superadmin_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => 1,
            'status' => 1,
        ]);
    }

    private function createRegularStaff(): Staff
    {
        return Staff::create([
            'first_name' => 'Regular',
            'last_name' => 'Staff',
            'email' => 'staff_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => 2,
            'status' => 1,
        ]);
    }

    #[Test]
    public function unauthenticated_user_cannot_access_activity_detail(): void
    {
        $response = $this->getJson('/adminconsole/system/activity-search/detail/1');
        $response->assertUnauthorized();
    }

    #[Test]
    public function non_superadmin_cannot_access_activity_detail(): void
    {
        $regularStaff = $this->createRegularStaff();

        $response = $this->actingAs($regularStaff, 'admin')
            ->getJson('/adminconsole/system/activity-search/detail/1');

        $response->assertStatus(403)
            ->assertJson([
                'status' => false,
            ]);
    }

    #[Test]
    public function superadmin_can_retrieve_activity_detail_via_adminconsole_route(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $client = Admin::create([
            'first_name' => 'Jane',
            'last_name' => 'Client',
            'email' => 'jane_' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'type' => 'client',
            'status' => 1,
        ]);

        $activity = ActivitiesLog::create([
            'client_id' => $client->id,
            'created_by' => $superAdmin->id,
            'activity_type' => 'note',
            'subject' => 'Consultation Note',
            'description' => 'Detailed consultation discussion regarding visa pathway.',
            'task_group' => 'Review',
            'task_status' => 0,
        ]);

        $response = $this->actingAs($superAdmin, 'admin')
            ->getJson('/adminconsole/system/activity-search/detail/' . $activity->id);

        $response->assertOk()
            ->assertJson([
                'status' => true,
                'data' => [
                    'id' => $activity->id,
                    'subject' => 'Consultation Note',
                    'activity_type' => 'note',
                    'task_group' => 'Review',
                    'task_status' => 0,
                    'client_id' => $client->id,
                ],
            ]);
    }

    #[Test]
    public function superadmin_can_retrieve_activity_detail_via_crm_alias_route(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $activity = ActivitiesLog::create([
            'created_by' => $superAdmin->id,
            'activity_type' => 'activity',
            'subject' => 'System Audit Log',
            'description' => 'Automated activity record created.',
        ]);

        $response = $this->actingAs($superAdmin, 'admin')
            ->getJson('/crm/activities?id=' . $activity->id);

        $response->assertOk()
            ->assertJson([
                'status' => true,
                'data' => [
                    'id' => $activity->id,
                    'subject' => 'System Audit Log',
                ],
            ]);
    }

    #[Test]
    public function returns_404_when_activity_not_found(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this->actingAs($superAdmin, 'admin')
            ->getJson('/adminconsole/system/activity-search/detail/999999');

        $response->assertStatus(404)
            ->assertJson([
                'status' => false,
                'message' => 'Activity not found.',
            ]);
    }
}
