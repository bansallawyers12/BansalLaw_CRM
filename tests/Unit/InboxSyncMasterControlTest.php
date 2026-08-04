<?php

namespace Tests\Unit;

use App\Models\Staff;
use App\Services\EmailSync\InboxSyncMasterControl;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InboxSyncMasterControlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget(InboxSyncMasterControl::CACHE_KEY);
        $path = storage_path('app/' . InboxSyncMasterControl::FILE_RELATIVE);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    protected function tearDown(): void
    {
        Cache::forget(InboxSyncMasterControl::CACHE_KEY);
        $path = storage_path('app/' . InboxSyncMasterControl::FILE_RELATIVE);
        if (is_file($path)) {
            @unlink($path);
        }
        parent::tearDown();
    }

    #[Test]
    public function defaults_to_config_when_no_override(): void
    {
        config(['imap_sync.enabled' => true]);
        $this->assertTrue(InboxSyncMasterControl::isEnabled());

        config(['imap_sync.enabled' => false]);
        $this->assertFalse(InboxSyncMasterControl::isEnabled());
    }

    #[Test]
    public function super_admin_override_disables_immediately(): void
    {
        config(['imap_sync.enabled' => true]);
        $admin = new Staff(['id' => 1, 'role' => 1, 'status' => 1]);

        InboxSyncMasterControl::setEnabled(false, $admin);
        $this->assertTrue(InboxSyncMasterControl::isDisabled());
        $this->assertFalse(InboxSyncMasterControl::isEnabled());

        InboxSyncMasterControl::setEnabled(true, $admin);
        $this->assertTrue(InboxSyncMasterControl::isEnabled());
    }

    #[Test]
    public function only_permanent_super_admin_can_control(): void
    {
        $super = new Staff(['role' => 1, 'status' => 1]);
        $staff = new Staff(['role' => 5, 'status' => 1, 'grant_super_admin_access' => 0]);

        $this->assertTrue(InboxSyncMasterControl::canControl($super));
        $this->assertFalse(InboxSyncMasterControl::canControl($staff));
    }
}
