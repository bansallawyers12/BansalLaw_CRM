<?php

namespace Tests\Unit;

use App\Models\Staff;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StaffPersonalCalendarPermissionTest extends TestCase
{
    #[Test]
    public function native_super_admin_can_use_and_grant_without_flag(): void
    {
        $staff = new Staff(['role' => 1, 'status' => 1, 'can_access_personal_calendar' => false]);

        $this->assertTrue($staff->canAccessPersonalCalendar());
        $this->assertTrue(Staff::canGrantPersonalCalendarPermission($staff));
    }

    #[Test]
    public function admin_cannot_use_or_grant_without_flag(): void
    {
        $staff = new Staff(['role' => 17, 'status' => 1, 'can_access_personal_calendar' => false]);

        $this->assertFalse($staff->canAccessPersonalCalendar());
        $this->assertFalse(Staff::canGrantPersonalCalendarPermission($staff));
    }

    #[Test]
    public function regular_staff_with_flag_can_use_but_cannot_grant(): void
    {
        $staff = new Staff([
            'role' => 16,
            'status' => 1,
            'can_access_personal_calendar' => true,
        ]);

        $this->assertTrue($staff->canAccessPersonalCalendar());
        $this->assertFalse(Staff::canGrantPersonalCalendarPermission($staff));
    }

    #[Test]
    public function regular_staff_without_flag_cannot_use(): void
    {
        $staff = new Staff([
            'role' => 16,
            'status' => 1,
            'can_access_personal_calendar' => false,
        ]);

        $this->assertFalse($staff->canAccessPersonalCalendar());
        $this->assertFalse(Staff::canGrantPersonalCalendarPermission($staff));
    }
}
