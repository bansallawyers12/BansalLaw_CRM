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
        $this->assertArrayHasKey('yesterday6am', $ranges);
        $this->assertArrayNotHasKey('from10aug', $ranges);
        $this->assertArrayNotHasKey('delete_unassigned_db', $ranges);
        $this->assertArrayNotHasKey('delete_unassigned_db_zoho', $ranges);
        $this->assertArrayHasKey('full', $ranges);
        $this->assertSame(
            IncomingEmailSyncService::yesterday6amSyncRangeLabel(),
            $ranges['yesterday6am']
        );
        $this->assertSame(
            IncomingEmailSyncService::adminUnassignedSyncRangeOptions(),
            $ranges
        );
    }

    #[Test]
    public function yesterday_6am_sync_range_resolves_to_yesterday_morning(): void
    {
        $timezone = (string) config('app.timezone', 'UTC');
        $now = now($timezone);
        $expected = $now->copy()->subDay()->startOfDay()->setTime(6, 0, 0);

        $this->assertTrue(
            IncomingEmailSyncService::resolveSyncSince('yesterday6am')->eq($expected)
        );
        $this->assertTrue(
            IncomingEmailSyncService::resolveYesterday6amSyncSince($now)->eq($expected)
        );
    }

    #[Test]
    public function from_10_aug_sync_range_resolves_to_configured_floor(): void
    {
        config(['imap_sync.unassigned_available_from' => '2026-08-10']);
        $timezone = (string) config('app.timezone', 'UTC');
        $expected = \Carbon\Carbon::parse('2026-08-10', $timezone)->startOfDay();

        $this->assertTrue(
            IncomingEmailSyncService::resolveSyncSince('from10aug')->eq($expected)
        );
        $this->assertTrue(
            IncomingEmailSyncService::resolveFrom10AugSyncSince()->eq($expected)
        );
        $this->assertSame(
            'From 10 Aug 2026 (delete older unassigned)',
            IncomingEmailSyncService::from10AugSyncRangeLabel()
        );
        $this->assertStringContainsString(
            'COALESCE(fetch_mail_sent_time, received_date, created_at)',
            IncomingEmailSyncService::syncedMailEffectiveDateSql()
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
    public function syncable_mailbox_helpers_require_zoho_password_scope(): void
    {
        $this->assertTrue(
            method_exists(IncomingEmailSyncService::class, 'applyMailboxHasZohoPasswordScope')
        );
        $this->assertTrue(
            method_exists(IncomingEmailSyncService::class, 'applySyncedMailboxHasZohoPasswordFilter')
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
