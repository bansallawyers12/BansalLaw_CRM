<?php

namespace Tests\Unit;

use App\Models\Staff;
use App\Services\EmailSync\IncomingEmailSyncService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SyncedInboxFullMailboxAccessTest extends TestCase
{
    #[Test]
    public function flagged_staff_receive_admin_sync_range_options(): void
    {
        $staff = new Staff([
            'role' => 16,
            'status' => 1,
            'can_view_all_synced_inbox_mail' => true,
        ]);

        $ranges = IncomingEmailSyncService::syncRangeOptionsForUnassignedTab($staff);

        $this->assertArrayHasKey('10min', $ranges);
        $this->assertArrayHasKey('full', $ranges);
        $this->assertSame(
            IncomingEmailSyncService::adminUnassignedSyncRangeOptions(),
            $ranges
        );
    }

    #[Test]
    public function regular_staff_only_receive_today_sync_range(): void
    {
        $staff = new Staff([
            'role' => 16,
            'status' => 1,
            'can_view_all_synced_inbox_mail' => false,
        ]);

        $ranges = IncomingEmailSyncService::syncRangeOptionsForUnassignedTab($staff);

        $this->assertSame(
            ['today' => IncomingEmailSyncService::todaySyncRangeLabel()],
            $ranges
        );
    }

    #[Test]
    public function native_super_admin_receives_admin_sync_range_options(): void
    {
        $staff = new Staff([
            'role' => 1,
            'status' => 1,
            'can_view_all_synced_inbox_mail' => false,
        ]);

        $ranges = IncomingEmailSyncService::syncRangeOptionsForUnassignedTab($staff);

        $this->assertSame(
            IncomingEmailSyncService::adminUnassignedSyncRangeOptions(),
            $ranges
        );
    }
}
