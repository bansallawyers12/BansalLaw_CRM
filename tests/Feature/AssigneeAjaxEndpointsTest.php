<?php

namespace Tests\Feature;

use App\Models\ActivitiesLog;
use App\Models\Admin;
use App\Models\Note;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssigneeAjaxEndpointsTest extends TestCase
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

    #[Test]
    public function routes_are_named_and_registered_properly(): void
    {
        $this->assertSame(url('/update_list_status'), route('assignee.update_status'));
        $this->assertSame(url('/update_list_priority'), route('assignee.update_priority'));
        $this->assertSame(url('/update_apppointment_comment'), route('assignee.add_comment'));
        $this->assertSame(url('/update_apppointment_description'), route('assignee.update_description'));
        $this->assertSame(url('/get-assigne-detail'), route('assignee.get_detail'));
        $this->assertSame(url('/change_assignee'), route('assignee.change_assignee'));
    }

    #[Test]
    public function endpoints_require_authentication(): void
    {
        $this->postJson('/update_list_status')->assertStatus(401);
        $this->postJson('/update_list_priority')->assertStatus(401);
        $this->postJson('/update_apppointment_comment')->assertStatus(401);
        $this->postJson('/update_apppointment_description')->assertStatus(401);
        $this->getJson('/get-assigne-detail')->assertStatus(401);
        $this->getJson('/change_assignee')->assertStatus(401);
    }

    #[Test]
    public function update_status_validates_and_updates_task_status(): void
    {
        $staff = Staff::factory()->create(['role' => 1]);
        $this->actingAs($staff, 'admin');

        // Missing ID
        $this->postJson('/update_list_status', [])
            ->assertStatus(400)
            ->assertJson(['status' => false]);

        // Non-existent ID
        $this->postJson('/update_list_status', ['id' => 999999])
            ->assertStatus(404)
            ->assertJson(['status' => false]);

        $task = Note::create([
            'user_id' => $staff->id,
            'assigned_to' => $staff->id,
            'is_action' => 1,
            'type' => 'client',
            'status' => '0',
            'description' => 'Original Task Description',
            'task_group' => 'Review',
            'pin' => 0,
        ]);

        // Update to Completed (1)
        $response = $this->postJson('/update_list_status', [
            'id' => $task->id,
            'status' => '1',
            'statusname' => 'Completed',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'success' => true,
                'message' => 'Status updated successfully',
            ]);
        $this->assertStringContainsString('Completed', $response->json('viewstatus'));
        $this->assertSame('1', (string) $task->fresh()->status);

        // Update to In-Progress (2)
        $response = $this->postJson('/update_list_status', [
            'id' => $task->id,
            'status' => '2',
            'statusname' => 'In-Progress',
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => true]);
        $this->assertStringContainsString('In-Progress', $response->json('viewstatus'));
        $this->assertSame('2', (string) $task->fresh()->status);
    }

    #[Test]
    public function update_priority_updates_task_group(): void
    {
        $staff = Staff::factory()->create(['role' => 1]);
        $this->actingAs($staff, 'admin');

        $task = Note::create([
            'user_id' => $staff->id,
            'assigned_to' => $staff->id,
            'is_action' => 1,
            'type' => 'client',
            'status' => '0',
            'task_group' => 'Review',
            'pin' => 0,
        ]);

        $response = $this->postJson('/update_list_priority', [
            'id' => $task->id,
            'status' => 'Urgent',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Priority updated successfully',
            ]);

        $this->assertSame('Urgent', $task->fresh()->task_group);
    }

    #[Test]
    public function add_comment_records_comment_in_activity_log(): void
    {
        $staff = Staff::factory()->create(['role' => 1]);
        $this->actingAs($staff, 'admin');

        $client = Admin::create([
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'email' => 'alice@example.com',
            'type' => 'client',
            'status' => 1,
            'client_id' => 'BLC77777',
        ]);

        $task = Note::create([
            'user_id' => $staff->id,
            'client_id' => $client->id,
            'assigned_to' => $staff->id,
            'is_action' => 1,
            'type' => 'client',
            'status' => '0',
            'description' => 'Followup call with Alice',
            'task_group' => 'Call',
            'pin' => 0,
        ]);

        $response = $this->postJson('/update_apppointment_comment', [
            'id' => $task->id,
            'visit_comment' => 'Called client, left a voicemail regarding documents.',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Comment saved successfully',
            ]);

        $this->assertDatabaseHas('activities_logs', [
            'client_id' => $client->id,
            'created_by' => $staff->id,
            'subject' => 'Task comment added',
            'activity_type' => 'comment',
        ]);
    }

    #[Test]
    public function update_description_updates_task_note_description(): void
    {
        $staff = Staff::factory()->create(['role' => 1]);
        $this->actingAs($staff, 'admin');

        $task = Note::create([
            'user_id' => $staff->id,
            'assigned_to' => $staff->id,
            'is_action' => 1,
            'type' => 'client',
            'status' => '0',
            'description' => 'Old description text',
            'task_group' => 'Checklist',
            'pin' => 0,
        ]);

        $response = $this->postJson('/update_apppointment_description', [
            'id' => $task->id,
            'visit_purpose' => 'Updated purpose: Review passport and PCC copies.',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Description updated successfully',
            ]);

        $this->assertSame('Updated purpose: Review passport and PCC copies.', $task->fresh()->description);
    }

    #[Test]
    public function get_detail_returns_rendered_html_and_json(): void
    {
        $staff = Staff::factory()->create(['role' => 1]);
        $this->actingAs($staff, 'admin');

        $client = Admin::create([
            'first_name' => 'Robert',
            'last_name' => 'Brown',
            'email' => 'robert@example.com',
            'type' => 'client',
            'status' => 1,
            'client_id' => 'BLC88888',
        ]);

        $task = Note::create([
            'user_id' => $staff->id,
            'client_id' => $client->id,
            'assigned_to' => $staff->id,
            'is_action' => 1,
            'type' => 'client',
            'status' => '0',
            'description' => 'Review visa conditions',
            'task_group' => 'Review',
            'action_date' => now()->toDateString(),
            'pin' => 0,
        ]);

        // HTML GET request (jQuery $.ajax without dataType)
        $htmlResponse = $this->get('/get-assigne-detail?id=' . $task->id);
        $htmlResponse->assertStatus(200);
        $this->assertStringContainsString('Task Details #' . $task->id, $htmlResponse->getContent());
        $this->assertStringContainsString('Robert Brown', $htmlResponse->getContent());
        $this->assertStringContainsString('BLC88888', $htmlResponse->getContent());

        // JSON GET request (wantsJson)
        $jsonResponse = $this->getJson('/get-assigne-detail?id=' . $task->id);
        $jsonResponse->assertStatus(200)
            ->assertJson([
                'status' => true,
                'task' => [
                    'id' => $task->id,
                ],
            ]);
        $this->assertStringContainsString('Task Details #' . $task->id, $jsonResponse->json('html'));
    }

    #[Test]
    public function change_assignee_root_endpoint_reassigns_task_note(): void
    {
        $staff = Staff::factory()->create(['role' => 1]);
        $newAssignee = Staff::factory()->create();
        $this->actingAs($staff, 'admin');

        $task = Note::create([
            'user_id' => $staff->id,
            'assigned_to' => $staff->id,
            'is_action' => 1,
            'type' => 'client',
            'status' => '0',
            'description' => 'Reassign task test',
            'task_group' => 'Review',
            'pin' => 0,
        ]);

        $response = $this->getJson('/change_assignee?id=' . $task->id . '&assinee=' . $newAssignee->id);
        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Assignee changed successfully',
            ]);

        $this->assertSame((int) $newAssignee->id, (int) $task->fresh()->assigned_to);
    }

    #[Test]
    public function unauthorized_staff_cannot_manage_unassigned_private_task(): void
    {
        $owner = Staff::factory()->create(['role' => 2]); // Non-admin staff
        $otherStaff = Staff::factory()->create(['role' => 2]); // Another non-admin staff

        $task = Note::create([
            'user_id' => $owner->id,
            'assigned_to' => $owner->id,
            'is_action' => 1,
            'type' => 'client',
            'status' => '0',
            'description' => 'Private Task',
            'task_group' => 'Personal Task',
            'pin' => 0,
        ]);

        $this->actingAs($otherStaff, 'admin');

        $this->postJson('/update_list_status', [
            'id' => $task->id,
            'status' => '1',
        ])->assertStatus(403);

        $this->postJson('/update_list_priority', [
            'id' => $task->id,
            'status' => 'Urgent',
        ])->assertStatus(403);

        $this->postJson('/update_apppointment_comment', [
            'id' => $task->id,
            'visit_comment' => 'Sneaky comment',
        ])->assertStatus(403);

        $this->postJson('/update_apppointment_description', [
            'id' => $task->id,
            'visit_purpose' => 'Sneaky description',
        ])->assertStatus(403);

        $this->getJson('/get-assigne-detail?id=' . $task->id)
            ->assertStatus(403);
    }
}
