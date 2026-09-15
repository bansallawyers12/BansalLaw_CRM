<?php

namespace Tests\Unit;

use App\Support\CalendarScheduleConstraints;
use Carbon\Carbon;
use Tests\TestCase;

class CalendarScheduleConstraintsTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_past_date_is_rejected(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15 10:00:00', config('app.timezone')));

        $starts = Carbon::parse('2026-09-14 10:00:00', config('app.timezone'));

        $this->assertTrue(CalendarScheduleConstraints::isPastDate($starts));
        $this->assertSame(
            CalendarScheduleConstraints::PAST_DATE_MESSAGE,
            CalendarScheduleConstraints::pastDateMessageIfAny($starts)
        );
    }

    public function test_today_is_allowed_when_within_business_hours(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15 10:00:00', config('app.timezone')));

        $starts = Carbon::parse('2026-09-15 10:00:00', config('app.timezone'));
        $ends = Carbon::parse('2026-09-15 11:00:00', config('app.timezone'));

        $this->assertFalse(CalendarScheduleConstraints::isPastDate($starts));
        $this->assertNull(CalendarScheduleConstraints::pastDateMessageIfAny($starts));
        $this->assertNull(CalendarScheduleConstraints::staffEventMessage($starts, $ends, false));
    }
}
