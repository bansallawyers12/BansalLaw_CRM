<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\Staff;
use App\Models\StaffCalendarEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MatterTaskReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Auth::guard('admin')->logout();

        \Illuminate\Support\Facades\DB::table('user_roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'Admin', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 16, 'name' => 'Solicitor', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function seedClientMatter(): array
    {
        $client = Admin::factory()->create(['type' => 'client', 'is_archived' => 0]);
        $matter = ClientMatter::create([
            'client_id' => $client->id,
            'client_unique_matter_no' => 'CIV_1',
            'matter_status' => 1,
        ]);

        return [$client, $matter];
    }

    #[Test]
    public function staff_with_calendar_access_can_add_reminder_from_tasks_tab(): void
    {
        [$client, $matter] = $this->seedClientMatter();
        $staff = Staff::factory()->superAdmin()->create([
            'status' => 1,
            'can_access_personal_calendar' => true,
        ]);
        $this->actingAs($staff, 'admin');

        $response = $this->postJson(route('clients.matterTask.store'), [
            'client_id' => $client->id,
            'matter_id' => $matter->id,
            'title' => 'Call Vantage Legal',
            'due_date' => '2026-09-12',
            'kind' => 'reminder',
        ]);

        $response->assertOk()->assertJson([
            'status' => true,
            'data' => [
                'item_kind' => 'reminder',
                'title' => 'Call Vantage Legal',
            ],
        ]);

        $this->assertDatabaseHas('staff_calendar_events', [
            'title' => 'Call Vantage Legal',
            'event_type' => 'reminder',
            'client_id' => $client->id,
            'client_matter_id' => $matter->id,
            'created_by_staff_id' => $staff->id,
        ]);
    }

    #[Test]
    public function staff_without_calendar_access_cannot_add_reminder(): void
    {
        [$client, $matter] = $this->seedClientMatter();
        config(['crm_access.allocation_enforcement' => false]);

        $staff = Staff::factory()->create([
            'role' => 16,
            'status' => 1,
            'can_access_personal_calendar' => false,
            'email' => 'nocal.reminder@example.com',
        ]);
        $this->actingAs($staff, 'admin');

        $this->postJson(route('clients.matterTask.store'), [
            'client_id' => $client->id,
            'matter_id' => $matter->id,
            'title' => 'Blocked reminder',
            'due_date' => '2026-09-12',
            'kind' => 'reminder',
        ])->assertStatus(403);
    }

    #[Test]
    public function matter_tasks_index_includes_upcoming_reminders(): void
    {
        [$client, $matter] = $this->seedClientMatter();
        $staff = Staff::factory()->superAdmin()->create(['status' => 1]);
        $this->actingAs($staff, 'admin');

        StaffCalendarEvent::create([
            'title' => 'Matter reminder',
            'event_type' => 'reminder',
            'starts_at' => now()->addDays(2)->setTime(9, 0),
            'ends_at' => now()->addDays(2)->setTime(9, 30),
            'is_all_day' => true,
            'client_id' => $client->id,
            'client_matter_id' => $matter->id,
            'created_by_staff_id' => $staff->id,
        ]);

        $this->getJson(route('clients.matterTask.index', [
            'client_id' => $client->id,
            'matter_id' => $matter->id,
        ]))
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('reminders.0.title', 'Matter reminder')
            ->assertJsonPath('reminders.0.item_kind', 'reminder')
            ->assertJsonPath('reminders.0.is_done', false)
            ->assertJsonPath('reminders.0.status', 'scheduled');
    }

    #[Test]
    public function matter_tasks_index_marks_completed_reminders_as_done(): void
    {
        [$client, $matter] = $this->seedClientMatter();
        $staff = Staff::factory()->superAdmin()->create(['status' => 1]);
        $this->actingAs($staff, 'admin');

        StaffCalendarEvent::create([
            'title' => 'Finished reminder',
            'event_type' => 'reminder',
            'status' => 'completed',
            'starts_at' => now()->addDays(2)->setTime(9, 0),
            'ends_at' => now()->addDays(2)->setTime(9, 30),
            'is_all_day' => true,
            'client_id' => $client->id,
            'client_matter_id' => $matter->id,
            'created_by_staff_id' => $staff->id,
        ]);

        StaffCalendarEvent::create([
            'title' => 'Cancelled reminder',
            'event_type' => 'reminder',
            'status' => 'cancelled',
            'starts_at' => now()->addDays(3)->setTime(9, 0),
            'ends_at' => now()->addDays(3)->setTime(9, 30),
            'is_all_day' => true,
            'client_id' => $client->id,
            'client_matter_id' => $matter->id,
            'created_by_staff_id' => $staff->id,
        ]);

        $response = $this->getJson(route('clients.matterTask.index', [
            'client_id' => $client->id,
            'matter_id' => $matter->id,
        ]));

        $response->assertOk()->assertJsonPath('status', true);
        $reminders = collect($response->json('reminders') ?? []);
        $this->assertTrue($reminders->contains(fn (array $row) => ($row['title'] ?? '') === 'Finished reminder' && ($row['is_done'] ?? false) === true));
        $this->assertFalse($reminders->contains(fn (array $row) => ($row['title'] ?? '') === 'Cancelled reminder'));
    }
}
