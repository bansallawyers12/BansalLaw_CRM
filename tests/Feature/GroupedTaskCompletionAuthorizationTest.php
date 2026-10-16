<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\Staff;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GroupedTaskCompletionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Auth::guard('admin')->logout();

        \Illuminate\Support\Facades\DB::table('user_roles')->insertOrIgnore([
            ['id' => 14, 'name' => 'Agent', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    #[Test]
    public function assignee_can_complete_their_row_in_multi_assignee_group_without_client_allocation(): void
    {
        $assignee = Staff::create([
            'first_name' => 'Khushi',
            'last_name' => 'Test',
            'email' => 'khushi.group@example.com',
            'password' => bcrypt('password'),
            'role' => 14,
            'status' => 1,
        ]);

        $otherAssignee = Staff::create([
            'first_name' => 'Other',
            'last_name' => 'Assignee',
            'email' => 'other.group@example.com',
            'password' => bcrypt('password'),
            'role' => 14,
            'status' => 1,
        ]);

        $groupId = 'group_multi_assignee_test';

        $noteForAssignee = Note::create([
            'user_id' => $assignee->id,
            'assigned_to' => $assignee->id,
            'client_id' => 999001,
            'unique_group_id' => $groupId,
            'description' => 'Grouped task for assignee',
            'type' => 'client',
            'is_action' => 1,
            'status' => '0',
            'task_group' => 'Review',
        ]);

        Note::create([
            'user_id' => $assignee->id,
            'assigned_to' => $otherAssignee->id,
            'client_id' => 999001,
            'unique_group_id' => $groupId,
            'description' => 'Grouped task for other',
            'type' => 'client',
            'is_action' => 1,
            'status' => '0',
            'task_group' => 'Review',
        ]);

        $this->actingAs($assignee, 'admin');

        $response = $this->postJson('/tasks/complete', [
            'id' => $noteForAssignee->id,
            'unique_group_id' => $groupId,
            'completion_notes' => 'Task Complete',
        ]);

        $response->assertOk()
            ->assertJson(['success' => true, 'status' => true]);

        $this->assertSame('1', (string) $noteForAssignee->fresh()->status);
    }

}
