<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Note;
use App\Models\Staff;
use App\Models\StaffCalendarEvent;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PersonalCalendarAccessTest extends TestCase
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createStaff(array $attrs = []): Staff
    {
        return Staff::create(array_merge([
            'first_name' => 'Sam',
            'last_name' => 'Staff',
            'email' => 'sam.calendar.' . uniqid('', true) . '@example.com',
            'password' => bcrypt('password'),
            'role' => 16,
            'status' => 1,
            'can_access_personal_calendar' => false,
        ], $attrs));
    }

    #[Test]
    public function staff_without_calendar_permission_cannot_load_calendar_events(): void
    {
        $staff = $this->createStaff(['can_access_personal_calendar' => false]);
        $this->actingAs($staff, 'admin');

        $this->getJson(route('dashboard.calendar-events'))
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }

    #[Test]
    public function staff_with_calendar_permission_can_load_calendar_events(): void
    {
        $staff = $this->createStaff(['can_access_personal_calendar' => true]);
        $this->actingAs($staff, 'admin');

        $this->getJson(route('dashboard.calendar-events', [
            'start' => now()->toIso8601String(),
            'end' => now()->addWeek()->toIso8601String(),
        ]))
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);
    }

    #[Test]
    public function super_admin_can_load_calendar_events_without_flag(): void
    {
        $staff = $this->createStaff([
            'role' => 1,
            'can_access_personal_calendar' => false,
            'first_name' => 'Super',
            'email' => 'super.calendar@example.com',
        ]);
        $this->actingAs($staff, 'admin');

        $this->getJson(route('dashboard.calendar-events', [
            'start' => now()->toIso8601String(),
            'end' => now()->addWeek()->toIso8601String(),
        ]))
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);
    }

    #[Test]
    public function staff_without_calendar_permission_cannot_create_calendar_event(): void
    {
        $staff = $this->createStaff(['can_access_personal_calendar' => false]);
        $this->actingAs($staff, 'admin');

        $this->postJson(route('booking.api.calendar-events.store'), [
            'title' => 'Blocked reminder',
            'event_type' => 'reminder',
            'starts_at' => now()->addDay()->setTime(10, 0)->toIso8601String(),
            'ends_at' => now()->addDay()->setTime(10, 30)->toIso8601String(),
        ])
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }

    #[Test]
    public function non_super_admin_cannot_force_all_staff_calendar_view(): void
    {
        $staff = $this->createStaff(['can_access_personal_calendar' => true]);
        $this->actingAs($staff, 'admin');

        $response = $this->getJson(route('dashboard.calendar-events', [
            'start' => now()->toIso8601String(),
            'end' => now()->addWeek()->toIso8601String(),
            'staff_view' => 'all',
        ]));

        $response->assertOk()->assertJson([
            'success' => true,
            'staff_view' => 'self',
        ]);
    }

    #[Test]
    public function super_admin_defaults_to_all_staff_calendar_view(): void
    {
        $staff = $this->createStaff([
            'role' => 1,
            'first_name' => 'Super',
            'email' => 'super.default.calendar@example.com',
        ]);
        $this->actingAs($staff, 'admin');

        $this->getJson(route('dashboard.calendar-events', [
            'start' => now()->toIso8601String(),
            'end' => now()->addWeek()->toIso8601String(),
        ]))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'staff_view' => 'all',
            ]);
    }

    #[Test]
    public function super_admin_can_request_all_staff_calendar_view(): void
    {
        $staff = $this->createStaff([
            'role' => 1,
            'can_access_personal_calendar' => false,
            'first_name' => 'Super',
            'email' => 'super.all.calendar@example.com',
        ]);
        $this->actingAs($staff, 'admin');

        $this->getJson(route('dashboard.calendar-events', [
            'start' => now()->toIso8601String(),
            'end' => now()->addWeek()->toIso8601String(),
            'staff_view' => 'all',
        ]))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'staff_view' => 'all',
            ]);
    }

    #[Test]
    public function super_admin_can_request_individual_staff_calendar_view(): void
    {
        $super = $this->createStaff([
            'role' => 1,
            'first_name' => 'Super',
            'email' => 'super.pick.calendar@example.com',
        ]);
        $other = $this->createStaff([
            'role' => 16,
            'can_access_personal_calendar' => true,
            'first_name' => 'Other',
            'email' => 'other.pick.calendar@example.com',
        ]);
        $this->actingAs($super, 'admin');

        $this->getJson(route('dashboard.calendar-events', [
            'start' => now()->toIso8601String(),
            'end' => now()->addWeek()->toIso8601String(),
            'staff_view' => $other->id,
        ]))
            ->assertOk()
            ->assertJson([
                'success' => true,
                'staff_view' => 'staff',
            ]);
    }

    #[Test]
    public function staff_calendar_list_excludes_super_admin_and_staff_without_access(): void
    {
        $super = $this->createStaff([
            'role' => 1,
            'first_name' => 'Admin',
            'last_name' => 'One',
            'email' => 'super.list.calendar@example.com',
        ]);
        $withAccess = $this->createStaff([
            'role' => 16,
            'can_access_personal_calendar' => true,
            'first_name' => 'WithAccess',
            'email' => 'with.access.calendar@example.com',
        ]);
        $withoutAccess = $this->createStaff([
            'role' => 16,
            'can_access_personal_calendar' => false,
            'first_name' => 'NoAccess',
            'email' => 'no.access.calendar@example.com',
        ]);
        $this->actingAs($super, 'admin');

        $options = app(\App\Services\StaffPersonalCalendarFeedService::class)->staffFilterOptions();
        $ids = collect($options)->pluck('id')->all();

        $this->assertNotContains($super->id, $ids);
        $this->assertContains($withAccess->id, $ids);
        $this->assertNotContains($withoutAccess->id, $ids);
    }

    #[Test]
    public function staff_with_calendar_access_can_open_personal_booking_calendar_page(): void
    {
        $viewer = $this->createStaff([
            'role' => 1,
            'first_name' => 'Admin',
            'email' => 'admin.khushi.calendar@example.com',
        ]);
        $khushi = $this->createStaff([
            'role' => 16,
            'can_access_personal_calendar' => true,
            'first_name' => 'Khushi',
            'last_name' => 'Sangroya',
            'email' => 'khushi.calendar@example.com',
        ]);
        $this->actingAs($viewer, 'admin');

        $this->get(route('booking.appointments.calendar.staff', ['staff' => $khushi->id]))
            ->assertOk()
            ->assertSee('Khushi Sangroya', false)
            ->assertSee('Personal calendar', false);

        $this->getJson(route('booking.api.appointments', [
            'format' => 'calendar',
            'type' => 'personal',
            'staff_id' => $khushi->id,
            'start' => now()->toIso8601String(),
            'end' => now()->addMonth()->toIso8601String(),
        ]))->assertOk()->assertJsonPath('success', true);
    }

    #[Test]
    public function staff_without_calendar_access_personal_page_returns_not_found(): void
    {
        $viewer = $this->createStaff([
            'role' => 1,
            'email' => 'admin.no.calendar.page@example.com',
        ]);
        $noAccess = $this->createStaff([
            'role' => 16,
            'can_access_personal_calendar' => false,
            'email' => 'no.page.calendar@example.com',
        ]);
        $this->actingAs($viewer, 'admin');

        $this->get(route('booking.appointments.calendar.staff', ['staff' => $noAccess->id]))
            ->assertNotFound();
    }

    #[Test]
    public function personal_calendar_cleared_at_hides_legacy_events_and_follow_ups(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-09 16:00:00', 'Australia/Melbourne'));

        $viewer = $this->createStaff([
            'role' => 1,
            'email' => 'admin.clear.calendar@example.com',
            'can_access_personal_calendar' => true,
        ]);
        $khushi = $this->createStaff([
            'role' => 16,
            'can_access_personal_calendar' => true,
            'first_name' => 'Khushi',
            'last_name' => 'Sangroya',
            'email' => 'khushi.clear.calendar@example.com',
        ]);

        config([
            'booking_calendar.personal_calendar_cleared' => [[
                'staff_id' => $khushi->id,
                'first_name' => 'Khushi',
                'last_name' => 'Sangroya',
                'cleared_at' => '2026-09-09 15:00:00',
            ]],
        ]);

        $legacyEvent = StaffCalendarEvent::create([
            'title' => 'Old reminder',
            'event_type' => 'reminder',
            'starts_at' => '2026-09-10 09:00:00',
            'ends_at' => '2026-09-10 09:15:00',
            'is_all_day' => false,
            'created_by_staff_id' => $khushi->id,
            'status' => 'scheduled',
        ]);
        $legacyEvent->forceFill(['created_at' => '2026-09-01 10:00:00'])->saveQuietly();

        $newEvent = StaffCalendarEvent::create([
            'title' => 'New reminder',
            'event_type' => 'reminder',
            'starts_at' => '2026-09-11 09:00:00',
            'ends_at' => '2026-09-11 09:15:00',
            'is_all_day' => false,
            'created_by_staff_id' => $khushi->id,
            'status' => 'scheduled',
        ]);

        $legacyFollowUp = Note::create([
            'client_id' => null,
            'user_id' => $khushi->id,
            'title' => 'Old follow-up',
            'description' => 'legacy',
            'is_action' => 1,
            'type' => 'client',
            'assigned_to' => $khushi->id,
            'status' => 0,
            'pin' => 0,
            'action_date' => '2026-09-12 09:00:00',
        ]);
        $legacyFollowUp->forceFill(['created_at' => '2026-09-01 11:00:00'])->saveQuietly();

        $newFollowUp = Note::create([
            'client_id' => null,
            'user_id' => $khushi->id,
            'title' => 'New follow-up',
            'description' => 'fresh',
            'is_action' => 1,
            'type' => 'client',
            'assigned_to' => $khushi->id,
            'status' => 0,
            'pin' => 0,
            'action_date' => '2026-09-13 09:00:00',
        ]);

        $this->actingAs($viewer, 'admin');

        $response = $this->getJson(route('booking.api.appointments', [
            'format' => 'calendar',
            'type' => 'personal',
            'staff_id' => $khushi->id,
            'start' => '2026-09-09T00:00:00+10:00',
            'end' => '2026-09-20T00:00:00+10:00',
        ]))->assertOk()->assertJsonPath('success', true);

        $ids = collect($response->json('data') ?? [])->pluck('id')->map(fn ($id) => (string) $id)->all();

        $this->assertContains('staff-cal-' . $newEvent->id, $ids);
        $this->assertNotContains('staff-cal-' . $legacyEvent->id, $ids);
        // Personal calendars no longer surface note/task follow-ups (My Tasks only).
        $this->assertNotContains('followup-' . $newFollowUp->id, $ids);
        $this->assertNotContains('followup-' . $legacyFollowUp->id, $ids);
    }

    #[Test]
    public function personal_calendar_shows_only_self_created_reminders_and_other(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 10:00:00', 'Australia/Melbourne'));

        $viewer = $this->createStaff([
            'role' => 1,
            'email' => 'admin.self.only.calendar@example.com',
            'can_access_personal_calendar' => true,
        ]);
        $khushi = $this->createStaff([
            'role' => 16,
            'can_access_personal_calendar' => true,
            'first_name' => 'Khushi',
            'last_name' => 'Sangroya',
            'email' => 'khushi.self.only.calendar@example.com',
        ]);
        $other = $this->createStaff([
            'role' => 16,
            'can_access_personal_calendar' => true,
            'email' => 'other.self.only.calendar@example.com',
        ]);

        config(['booking_calendar.personal_calendar_cleared' => []]);

        $ownReminder = StaffCalendarEvent::create([
            'title' => 'Own reminder',
            'event_type' => 'reminder',
            'starts_at' => '2026-09-12 09:00:00',
            'ends_at' => '2026-09-12 09:15:00',
            'is_all_day' => false,
            'created_by_staff_id' => $khushi->id,
            'status' => 'scheduled',
        ]);
        $ownMeeting = StaffCalendarEvent::create([
            'title' => 'Own meeting should hide',
            'event_type' => 'meeting',
            'starts_at' => '2026-09-12 11:00:00',
            'ends_at' => '2026-09-12 11:30:00',
            'is_all_day' => false,
            'created_by_staff_id' => $khushi->id,
            'status' => 'scheduled',
        ]);
        $ownCourt = StaffCalendarEvent::create([
            'title' => 'Own court should hide',
            'event_type' => 'court',
            'starts_at' => '2026-09-12 14:00:00',
            'ends_at' => '2026-09-12 15:00:00',
            'is_all_day' => false,
            'created_by_staff_id' => $khushi->id,
            'status' => 'scheduled',
        ]);
        $otherReminder = StaffCalendarEvent::create([
            'title' => 'Other staff reminder',
            'event_type' => 'reminder',
            'starts_at' => '2026-09-12 16:00:00',
            'ends_at' => '2026-09-12 16:15:00',
            'is_all_day' => false,
            'created_by_staff_id' => $other->id,
            'status' => 'scheduled',
        ]);

        $selfFollowUp = Note::create([
            'client_id' => null,
            'user_id' => $khushi->id,
            'title' => 'Self follow-up',
            'description' => 'mine',
            'is_action' => 1,
            'type' => 'client',
            'assigned_to' => $khushi->id,
            'status' => 0,
            'pin' => 0,
            'action_date' => '2026-09-13 09:00:00',
        ]);
        $assignedByOther = Note::create([
            'client_id' => null,
            'user_id' => $other->id,
            'title' => 'Assigned by other',
            'description' => 'not mine',
            'is_action' => 1,
            'type' => 'client',
            'assigned_to' => $khushi->id,
            'status' => 0,
            'pin' => 0,
            'action_date' => '2026-09-14 09:00:00',
        ]);

        $this->actingAs($viewer, 'admin');

        $ids = collect($this->getJson(route('booking.api.appointments', [
            'format' => 'calendar',
            'type' => 'personal',
            'staff_id' => $khushi->id,
            'start' => '2026-09-10T00:00:00+10:00',
            'end' => '2026-09-20T00:00:00+10:00',
        ]))->assertOk()->json('data') ?? [])->pluck('id')->map(fn ($id) => (string) $id)->all();

        $this->assertContains('staff-cal-' . $ownReminder->id, $ids);
        $this->assertNotContains('followup-' . $selfFollowUp->id, $ids);
        $this->assertNotContains('staff-cal-' . $ownMeeting->id, $ids);
        $this->assertNotContains('staff-cal-' . $ownCourt->id, $ids);
        $this->assertNotContains('staff-cal-' . $otherReminder->id, $ids);
        $this->assertNotContains('followup-' . $assignedByOther->id, $ids);
    }

    #[Test]
    public function personal_calendar_does_not_include_note_follow_ups(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 10:00:00', 'Australia/Melbourne'));

        $viewer = $this->createStaff([
            'role' => 1,
            'email' => 'admin.followup.dedupe@example.com',
            'can_access_personal_calendar' => true,
        ]);
        $khushi = $this->createStaff([
            'role' => 16,
            'can_access_personal_calendar' => true,
            'first_name' => 'Khushi',
            'last_name' => 'Sangroya',
            'email' => 'khushi.followup.dedupe@example.com',
        ]);
        $michael = $this->createStaff([
            'role' => 16,
            'can_access_personal_calendar' => true,
            'email' => 'michael.followup.dedupe@example.com',
        ]);
        $client = Admin::factory()->create([
            'type' => 'client',
            'first_name' => 'Rakesh',
            'last_name' => 'Kumar',
            'is_archived' => 0,
        ]);

        config(['booking_calendar.personal_calendar_cleared' => []]);

        $groupId = 'group_followup_dedupe_test';
        $selfCopy = Note::create([
            'client_id' => $client->id,
            'user_id' => $khushi->id,
            'title' => '',
            'description' => 'Lead follow-up',
            'is_action' => 1,
            'type' => 'client',
            'assigned_to' => $khushi->id,
            'status' => 0,
            'pin' => 0,
            'unique_group_id' => $groupId,
            'action_date' => '2026-09-24 00:00:00',
        ]);
        Note::create([
            'client_id' => $client->id,
            'user_id' => $khushi->id,
            'title' => '',
            'description' => 'Lead follow-up',
            'is_action' => 1,
            'type' => 'client',
            'assigned_to' => $michael->id,
            'status' => 0,
            'pin' => 0,
            'unique_group_id' => $groupId,
            'action_date' => '2026-09-24 00:00:00',
        ]);

        $this->actingAs($viewer, 'admin');

        $ids = collect($this->getJson(route('booking.api.appointments', [
            'format' => 'calendar',
            'type' => 'personal',
            'staff_id' => $khushi->id,
            'start' => '2026-09-01T00:00:00+10:00',
            'end' => '2026-09-30T00:00:00+10:00',
        ]))->assertOk()->json('data') ?? [])
            ->filter(fn ($row) => ($row['event_kind'] ?? '') === 'follow_up')
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->values()
            ->all();

        $this->assertSame([], $ids);
        $this->assertNotContains('followup-' . $selfCopy->id, $ids);

        $this->getJson(route('booking.api.calendar-stats.staff', ['staff' => $khushi->id]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.this_month', 0)
            ->assertJsonPath('data.upcoming', 0);
    }

    #[Test]
    public function personal_calendar_stats_match_deduped_feed_when_reminder_and_follow_up_overlap(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10 10:00:00', 'Australia/Melbourne'));

        $viewer = $this->createStaff([
            'role' => 1,
            'email' => 'admin.stats.dedupe@example.com',
            'can_access_personal_calendar' => true,
        ]);
        $khushi = $this->createStaff([
            'role' => 16,
            'can_access_personal_calendar' => true,
            'first_name' => 'Khushi',
            'last_name' => 'Sangroya',
            'email' => 'khushi.stats.dedupe@example.com',
        ]);
        $client = Admin::factory()->create([
            'type' => 'client',
            'first_name' => 'Rakesh',
            'last_name' => 'Kumar',
            'is_archived' => 0,
        ]);

        config(['booking_calendar.personal_calendar_cleared' => []]);

        Note::create([
            'client_id' => $client->id,
            'user_id' => $khushi->id,
            'title' => '',
            'description' => 'Lead follow-up',
            'is_action' => 1,
            'type' => 'client',
            'assigned_to' => $khushi->id,
            'status' => 0,
            'pin' => 0,
            'unique_group_id' => 'group_stats_dedupe',
            'action_date' => '2026-09-25 00:00:00',
        ]);
        StaffCalendarEvent::create([
            'title' => 'Rakesh Kumar — Follow-up',
            'event_type' => 'reminder',
            'starts_at' => '2026-09-25 09:00:00',
            'ends_at' => '2026-09-25 09:30:00',
            'is_all_day' => true,
            'client_id' => $client->id,
            'created_by_staff_id' => $khushi->id,
            'status' => 'scheduled',
        ]);

        $this->actingAs($viewer, 'admin');

        $this->getJson(route('booking.api.calendar-stats.staff', ['staff' => $khushi->id]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.this_month', 1)
            ->assertJsonPath('data.upcoming', 1);
    }
}
