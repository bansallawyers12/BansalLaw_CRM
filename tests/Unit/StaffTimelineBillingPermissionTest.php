<?php

namespace Tests\Unit;

use App\Models\Staff;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StaffTimelineBillingPermissionTest extends TestCase
{
    #[Test]
    public function native_super_admin_can_use_and_grant_without_flag(): void
    {
        $staff = new Staff(['role' => 1, 'status' => 1, 'can_use_timeline_billing' => false]);

        $this->assertTrue($staff->canUseTimelineBilling());
        $this->assertTrue(Staff::canGrantTimelineBillingPermission($staff));
    }

    #[Test]
    public function any_active_staff_can_use_timeline_billing_on_a_matter_they_can_open(): void
    {
        $staff = new Staff(['role' => 17, 'status' => 1, 'can_use_timeline_billing' => false]);

        $this->assertTrue($staff->canUseTimelineBilling());
        $this->assertFalse(Staff::canGrantTimelineBillingPermission($staff));
    }

    #[Test]
    public function admin_access_can_use_timeline_billing_without_a_separate_flag(): void
    {
        $staff = new Staff([
            'role' => 16,
            'status' => 1,
            'grant_super_admin_access' => true,
            'can_use_timeline_billing' => false,
        ]);

        $this->assertTrue($staff->canUseTimelineBilling());
        $this->assertFalse(Staff::canGrantTimelineBillingPermission($staff));
    }

    #[Test]
    public function inactive_staff_cannot_use_timeline_billing(): void
    {
        $staff = new Staff([
            'role' => 16,
            'status' => 0,
            'can_use_timeline_billing' => true,
        ]);

        $this->assertFalse($staff->canUseTimelineBilling());
    }
}
