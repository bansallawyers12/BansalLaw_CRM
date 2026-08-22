<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmptyUniqueGroupIdMassCompleteTest extends TestCase
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
    public function empty_unique_group_id_completes_only_target_note_in_assignee_controller(): void
    {
        $admin = Staff::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin.group@example.com',
            'password' => bcrypt('password'),
            'role' => 1,
        ]);

        $note1 = Note::create([
            'description' => 'Task 1',
            'unique_group_id' => '',
            'status' => 0,
            'assigned_to' => $admin->id,
            'is_action' => 1,
        ]);

        $note2 = Note::create([
            'description' => 'Task 2',
            'unique_group_id' => '',
            'status' => 0,
            'assigned_to' => $admin->id,
            'is_action' => 1,
        ]);

        $this->actingAs($admin, 'admin');

        // Send completion for note1 with empty unique_group_id
        $response = $this->post('/tasks/complete', [
            'id' => $note1->id,
            'unique_group_id' => '',
        ]);

        $this->assertEquals(1, (int) $note1->fresh()->status);
        $this->assertEquals(0, (int) $note2->fresh()->status);
    }

    #[Test]
    public function empty_unique_group_id_completes_only_target_note_in_dashboard_service(): void
    {
        $admin = Staff::create([
            'first_name' => 'Admin2',
            'last_name' => 'User2',
            'email' => 'admin.group2@example.com',
            'password' => bcrypt('password'),
            'role' => 1,
        ]);

        $note1 = Note::create([
            'description' => 'Task 3',
            'unique_group_id' => null,
            'status' => 0,
            'assigned_to' => $admin->id,
            'is_action' => 1,
        ]);

        $note2 = Note::create([
            'description' => 'Task 4',
            'unique_group_id' => null,
            'status' => 0,
            'assigned_to' => $admin->id,
            'is_action' => 1,
        ]);

        $this->actingAs($admin, 'admin');

        $service = app(\App\Services\DashboardService::class);
        $result = $service->completeTask($note1->id, '', null, $admin);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $note1->fresh()->status);
        $this->assertEquals(0, $note2->fresh()->status);
    }
}
