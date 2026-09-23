<?php

namespace Tests\Unit;

use App\Models\Staff;
use App\Services\Booking\StaffCalendarFeedService;
use App\Services\StaffPersonalCalendarFeedService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StaffPersonalCalendarFeedServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function service(): StaffPersonalCalendarFeedService
    {
        return new StaffPersonalCalendarFeedService(new StaffCalendarFeedService());
    }

    #[Test]
    public function clamp_range_moves_start_up_to_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-14 10:00:00', 'Australia/Melbourne'));
        config(['app.timezone' => 'Australia/Melbourne']);

        [$start, $end] = $this->service()->clampRangeToUpcoming(
            '2026-08-01T00:00:00+10:00',
            '2026-09-01T00:00:00+10:00',
            'Australia/Melbourne'
        );

        $this->assertSame('2026-08-14', $start->toDateString());
        $this->assertSame('2026-09-01', $end->toDateString());
    }

    #[Test]
    public function clamp_range_keeps_future_month_start(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-14 10:00:00', 'Australia/Melbourne'));
        config(['app.timezone' => 'Australia/Melbourne']);

        [$start] = $this->service()->clampRangeToUpcoming(
            '2026-09-01T00:00:00+10:00',
            '2026-10-01T00:00:00+10:00',
            'Australia/Melbourne'
        );

        $this->assertSame('2026-09-01', $start->toDateString());
    }

    #[Test]
    public function to_full_calendar_event_uses_booking_legend_colours(): void
    {
        $event = $this->service()->toFullCalendarEvent([
            'id' => 'court-1',
            'title' => 'Malcolm Heys — Hearing',
            'event_type' => 'court',
            'event_kind' => 'court_hearing',
            'starts_at' => '2026-08-14T09:00:00+10:00',
            'client_email' => 'malcolm.s.heys@gmail.com',
        ]);

        $this->assertSame('#e8590c', $event['backgroundColor']);
        $this->assertContains('event-court', $event['classNames']);
        $this->assertSame('malcolm.s.heys@gmail.com', $event['extendedProps']['client_email']);
    }

    #[Test]
    public function to_full_calendar_event_colours_website_bookings_as_meetings(): void
    {
        $event = $this->service()->toFullCalendarEvent([
            'id' => 'booking-9',
            'title' => 'Jane Doe (In Person)',
            'event_type' => 'meeting',
            'event_kind' => 'website_booking',
            'status' => 'confirmed',
            'starts_at' => '2026-08-21T11:00:00+10:00',
        ]);

        $this->assertSame('#218838', $event['backgroundColor']);
        $this->assertContains('event-kind-website_booking', $event['classNames']);
        $this->assertContains('event-status-confirmed', $event['classNames']);
        $this->assertSame('#fff', $event['textColor']);
    }

    #[Test]
    public function booking_calendar_type_falls_back_to_first_name(): void
    {
        $staff = new Staff();
        $staff->first_name = 'Ajay';
        $staff->email = '';

        $this->assertSame('ajay', $this->service()->bookingCalendarTypeForStaff($staff));
    }

    #[Test]
    public function booking_calendar_type_maps_michael_to_kunal(): void
    {
        $staff = new Staff();
        $staff->first_name = 'Michael';
        $staff->email = '';

        $this->assertSame('kunal', $this->service()->bookingCalendarTypeForStaff($staff));
    }

    #[Test]
    public function important_events_accept_synthetic_request_without_url(): void
    {
        $request = new \Illuminate\Http\Request([
            'start' => '2026-08-22T00:00:00+10:00',
            'end' => '2026-08-30T00:00:00+10:00',
        ]);

        $method = new \ReflectionMethod(StaffPersonalCalendarFeedService::class, 'bookingCalendarImportantEvents');
        $method->setAccessible(true);

        $rows = $method->invoke($this->service(), 'ajay', $request);

        $this->assertIsArray($rows);
    }

    #[Test]
    public function stats_for_staff_returns_integer_kpi_counts(): void
    {
        $staff = new Staff();
        $staff->first_name = 'Ajay';
        $staff->email = '';
        $staff->id = 1;

        $stats = $this->service()->statsForStaff($staff);

        $this->assertArrayHasKey('today', $stats);
        $this->assertArrayHasKey('this_week', $stats);
        $this->assertArrayHasKey('overdue_actions', $stats);
        $this->assertIsInt($stats['today']);
        $this->assertIsInt($stats['this_week']);
        $this->assertIsInt($stats['overdue_actions']);
    }

    #[Test]
    public function deduplicate_events_removes_same_booking_and_cross_source_slot_duplicates(): void
    {
        $method = new \ReflectionMethod(StaffPersonalCalendarFeedService::class, 'deduplicateEvents');
        $method->setAccessible(true);

        $rows = $method->invoke($this->service(), [
            [
                'id' => 'booking-12',
                'event_kind' => 'website_booking',
                'booking_appointment_id' => 12,
                'event_type' => 'meeting',
                'client_id' => 44,
                'starts_at' => '2026-12-02T10:00:00+11:00',
            ],
            [
                'id' => 'booking-12',
                'event_kind' => 'website_booking',
                'booking_appointment_id' => 12,
                'event_type' => 'meeting',
                'client_id' => 44,
                'starts_at' => '2026-12-02T10:00:00+11:00',
            ],
            [
                'id' => 'staff-cal-88',
                'event_kind' => 'staff_event',
                'staff_calendar_event_id' => 88,
                'event_type' => 'meeting',
                'client_id' => 44,
                'starts_at' => '2026-12-02T10:00:00+11:00',
            ],
            [
                'id' => 'court-5',
                'event_kind' => 'court_hearing',
                'court_hearing_id' => 5,
                'event_type' => 'court',
                'client_id' => 99,
                'starts_at' => '2026-12-02T14:00:00+11:00',
            ],
        ]);

        $this->assertCount(2, $rows);
        $this->assertSame('booking-12', $rows[0]['id']);
        $this->assertSame('court-5', $rows[1]['id']);
    }

    #[Test]
    public function to_full_calendar_event_colours_follow_ups_as_reminders(): void
    {
        $event = $this->service()->toFullCalendarEvent([
            'id' => 'followup-3',
            'title' => 'Jane — Call back',
            'event_type' => 'reminder',
            'event_kind' => 'follow_up',
            'starts_at' => '2026-08-21T15:00:00+10:00',
        ]);

        $this->assertSame('#e0a800', $event['backgroundColor']);
        $this->assertSame('#000', $event['textColor']);
        $this->assertContains('event-kind-follow_up', $event['classNames']);
    }

    #[Test]
    public function default_type_for_staff_uses_admin_override(): void
    {
        $staff = new Staff([
            'first_name' => 'Sarah',
            'last_name' => 'Jones',
            'email' => 'sarah@example.com',
            'default_calendar_type' => 'kunal',
        ]);

        $this->assertSame('kunal', $this->service()->defaultTypeForStaff($staff));
    }

    #[Test]
    public function default_type_for_staff_uses_name_hint_when_automatic(): void
    {
        $staff = new Staff([
            'first_name' => 'Ajay',
            'last_name' => 'Bansal',
            'email' => 'ajay@example.com',
            'default_calendar_type' => null,
        ]);

        $this->assertSame('ajay', $this->service()->defaultTypeForStaff($staff));

        $michael = new Staff([
            'first_name' => 'Michael',
            'last_name' => 'Test',
            'email' => 'michael@example.com',
            'default_calendar_type' => null,
        ]);

        $this->assertSame('kunal', $this->service()->defaultTypeForStaff($michael));
    }

    #[Test]
    public function default_type_for_staff_falls_back_to_ajay(): void
    {
        config(['booking_calendar.default_website_calendar_type' => 'ajay']);

        $staff = new Staff([
            'first_name' => 'Priya',
            'last_name' => 'Shah',
            'email' => 'priya@example.com',
            'default_calendar_type' => null,
        ]);

        $this->assertSame('ajay', $this->service()->defaultTypeForStaff($staff));
    }

    #[Test]
    public function resolve_requested_calendar_type_accepts_known_keys_only(): void
    {
        $ok = $this->service()->resolveRequestedCalendarType(new \Illuminate\Http\Request([
            'booking_calendar_type' => 'kunal',
        ]));
        $this->assertSame('kunal', $ok);

        $bad = $this->service()->resolveRequestedCalendarType(new \Illuminate\Http\Request([
            'booking_calendar_type' => 'paid',
        ]));
        $this->assertNull($bad);
    }
}
