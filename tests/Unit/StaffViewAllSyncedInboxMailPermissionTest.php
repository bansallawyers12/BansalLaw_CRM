<?php

namespace Tests\Unit;

use App\Models\Staff;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StaffViewAllSyncedInboxMailPermissionTest extends TestCase
{
    #[Test]
    public function native_super_admin_can_view_all_synced_inbox_mail_without_flag(): void
    {
        $staff = new Staff(['role' => 1, 'status' => 1, 'can_view_all_synced_inbox_mail' => false]);

        $this->assertTrue($staff->canViewAllSyncedInboxMail());
        $this->assertTrue($staff->canSyncInboxEmails());
        $this->assertTrue(Staff::canGrantViewAllSyncedInboxMailPermission($staff));
    }

    #[Test]
    public function admin_cannot_view_all_synced_inbox_mail_without_flag_and_cannot_grant(): void
    {
        $staff = new Staff(['role' => 17, 'status' => 1, 'can_view_all_synced_inbox_mail' => false]);

        $this->assertFalse($staff->canViewAllSyncedInboxMail());
        $this->assertTrue($staff->canSyncInboxEmails());
        $this->assertFalse(Staff::canGrantViewAllSyncedInboxMailPermission($staff));
    }

    #[Test]
    public function regular_staff_without_flag_cannot_view_all_or_grant(): void
    {
        $staff = new Staff([
            'role' => 16,
            'status' => 1,
            'can_view_all_synced_inbox_mail' => false,
            'can_sync_inbox_emails' => false,
        ]);

        $this->assertFalse($staff->canViewAllSyncedInboxMail());
        $this->assertFalse($staff->canSyncInboxEmails());
        $this->assertFalse(Staff::canGrantViewAllSyncedInboxMailPermission($staff));
    }

    #[Test]
    public function regular_staff_with_flag_can_view_all_and_sync_but_cannot_grant(): void
    {
        $staff = new Staff([
            'role' => 16,
            'status' => 1,
            'can_view_all_synced_inbox_mail' => true,
            'can_sync_inbox_emails' => false,
        ]);

        $this->assertTrue($staff->canViewAllSyncedInboxMail());
        $this->assertTrue($staff->canSyncInboxEmails());
        $this->assertFalse(Staff::canGrantViewAllSyncedInboxMailPermission($staff));
    }

    #[Test]
    public function admin_with_flag_can_view_all_but_still_cannot_grant(): void
    {
        $staff = new Staff([
            'role' => 17,
            'status' => 1,
            'can_view_all_synced_inbox_mail' => true,
        ]);

        $this->assertTrue($staff->canViewAllSyncedInboxMail());
        $this->assertTrue($staff->canSyncInboxEmails());
        $this->assertFalse(Staff::canGrantViewAllSyncedInboxMailPermission($staff));
    }
}
