<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\StaffCalendarEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StaffCalendarReminderStatusTest extends TestCase
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

    #[Test]
    public function staff_can_update_reminder_status(): void
    {
        $staff = Staff::factory()->superAdmin()->create([
            'status' => 1,
            'can_access_personal_calendar' => true,
        ]);
        $this->actingAs($staff, 'admin');

        $event = StaffCalendarEvent::create([
            'title' => 'Follow up call',
            'event_type' => 'reminder',
            'status' => 'scheduled',
            'starts_at' => '2026-09-12 09:00:00',
            'ends_at' => '2026-09-12 09:30:00',
            'is_all_day' => true,
            'created_by_staff_id' => $staff->id,
        ]);

        $response = $this->putJson(route('booking.api.calendar-events.update', $event->id), [
            'status' => 'completed',
        ]);

        $response->assertOk()->assertJson([
            'success' => true,
            'data' => [
                'status' => 'completed',
                'status_label' => 'Completed',
            ],
        ]);

        $this->assertDatabaseHas('staff_calendar_events', [
            'id' => $event->id,
            'status' => 'completed',
        ]);
    }

    #[Test]
    public function completed_and_cancelled_reminders_are_hidden_from_personal_calendar_feed(): void
    {
        $staff = Staff::factory()->superAdmin()->create([
            'status' => 1,
            'can_access_personal_calendar' => true,
        ]);
        $this->actingAs($staff, 'admin');

        $active = StaffCalendarEvent::create([
            'title' => 'Active reminder',
            'event_type' => 'reminder',
            'status' => 'scheduled',
            'starts_at' => '2026-09-12 09:00:00',
            'ends_at' => '2026-09-12 09:30:00',
            'is_all_day' => true,
            'created_by_staff_id' => $staff->id,
        ]);
        $completed = StaffCalendarEvent::create([
            'title' => 'Done reminder',
            'event_type' => 'reminder',
            'status' => 'completed',
            'starts_at' => '2026-09-12 10:00:00',
            'ends_at' => '2026-09-12 10:30:00',
            'is_all_day' => true,
            'created_by_staff_id' => $staff->id,
        ]);
        $cancelled = StaffCalendarEvent::create([
            'title' => 'Cancelled reminder',
            'event_type' => 'reminder',
            'status' => 'cancelled',
            'starts_at' => '2026-09-12 11:00:00',
            'ends_at' => '2026-09-12 11:30:00',
            'is_all_day' => true,
            'created_by_staff_id' => $staff->id,
        ]);

        $response = $this->getJson(route('dashboard.calendar-events', [
            'start' => '2026-09-01T00:00:00+10:00',
            'end' => '2026-09-30T23:59:59+10:00',
        ]));

        $response->assertOk()->assertJson(['success' => true]);
        $titles = collect($response->json('data') ?? [])->pluck('title')->all();

        $this->assertContains('Active reminder', $titles);
        $this->assertNotContains('Done reminder', $titles);
        $this->assertNotContains('Cancelled reminder', $titles);
        $this->assertTrue($active->exists());
        $this->assertTrue($completed->exists());
        $this->assertTrue($cancelled->exists());
    }
}
