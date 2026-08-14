<?php

namespace Tests\Unit;

use App\Models\Staff;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StaffPauseMailboxInboxSyncPermissionTest extends TestCase
{
    #[Test]
    public function native_super_admin_can_pause_mailbox_sync_without_flag(): void
    {
        $staff = new Staff(['role' => 1, 'status' => 1, 'can_pause_mailbox_inbox_sync' => false]);

        $this->assertTrue($staff->canPauseMailboxInboxSync());
        $this->assertTrue(Staff::canGrantPauseMailboxInboxSyncPermission($staff));
    }

    #[Test]
    public function admin_cannot_pause_or_grant_without_flag(): void
    {
        $staff = new Staff(['role' => 17, 'status' => 1, 'can_pause_mailbox_inbox_sync' => false]);

        $this->assertFalse($staff->canPauseMailboxInboxSync());
        $this->assertFalse(Staff::canGrantPauseMailboxInboxSyncPermission($staff));
    }

    #[Test]
    public function regular_staff_with_flag_can_pause_but_cannot_grant(): void
    {
        $staff = new Staff([
            'role' => 16,
            'status' => 1,
            'can_pause_mailbox_inbox_sync' => true,
        ]);

        $this->assertTrue($staff->canPauseMailboxInboxSync());
        $this->assertFalse(Staff::canGrantPauseMailboxInboxSyncPermission($staff));
    }

    #[Test]
    public function regular_staff_without_flag_cannot_pause_or_grant(): void
    {
        $staff = new Staff([
            'role' => 16,
            'status' => 1,
            'can_pause_mailbox_inbox_sync' => false,
        ]);

        $this->assertFalse($staff->canPauseMailboxInboxSync());
        $this->assertFalse(Staff::canGrantPauseMailboxInboxSyncPermission($staff));
    }
}
