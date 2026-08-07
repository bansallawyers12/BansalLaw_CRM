<?php

namespace Tests\Unit;

use App\Models\Staff;
use App\Models\StaffCalendarEvent;
use App\Services\CalendarSync\CalendarEventTitleBuilder;
use App\Services\CalendarSync\CalendarSyncMasterControl;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CalendarSyncMasterControlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget(CalendarSyncMasterControl::CACHE_KEY);
        $path = storage_path('app/' . CalendarSyncMasterControl::FILE_RELATIVE);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    protected function tearDown(): void
    {
        Cache::forget(CalendarSyncMasterControl::CACHE_KEY);
        $path = storage_path('app/' . CalendarSyncMasterControl::FILE_RELATIVE);
        if (is_file($path)) {
            @unlink($path);
        }
        parent::tearDown();
    }

    #[Test]
    public function defaults_to_config_when_no_override(): void
    {
        config(['zoho_calendar.enabled' => false]);
        $this->assertFalse(CalendarSyncMasterControl::isEnabled());

        config(['zoho_calendar.enabled' => true]);
        $this->assertTrue(CalendarSyncMasterControl::isEnabled());
    }

    #[Test]
    public function super_admin_override_disables_immediately(): void
    {
        config(['zoho_calendar.enabled' => true]);
        $admin = new Staff(['id' => 1, 'role' => 1, 'status' => 1]);

        CalendarSyncMasterControl::setEnabled(false, $admin);
        $this->assertTrue(CalendarSyncMasterControl::isDisabled());

        CalendarSyncMasterControl::setEnabled(true, $admin);
        $this->assertTrue(CalendarSyncMasterControl::isEnabled());
    }

    #[Test]
    public function only_permanent_super_admin_can_control(): void
    {
        $super = new Staff(['role' => 1, 'status' => 1]);
        $staff = new Staff(['role' => 5, 'status' => 1, 'grant_super_admin_access' => 0]);

        $this->assertTrue(CalendarSyncMasterControl::canControl($super));
        $this->assertFalse(CalendarSyncMasterControl::canControl($staff));
    }

    #[Test]
    public function title_builder_includes_file_and_matter(): void
    {
        $this->assertSame(
            '[71 / MAT-9] Court mention',
            CalendarEventTitleBuilder::compose('Court mention', '71', 'MAT-9')
        );

        $event = new StaffCalendarEvent([
            'title' => 'Deadline',
            'client_id' => 42,
            'client_matter_id' => null,
        ]);
        $this->assertSame('[42] Deadline', CalendarEventTitleBuilder::forStaffEvent($event));
    }

    #[Test]
    public function title_builder_for_hearing(): void
    {
        $hearing = new \App\Models\ClientCourtHearing([
            'client_id' => 71,
            'hearing_type' => 'Directions',
            'court_name' => 'FCFCOA',
            'client_matter_id' => null,
        ]);

        $this->assertSame(
            '[71] Directions @ FCFCOA',
            \App\Services\CalendarSync\CalendarEventTitleBuilder::forHearing($hearing)
        );
    }
}
