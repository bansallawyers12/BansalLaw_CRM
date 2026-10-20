<?php

namespace Tests\Unit;

use App\Models\Staff;
use App\Models\StaffAccessProfile;
use App\Models\StaffRecordGrant;
use App\Services\CrmAccess\StaffAccessGate;
use App\Services\CrmAccess\StaffRestrictedAccessService;
use App\Support\StaffClientVisibility;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StaffAccessGateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'crm_access.restricted_access.enabled' => true,
            'crm_access.restricted_access.full_access_role_ids' => [1],
            'crm_access.restricted_access.honor_effective_super_admin' => false,
            'crm_access.restricted_access.restricted_role_id' => 18,
            'crm_access.restricted_access.restricted_role_name' => 'Restricted User',
            'crm_access.allocation_enabled' => true,
        ]);
    }

    public function test_super_admin_has_full_crm_access_admin_does_not(): void
    {
        $gate = app(StaffAccessGate::class);

        $this->assertTrue($gate->hasFullCrmAccess(new Staff(['id' => 1, 'role' => 1])));
        $this->assertFalse($gate->hasFullCrmAccess(new Staff(['id' => 2, 'role' => 17])));
        $this->assertFalse($gate->hasFullCrmAccess(new Staff(['id' => 3, 'role' => 12])));
    }

    public function test_restricted_role_is_detected(): void
    {
        $gate = app(StaffAccessGate::class);
        $restricted = new Staff(['id' => 50, 'role' => 18]);

        $this->assertTrue($gate->isRestricted($restricted));
        $this->assertFalse($gate->isRestricted(new Staff(['id' => 1, 'role' => 1])));
    }

    public function test_restricted_user_gets_no_dashboard_widgets_by_default(): void
    {
        $gate = app(StaffAccessGate::class);
        $restricted = new Staff(['id' => 50, 'role' => 18]);

        $this->assertTrue($gate->canViewDashboard($restricted));
        $this->assertFalse($gate->canDashboardWidget($restricted, 'calendar'));
        $this->assertFalse($gate->canDashboardWidget($restricted, 'recent_activity'));
    }

    public function test_exempt_from_allocation_is_super_admin_only_when_feature_on(): void
    {
        $this->assertTrue(StaffClientVisibility::isExemptFromAllocation(new Staff(['id' => 1, 'role' => 1])));
        $this->assertFalse(StaffClientVisibility::isExemptFromAllocation(new Staff(['id' => 2, 'role' => 17])));
    }

    public function test_feature_off_restores_admin_exempt_via_config_roles(): void
    {
        config([
            'crm_access.restricted_access.enabled' => false,
            'crm_access.exempt_role_ids' => [1, 17],
        ]);

        $this->assertTrue(StaffClientVisibility::isExemptFromAllocation(new Staff(['id' => 2, 'role' => 17])));
    }

    public function test_restricted_without_grants_cannot_access_client(): void
    {
        $gate = app(StaffAccessGate::class);
        $restricted = new Staff(['id' => 50, 'role' => 18]);

        $this->assertFalse($gate->canClient($restricted, 999999));
        $this->assertFalse($gate->canLead($restricted, 999999));
        $this->assertFalse($gate->canEmails($restricted, 999999));
    }

    public function test_ensure_restricted_role_helper_still_works(): void
    {
        $role = app(StaffRestrictedAccessService::class)->ensureRestrictedRole();
        $this->assertSame('Restricted User', $role->name);
        $this->assertSame('{}', $role->module_access);
    }

    public function test_restricted_nav_flags_hide_modules_without_grants(): void
    {
        $gate = app(StaffAccessGate::class);
        $restricted = new Staff(['id' => 50, 'role' => 18]);
        $flags = $gate->navFlags($restricted);

        $this->assertTrue($flags['restricted']);
        $this->assertTrue($flags['dashboard']);
        $this->assertTrue($flags['profile']);
        $this->assertFalse($flags['clients']);
        $this->assertFalse($flags['leads']);
        $this->assertFalse($flags['accounts']);
        $this->assertFalse($flags['booking']);
        $this->assertFalse($flags['signatures']);
        $this->assertFalse($flags['tasks']);
        $this->assertFalse($flags['search']);
        $this->assertFalse($flags['admin_console']);
    }

    public function test_restricted_can_access_profile_and_dashboard_routes_only(): void
    {
        $gate = app(StaffAccessGate::class);
        $restricted = new Staff(['id' => 50, 'role' => 18]);

        $this->assertTrue($gate->canAccessRoute($restricted, 'dashboard'));
        $this->assertTrue($gate->canAccessRoute($restricted, 'dashboard.summary'));
        $this->assertTrue($gate->canAccessRoute($restricted, 'my_profile'));
        $this->assertTrue($gate->canAccessRoute($restricted, 'change_password'));
        $this->assertFalse($gate->canAccessRoute($restricted, 'clients.index'));
        $this->assertFalse($gate->canAccessRoute($restricted, 'booking.appointments.index'));
        $this->assertFalse($gate->canAccessRoute($restricted, 'adminconsole.features.matter.index'));
        $this->assertFalse($gate->canAccessRoute($restricted, 'signatures.index'));
    }

    public function test_non_restricted_nav_flags_remain_open(): void
    {
        $gate = app(StaffAccessGate::class);
        $flags = $gate->navFlags(new Staff(['id' => 1, 'role' => 1]));

        $this->assertFalse($flags['restricted']);
        $this->assertTrue($flags['clients']);
        $this->assertTrue($flags['booking']);
        $this->assertTrue($gate->canAccessRoute(new Staff(['id' => 1, 'role' => 1]), 'clients.index'));
    }

    public function test_restricted_dashboard_is_shell_only_by_default(): void
    {
        $gate = app(StaffAccessGate::class);
        $restricted = new Staff(['id' => 50, 'role' => 18]);
        $flags = $gate->dashboardWidgetFlags($restricted);

        $this->assertTrue($flags['restricted']);
        $this->assertTrue($flags['shell_only']);
        $this->assertFalse($flags['calendar']);
        $this->assertFalse($flags['recent_activity']);
        $this->assertFalse($flags['tasks']);
        $this->assertFalse($flags['active_matters']);
        $this->assertFalse($flags['new_client']);
    }

    public function test_super_admin_dashboard_widgets_remain_open(): void
    {
        $gate = app(StaffAccessGate::class);
        $flags = $gate->dashboardWidgetFlags(new Staff(['id' => 1, 'role' => 1]));

        $this->assertFalse($flags['shell_only']);
        $this->assertTrue($flags['calendar']);
        $this->assertTrue($flags['recent_activity']);
        $this->assertTrue($flags['new_client']);
    }

    public function test_view_grant_without_emails_scope_denies_emails(): void
    {
        if (! Schema::hasTable('staff_record_grants') || ! Schema::hasTable('staff')) {
            $this->markTestSkipped('required tables missing');
        }

        $gate = app(StaffAccessGate::class);
        $adminId = 905002;
        $staff = $this->makeEphemeralRestrictedStaff('view-only-emails-test@example.test');

        try {
            StaffRecordGrant::query()->create([
                'staff_id' => (int) $staff->id,
                'resource_type' => StaffRecordGrant::TYPE_CLIENT,
                'resource_id' => $adminId,
                'scopes' => [StaffRecordGrant::SCOPE_VIEW],
                'granted_by_staff_id' => (int) $staff->id,
            ]);

            $this->assertTrue($gate->canClient($staff, $adminId));
            $this->assertFalse($gate->canEmails($staff, $adminId));
        } finally {
            $this->cleanupEphemeralRestrictedStaff((int) $staff->id);
        }
    }

    public function test_emails_scope_on_client_grant_allows_emails(): void
    {
        if (! Schema::hasTable('staff_record_grants') || ! Schema::hasTable('staff')) {
            $this->markTestSkipped('required tables missing');
        }

        $gate = app(StaffAccessGate::class);
        $adminId = 905012;
        $staff = $this->makeEphemeralRestrictedStaff('emails-scope-test@example.test');

        try {
            StaffRecordGrant::query()->create([
                'staff_id' => (int) $staff->id,
                'resource_type' => StaffRecordGrant::TYPE_CLIENT,
                'resource_id' => $adminId,
                'scopes' => [StaffRecordGrant::SCOPE_VIEW, StaffRecordGrant::SCOPE_EMAILS],
                'granted_by_staff_id' => (int) $staff->id,
            ]);

            $this->assertTrue($gate->canEmails($staff, $adminId));
            $this->assertContains($adminId, $gate->allowedAdminIdsWithEmailScope($staff));
        } finally {
            $this->cleanupEphemeralRestrictedStaff((int) $staff->id);
        }
    }

    public function test_staff_record_grant_has_scope_helper(): void
    {
        $viewOnly = new StaffRecordGrant(['scopes' => [StaffRecordGrant::SCOPE_VIEW]]);
        $this->assertTrue($viewOnly->hasScope(StaffRecordGrant::SCOPE_VIEW));
        $this->assertFalse($viewOnly->hasScope(StaffRecordGrant::SCOPE_EMAILS));

        $withEmails = new StaffRecordGrant([
            'scopes' => [StaffRecordGrant::SCOPE_VIEW, StaffRecordGrant::SCOPE_EMAILS],
        ]);
        $this->assertTrue($withEmails->hasScope(StaffRecordGrant::SCOPE_EMAILS));
    }

    public function test_restricted_access_service_grant_and_revoke(): void
    {
        if (! Schema::hasTable('staff_record_grants') || ! Schema::hasTable('staff')) {
            $this->markTestSkipped('required tables missing');
        }

        $svc = app(StaffRestrictedAccessService::class);
        $staff = $this->makeEphemeralRestrictedStaff('grant-revoke-test@example.test');
        $adminId = 905022;

        try {
            $grant = $svc->grantRecord(
                $staff,
                StaffRecordGrant::TYPE_CLIENT,
                $adminId,
                [StaffRecordGrant::SCOPE_VIEW, StaffRecordGrant::SCOPE_EMAILS],
                $staff,
                'step7 test'
            );

            $this->assertNotNull($grant->id);
            $this->assertTrue($grant->hasScope(StaffRecordGrant::SCOPE_EMAILS));
            $this->assertNull($grant->revoked_at);

            $revoked = $svc->revokeGrant($grant, $staff, 'revoked in test');
            $this->assertNotNull($revoked->revoked_at);

            if (Schema::hasTable('staff_access_audit')) {
                $this->assertTrue(
                    \App\Models\StaffAccessAudit::query()
                        ->where('target_staff_id', (int) $staff->id)
                        ->whereIn('action', ['grant_created', 'grant_updated'])
                        ->exists()
                );
                $this->assertTrue(
                    \App\Models\StaffAccessAudit::query()
                        ->where('target_staff_id', (int) $staff->id)
                        ->where('action', 'grant_revoked')
                        ->exists()
                );
            }
        } finally {
            if (Schema::hasTable('staff_access_audit')) {
                \App\Models\StaffAccessAudit::query()->where('target_staff_id', (int) $staff->id)->delete();
            }
            $this->cleanupEphemeralRestrictedStaff((int) $staff->id);
        }
    }

    protected function makeEphemeralRestrictedStaff(string $email): Staff
    {
        $svc = app(StaffRestrictedAccessService::class);
        $role = $svc->ensureRestrictedRole();
        // Test DB may assign Restricted User id=1; keep full-access ids distinct.
        config([
            'crm_access.restricted_access.restricted_role_id' => (int) $role->id,
            'crm_access.restricted_access.full_access_role_ids' => [99999],
        ]);
        Staff::query()->where('email', $email)->delete();

        $staff = Staff::query()->create([
            'first_name' => 'Restricted',
            'last_name' => 'Test',
            'email' => $email,
            'password' => bcrypt('secret'),
            'status' => 1,
            'role' => (int) $role->id,
        ]);

        $svc->ensureRestrictedProfile($staff);

        return $staff->fresh();
    }

    protected function cleanupEphemeralRestrictedStaff(int $staffId): void
    {
        if (Schema::hasTable('staff_access_audit')) {
            \App\Models\StaffAccessAudit::query()
                ->where(function ($q) use ($staffId) {
                    $q->where('target_staff_id', $staffId)
                        ->orWhere('actor_staff_id', $staffId);
                })
                ->delete();
        }
        StaffRecordGrant::query()->where('staff_id', $staffId)->delete();
        StaffAccessProfile::query()->where('staff_id', $staffId)->delete();
        Staff::query()->where('id', $staffId)->delete();
    }
}
