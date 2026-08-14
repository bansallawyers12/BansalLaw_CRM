<?php

namespace Tests\Unit;

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
}
