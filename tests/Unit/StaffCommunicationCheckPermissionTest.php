<?php

namespace Tests\Unit;

use App\Models\Staff;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StaffCommunicationCheckPermissionTest extends TestCase
{
    #[Test]
    public function native_super_admin_can_use_without_flag(): void
    {
        $staff = new Staff(['role' => 1, 'status' => 1, 'can_use_communication_check' => false]);

        $this->assertTrue($staff->canUseCommunicationCheck());
        $this->assertTrue(Staff::canGrantCommunicationCheckPermission($staff));
    }

    #[Test]
    public function admin_cannot_use_or_grant_without_flag(): void
    {
        $staff = new Staff(['role' => 17, 'status' => 1, 'can_use_communication_check' => false]);

        $this->assertFalse($staff->canUseCommunicationCheck());
        $this->assertFalse(Staff::canGrantCommunicationCheckPermission($staff));
    }

    #[Test]
    public function regular_staff_with_flag_can_use_but_cannot_grant(): void
    {
        $staff = new Staff([
            'role' => 16,
            'status' => 1,
            'can_use_communication_check' => true,
        ]);

        $this->assertTrue($staff->canUseCommunicationCheck());
        $this->assertFalse(Staff::canGrantCommunicationCheckPermission($staff));
    }

    #[Test]
    public function regular_staff_without_flag_cannot_use_or_grant(): void
    {
        $staff = new Staff([
            'role' => 16,
            'status' => 1,
            'can_use_communication_check' => false,
        ]);

        $this->assertFalse($staff->canUseCommunicationCheck());
        $this->assertFalse(Staff::canGrantCommunicationCheckPermission($staff));
    }
}
