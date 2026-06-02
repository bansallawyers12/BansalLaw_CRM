<?php

namespace Tests\Unit;

use App\Models\Staff;
use App\Support\StaffClientVisibility;
use Tests\TestCase;

class StaffClientVisibilityAllocationTest extends TestCase
{
    protected function tearDown(): void
    {
        config(['crm_access.allocation_enabled' => true]);
        parent::tearDown();
    }

    public function test_allocation_disabled_treats_pa_as_unrestricted(): void
    {
        config(['crm_access.allocation_enabled' => false]);

        $staff = new Staff(['role' => 13]);

        $this->assertFalse(StaffClientVisibility::isRestrictedPersonAssisting($staff));
        $this->assertNull(StaffClientVisibility::personAssistingStaffIdOrNull($staff));
    }

    public function test_allocation_enabled_keeps_pa_restricted(): void
    {
        config(['crm_access.allocation_enabled' => true]);

        $staff = new Staff(['role' => 13]);
        $staff->id = 99;

        $this->assertTrue(StaffClientVisibility::isRestrictedPersonAssisting($staff));
        $this->assertSame(99, StaffClientVisibility::personAssistingStaffIdOrNull($staff));
    }

    public function test_allocation_disabled_hides_cross_access_ui_flags(): void
    {
        config(['crm_access.allocation_enabled' => false]);

        $staff = new Staff(['role' => 13, 'quick_access_enabled' => true]);

        $this->assertSame(
            ['show_quick' => false, 'show_supervisor' => false],
            StaffClientVisibility::crossAccessUiFlags($staff)
        );
    }

    public function test_allocation_disabled_unlocks_global_search_items(): void
    {
        config(['crm_access.allocation_enabled' => false]);

        $staff = new Staff(['role' => 13]);
        $item = [
            'id' => 'abc/Client',
            'name' => 'Test Client',
            'email' => 'test@example.com',
            'cid' => 12345,
        ];

        $enriched = StaffClientVisibility::enrichGlobalSearchItem($item, 'client', $staff);

        $this->assertFalse($enriched['locked']);
        $this->assertSame(['show_quick' => false, 'show_supervisor' => false], $enriched['access_ui']);
    }

    public function test_super_admin_only_locked_client_check_is_independent_of_allocation_flag(): void
    {
        config([
            'crm_access.allocation_enabled' => false,
            'crm.super_admin_only_client_file_ids' => ['LOCKED123'],
        ]);

        $this->assertTrue(
            StaffClientVisibility::isSuperAdminOnlyLockedClient('client', 'locked123')
        );
    }
}
