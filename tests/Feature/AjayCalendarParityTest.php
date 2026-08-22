<?php

namespace Tests\Feature;

use App\Http\Controllers\CRM\BookingAppointmentsController;
use App\Models\AppointmentConsultant;
use App\Models\BookingAppointment;
use App\Models\ClientCourtHearing;
use App\Models\Staff;
use App\Models\StaffCalendarEvent;
use App\Services\Email\EmailCalendarMergeService;
use App\Services\StaffPersonalCalendarFeedService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AjayCalendarParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\DB::table('user_roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'Admin', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function ajay_dashboard_feed_matches_ajay_booking_calendar_scope(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-22 10:00:00', 'Australia/Melbourne'));
        config([
            'app.timezone' => 'Australia/Melbourne',
            'booking_calendar.include_important_events' => true,
            'booking_calendar.include_past_in_visible_range' => false,
            'booking_calendar.data_source' => 'local',
        ]);

        $consultant = AppointmentConsultant::create([
            'name' => 'Ajay Bansal',
            'email' => 'ajay.calendar@example.com',
            'calendar_type' => 'ajay',
            'location' => 'melbourne',
            'is_active' => true,
        ]);
        config(['booking_calendar.local_consultant_id_by_calendar_type.ajay' => (int) $consultant->id]);

        $otherConsultant = AppointmentConsultant::create([
            'name' => 'Michael',
            'email' => 'michael.calendar@example.com',
            'calendar_type' => 'kunal',
            'location' => 'melbourne',
            'is_active' => true,
        ]);
        config(['booking_calendar.local_consultant_id_by_calendar_type.kunal' => (int) $otherConsultant->id]);

        $staff = Staff::create([
            'first_name' => 'Ajay',
            'last_name' => 'Tester',
            'email' => 'ajay.calendar@example.com',
            'password' => bcrypt('password'),
            'role' => 1,
            'status' => 1,
        ]);

        $confirmed = BookingAppointment::create([
            'bansal_appointment_id' => 91001,
            'consultant_id' => $consultant->id,
            'client_name' => 'Confirmed Client',
            'client_email' => 'confirmed@example.com',
            'appointment_datetime' => '2026-08-25 11:00:00',
            'duration_minutes' => 15,
            'location' => 'melbourne',
            'meeting_type' => 'in_person',
            'status' => 'confirmed',
        ]);

        BookingAppointment::create([
            'bansal_appointment_id' => 91002,
            'consultant_id' => $consultant->id,
            'client_name' => 'Cancelled Client',
            'client_email' => 'cancelled@example.com',
            'appointment_datetime' => '2026-08-26 11:00:00',
            'duration_minutes' => 15,
            'location' => 'melbourne',
            'meeting_type' => 'phone',
            'status' => 'cancelled',
        ]);

        BookingAppointment::create([
            'bansal_appointment_id' => 91003,
            'consultant_id' => $consultant->id,
            'client_name' => 'No Show Client',
            'client_email' => 'noshow@example.com',
            'appointment_datetime' => '2026-08-27 11:00:00',
            'duration_minutes' => 15,
            'location' => 'melbourne',
            'meeting_type' => 'video',
            'status' => 'no_show',
        ]);

        BookingAppointment::create([
            'bansal_appointment_id' => 91004,
            'consultant_id' => $otherConsultant->id,
            'client_name' => 'Other Calendar Client',
            'client_email' => 'other@example.com',
            'appointment_datetime' => '2026-08-28 11:00:00',
            'duration_minutes' => 15,
            'location' => 'melbourne',
            'meeting_type' => 'in_person',
            'status' => 'confirmed',
        ]);

        $hearing = ClientCourtHearing::create([
            'client_id' => 1,
            'hearing_date' => '2026-08-29',
            'hearing_time' => '14:00:00',
            'hearing_type' => 'Directions',
            'court_name' => 'Federal Court',
            'status' => 'Scheduled',
        ]);

        $staffEvent = StaffCalendarEvent::create([
            'title' => 'Team huddle',
            'event_type' => 'meeting',
            'starts_at' => '2026-08-30 10:00:00',
            'ends_at' => '2026-08-30 10:30:00',
            'is_all_day' => false,
            'calendar_type' => 'ajay',
        ]);

        $range = [
            'start' => '2026-08-22T00:00:00+10:00',
            'end' => '2026-09-01T00:00:00+10:00',
            'type' => 'ajay',
        ];

        $dashboard = app(StaffPersonalCalendarFeedService::class)
            ->eventsForStaffRequest($staff, Request::create('/dashboard/calendar-events', 'GET', $range));

        $bookingQuery = BookingAppointment::query()->with(['client', 'consultant']);
        $bookingQuery->where('consultant_id', $consultant->id);
        $bookingFeed = $this->buildBookingCalendarFeed(
            Request::create('/booking/appointments', 'GET', array_merge($range, ['format' => 'calendar'])),
            $bookingQuery
        );

        $dashboardIds = $this->normalizedEventKeys($dashboard);
        $bookingIds = $this->normalizedEventKeys($bookingFeed['data'] ?? []);

        sort($dashboardIds);
        sort($bookingIds);

        $this->assertSame($bookingIds, $dashboardIds);
        $this->assertContains('booking:' . $confirmed->id, $dashboardIds);
        $this->assertContains('court:' . $hearing->id, $dashboardIds);
        $this->assertContains('staff:' . $staffEvent->id, $dashboardIds);
        $this->assertFalse(collect($dashboard)->contains(fn (array $row) => ($row['status'] ?? '') === 'cancelled'));
        $this->assertFalse(collect($dashboard)->contains(fn (array $row) => ($row['status'] ?? '') === 'no_show'));
        $this->assertFalse(collect($bookingFeed['data'] ?? [])->contains(
            fn (array $row) => in_array(strtolower((string) ($row['status'] ?? '')), ['cancelled', 'no_show'], true)
        ));
    }

    #[Test]
    public function find_existing_hearing_does_not_match_timed_to_all_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-21 10:00:00', 'Australia/Melbourne'));
        config(['app.timezone' => 'Australia/Melbourne']);

        $timed = ClientCourtHearing::create([
            'client_id' => 9001,
            'hearing_date' => '2026-08-30',
            'hearing_time' => '10:00',
            'hearing_type' => 'Hearing',
            'status' => 'Scheduled',
        ]);

        $service = new EmailCalendarMergeService();
        $ref = new \ReflectionClass($service);
        $method = $ref->getMethod('findExistingHearingId');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($service, 9001, '2026-08-30', null));
        $this->assertSame((int) $timed->id, $method->invoke($service, 9001, '2026-08-30', '10:00'));

        $allDay = ClientCourtHearing::create([
            'client_id' => 9001,
            'hearing_date' => '2026-08-30',
            'hearing_time' => null,
            'hearing_type' => 'Hearing',
            'status' => 'Scheduled',
        ]);

        $this->assertSame((int) $allDay->id, $method->invoke($service, 9001, '2026-08-30', null));
        $this->assertSame((int) $timed->id, $method->invoke($service, 9001, '2026-08-30', '10:00'));
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\BookingAppointment>  $query
     * @return array{success: bool, data: list<array<string, mixed>>}
     */
    private function buildBookingCalendarFeed(Request $request, $query): array
    {
        $controller = app(BookingAppointmentsController::class);
        $ref = new \ReflectionClass($controller);
        $method = $ref->getMethod('buildCalendarFeedResponse');
        $method->setAccessible(true);

        return $method->invoke($controller, $request, $query);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<string>
     */
    private function normalizedEventKeys(array $rows): array
    {
        $keys = [];
        foreach ($rows as $row) {
            $id = (string) ($row['id'] ?? '');
            if (str_starts_with($id, 'booking-')) {
                $keys[] = 'booking:' . substr($id, strlen('booking-'));
            } elseif (str_starts_with($id, 'court-')) {
                $keys[] = 'court:' . substr($id, strlen('court-'));
            } elseif (str_starts_with($id, 'staff-cal-')) {
                $keys[] = 'staff:' . substr($id, strlen('staff-cal-'));
            } elseif (ctype_digit($id)) {
                $keys[] = 'booking:' . $id;
            } elseif (($row['event_kind'] ?? '') === 'website_booking' && ! empty($row['booking_appointment_id'])) {
                $keys[] = 'booking:' . $row['booking_appointment_id'];
            }
        }

        return array_values(array_unique($keys));
    }
}
