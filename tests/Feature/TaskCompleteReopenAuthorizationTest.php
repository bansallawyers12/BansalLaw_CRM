<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TaskCompleteReopenAuthorizationTest extends TestCase
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
    public function unassigned_and_non_creator_staff_cannot_complete_or_reopen_task(): void
    {
        $creator = Staff::create([
            'first_name' => 'Creator',
            'last_name' => 'User',
            'email' => 'creator@example.com',
            'password' => bcrypt('password'),
            'role' => 2,
        ]);

        $assignee = Staff::create([
            'first_name' => 'Assignee',
            'last_name' => 'User',
            'email' => 'assignee@example.com',
            'password' => bcrypt('password'),
            'role' => 2,
        ]);

        $otherStaff = Staff::create([
            'first_name' => 'Other',
            'last_name' => 'Staff',
            'email' => 'other@example.com',
            'password' => bcrypt('password'),
            'role' => 2,
        ]);

        $note = Note::create([
            'description' => 'Target task',
            'user_id' => $creator->id,
            'assigned_to' => $assignee->id,
            'status' => 0,
            'is_action' => 1,
        ]);

        $this->actingAs($otherStaff, 'admin');
        $response = $this->post('/tasks/complete', ['id' => $note->id]);
        $response->assertStatus(403);

        $response = $this->post('/tasks/reopen', ['id' => $note->id]);
        $response->assertStatus(403);
    }

    #[Test]
    public function assignee_or_creator_can_complete_and_reopen_task(): void
    {
        $creator = Staff::create([
            'first_name' => 'Creator2',
            'last_name' => 'User2',
            'email' => 'creator2@example.com',
            'password' => bcrypt('password'),
            'role' => 2,
        ]);

        $assignee = Staff::create([
            'first_name' => 'Assignee2',
            'last_name' => 'User2',
            'email' => 'assignee2@example.com',
            'password' => bcrypt('password'),
            'role' => 2,
        ]);

        $note = Note::create([
            'description' => 'Target task 2',
            'user_id' => $creator->id,
            'assigned_to' => $assignee->id,
            'status' => 0,
            'is_action' => 1,
        ]);

        $this->actingAs($assignee, 'admin');
        $response = $this->post('/tasks/complete', ['id' => $note->id]);
        $response->assertStatus(200);
        $this->assertEquals(1, (int) $note->fresh()->status);

        $this->actingAs($creator, 'admin');
        $response = $this->post('/tasks/reopen', ['id' => $note->id]);
        $response->assertStatus(200);
        $this->assertEquals(0, (int) $note->fresh()->status);
    }
}
