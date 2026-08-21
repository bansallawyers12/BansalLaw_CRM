<?php

namespace Tests\Unit;

use App\Models\Staff;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StaffAssignEmailsBySubjectPermissionTest extends TestCase
{
    #[Test]
    public function native_super_admin_can_assign_by_subject_without_flag(): void
    {
        $staff = new Staff(['role' => 1, 'status' => 1, 'can_assign_emails_by_subject' => false]);

        $this->assertTrue($staff->canAssignEmailsBySubject());
        $this->assertTrue(Staff::canGrantAssignEmailsBySubjectPermission($staff));
    }

    #[Test]
    public function admin_cannot_assign_or_grant_without_flag(): void
    {
        $staff = new Staff(['role' => 17, 'status' => 1, 'can_assign_emails_by_subject' => false]);

        $this->assertFalse($staff->canAssignEmailsBySubject());
        $this->assertFalse(Staff::canGrantAssignEmailsBySubjectPermission($staff));
    }

    #[Test]
    public function regular_staff_with_flag_can_assign_but_cannot_grant(): void
    {
        $staff = new Staff([
            'role' => 16,
            'status' => 1,
            'can_assign_emails_by_subject' => true,
        ]);

        $this->assertTrue($staff->canAssignEmailsBySubject());
        $this->assertFalse(Staff::canGrantAssignEmailsBySubjectPermission($staff));
    }

    #[Test]
    public function regular_staff_without_flag_cannot_assign_or_grant(): void
    {
        $staff = new Staff([
            'role' => 16,
            'status' => 1,
            'can_assign_emails_by_subject' => false,
        ]);

        $this->assertFalse($staff->canAssignEmailsBySubject());
        $this->assertFalse(Staff::canGrantAssignEmailsBySubjectPermission($staff));
    }

    #[Test]
    public function admin_with_flag_can_assign_but_still_cannot_grant(): void
    {
        $staff = new Staff([
            'role' => 17,
            'status' => 1,
            'can_assign_emails_by_subject' => true,
        ]);

        $this->assertTrue($staff->canAssignEmailsBySubject());
        $this->assertFalse(Staff::canGrantAssignEmailsBySubjectPermission($staff));
    }
}
