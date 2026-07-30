<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\Staff;
use App\Models\StaffLoginLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class Area7SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Auth::guard('admin')->logout();
    }

    #[Test]
    public function regular_staff_cannot_delete_another_staff_members_action_task(): void
    {
        $staff1 = new Staff();
        $staff1->id = 101;
        $staff1->role = 2; // Regular Staff

        $this->actingAs($staff1, 'admin');

        $note = new Note();
        $note->id = 999;
        $note->user_id = 102; // Created by staff 102
        $note->assigned_to = 102; // Assigned to staff 102
        $note->is_action = 1;
        $note->save();

        $response = $this->delete('/assignee/999');

        $response->assertStatus(403);
    }

    #[Test]
    public function regular_staff_cannot_broadcast_to_all_staff(): void
    {
        $staff = new Staff();
        $staff->id = 103;
        $staff->role = 2; // Regular Staff

        $this->actingAs($staff, 'admin');

        $response = $this->postJson('/notifications/broadcasts/send', [
            'message' => 'Test broadcast',
            'scope' => 'all',
        ]);

        $response->assertStatus(403);
        $this->assertStringContainsString('Unauthorized access', $response->json('message'));
    }

    #[Test]
    public function regular_staff_cannot_view_audit_logs(): void
    {
        $staff = new Staff();
        $staff->id = 104;
        $staff->role = 2; // Regular Staff

        $this->actingAs($staff, 'admin');

        $response = $this->get('/audit-logs');

        $response->assertStatus(403);
    }
}
