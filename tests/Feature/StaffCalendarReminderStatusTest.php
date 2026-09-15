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
    public function pending_reminders_include_upcoming_within_reminder_window(): void
    {
        $staff = Staff::factory()->superAdmin()->create([
            'status' => 1,
            'can_access_personal_calendar' => true,
        ]);
        $this->actingAs($staff, 'admin');

        $dueSoon = StaffCalendarEvent::create([
            'title' => 'Call client',
            'event_type' => 'reminder',
            'status' => 'scheduled',
            'starts_at' => now()->addMinutes(20),
            'ends_at' => now()->addMinutes(50),
            'is_all_day' => false,
            'reminder_minutes' => 30,
            'created_by_staff_id' => $staff->id,
        ]);

        // 1-day reminder should still fire even though start is > 60 minutes away
        $dayAhead = StaffCalendarEvent::create([
            'title' => 'Court prep',
            'event_type' => 'reminder',
            'status' => 'scheduled',
            'starts_at' => now()->addHours(20),
            'ends_at' => now()->addHours(21),
            'is_all_day' => false,
            'reminder_minutes' => 1440,
            'created_by_staff_id' => $staff->id,
        ]);

        $tooEarly = StaffCalendarEvent::create([
            'title' => 'Later reminder',
            'event_type' => 'reminder',
            'status' => 'scheduled',
            'starts_at' => now()->addHours(3),
            'ends_at' => now()->addHours(4),
            'is_all_day' => false,
            'reminder_minutes' => 30,
            'created_by_staff_id' => $staff->id,
        ]);

        $response = $this->getJson(route('booking.api.calendar-events.reminders'));
        $response->assertOk()->assertJson(['success' => true]);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($dueSoon->id, $ids);
        $this->assertContains($dayAhead->id, $ids);
        $this->assertNotContains($tooEarly->id, $ids);
    }

    #[Test]
    public function pending_reminders_include_overdue_not_attended_events(): void
    {
        $staff = Staff::factory()->superAdmin()->create([
            'status' => 1,
            'can_access_personal_calendar' => true,
        ]);
        $this->actingAs($staff, 'admin');

        $overdue = StaffCalendarEvent::create([
            'title' => 'Missed follow-up',
            'event_type' => 'reminder',
            'status' => 'scheduled',
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->subHours(1),
            'is_all_day' => false,
            'reminder_minutes' => null,
            'created_by_staff_id' => $staff->id,
        ]);

        $tooOld = StaffCalendarEvent::create([
            'title' => 'Older than yesterday',
            'event_type' => 'reminder',
            'status' => 'scheduled',
            'starts_at' => now()->subDays(2)->setTime(9, 0),
            'ends_at' => now()->subDays(2)->setTime(9, 30),
            'is_all_day' => false,
            'reminder_minutes' => null,
            'created_by_staff_id' => $staff->id,
        ]);

        $completed = StaffCalendarEvent::create([
            'title' => 'Done already',
            'event_type' => 'reminder',
            'status' => 'completed',
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->subHours(1),
            'is_all_day' => false,
            'reminder_minutes' => 15,
            'created_by_staff_id' => $staff->id,
        ]);

        $response = $this->getJson(route('booking.api.calendar-events.reminders'));
        $response->assertOk()->assertJson(['success' => true]);

        $rows = collect($response->json('data'));
        $this->assertTrue($rows->contains(fn ($row) => (int) $row['id'] === $overdue->id));
        $this->assertFalse($rows->contains(fn ($row) => (int) $row['id'] === $tooOld->id));
        $this->assertFalse($rows->contains(fn ($row) => (int) $row['id'] === $completed->id));

        $overdueRow = $rows->firstWhere('id', $overdue->id);
        $this->assertTrue((bool) ($overdueRow['is_overdue'] ?? false));
        $this->assertSame('overdue', $overdueRow['alert_kind'] ?? null);
    }

    #[Test]
    public function pending_reminders_only_show_to_the_staff_who_created_them(): void
    {
        $owner = Staff::factory()->superAdmin()->create([
            'status' => 1,
            'can_access_personal_calendar' => true,
            'email' => 'owner.reminder.alert@example.com',
        ]);
        $other = Staff::factory()->superAdmin()->create([
            'status' => 1,
            'can_access_personal_calendar' => true,
            'email' => 'other.reminder.alert@example.com',
        ]);

        $ownersEvent = StaffCalendarEvent::create([
            'title' => 'Owner only alert',
            'event_type' => 'reminder',
            'status' => 'scheduled',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->subMinutes(30),
            'is_all_day' => false,
            'reminder_minutes' => 15,
            'created_by_staff_id' => $owner->id,
        ]);

        $this->actingAs($other, 'admin');
        $otherResponse = $this->getJson(route('booking.api.calendar-events.reminders'));
        $otherResponse->assertOk()->assertJson(['success' => true]);
        $otherIds = collect($otherResponse->json('data'))->pluck('id')->all();
        $this->assertNotContains($ownersEvent->id, $otherIds);

        $this->actingAs($owner, 'admin');
        $ownerResponse = $this->getJson(route('booking.api.calendar-events.reminders'));
        $ownerResponse->assertOk()->assertJson(['success' => true]);
        $ownerIds = collect($ownerResponse->json('data'))->pluck('id')->all();
        $this->assertContains($ownersEvent->id, $ownerIds);
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
            'starts_at' => now()->addDays(2)->setTime(9, 0),
            'ends_at' => now()->addDays(2)->setTime(9, 30),
            'is_all_day' => true,
            'created_by_staff_id' => $staff->id,
        ]);
        $completed = StaffCalendarEvent::create([
            'title' => 'Done reminder',
            'event_type' => 'reminder',
            'status' => 'completed',
            'starts_at' => now()->addDays(2)->setTime(10, 0),
            'ends_at' => now()->addDays(2)->setTime(10, 30),
            'is_all_day' => true,
            'created_by_staff_id' => $staff->id,
        ]);
        $cancelled = StaffCalendarEvent::create([
            'title' => 'Cancelled reminder',
            'event_type' => 'reminder',
            'status' => 'cancelled',
            'starts_at' => now()->addDays(2)->setTime(11, 0),
            'ends_at' => now()->addDays(2)->setTime(11, 30),
            'is_all_day' => true,
            'created_by_staff_id' => $staff->id,
        ]);

        // Personal reminders live on the booking personal calendar feed (not the dashboard widget).
        $response = $this->getJson(route('booking.api.appointments', [
            'format' => 'calendar',
            'type' => 'personal',
            'staff_id' => $staff->id,
            'start' => now()->startOfMonth()->toIso8601String(),
            'end' => now()->endOfMonth()->toIso8601String(),
        ]));

        $response->assertOk()->assertJson(['success' => true]);
        $ids = collect($response->json('data') ?? [])->pluck('id')->map(fn ($id) => (string) $id)->all();

        $this->assertContains('staff-cal-' . $active->id, $ids);
        $this->assertNotContains('staff-cal-' . $completed->id, $ids);
        $this->assertNotContains('staff-cal-' . $cancelled->id, $ids);
    }
}
