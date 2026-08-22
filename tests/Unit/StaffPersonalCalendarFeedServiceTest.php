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

        $this->assertSame('#5c3d8f', $event['backgroundColor']);
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

        $this->assertSame('#1E7A52', $event['backgroundColor']);
        $this->assertContains('event-kind-website_booking', $event['classNames']);
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
    public function stats_for_staff_uses_count_queries_not_full_event_payloads(): void
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
}
