<?php

namespace Tests\Unit;

use App\Models\ClientAccessGrant;
use App\Models\ClientMatter;
use App\Models\Staff;
use App\Support\MatterScopedAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MatterScopedAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\DB::table('user_roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'Admin', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 14, 'name' => 'Calling', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    #[Test]
    public function assigned_staff_can_access_matter(): void
    {
        config(['crm_access.allocation_enabled' => true, 'crm_access.matter_scoped_access_enabled' => true]);

        $staff = Staff::factory()->create(['status' => 1, 'role' => 14]);
        $client = \App\Models\Admin::factory()->create(['type' => 'client']);

        $matter = ClientMatter::create([
            'client_id' => $client->id,
            'matter_status' => 1,
            'client_unique_matter_no' => 'MAT-A',
            'sel_person_responsible' => $staff->id,
        ]);

        $this->assertTrue(MatterScopedAccess::canAccessClientMatter((int) $matter->id, $staff));
    }

    #[Test]
    public function unassigned_staff_cannot_access_matter_without_grant(): void
    {
        config(['crm_access.allocation_enabled' => true, 'crm_access.matter_scoped_access_enabled' => true]);

        $assigned = Staff::factory()->create(['status' => 1, 'role' => 14]);
        $other = Staff::factory()->create(['status' => 1, 'role' => 14]);
        $client = \App\Models\Admin::factory()->create(['type' => 'client', 'user_id' => $assigned->id]);

        $matter = ClientMatter::create([
            'client_id' => $client->id,
            'matter_status' => 1,
            'client_unique_matter_no' => 'MAT-B',
            'sel_person_responsible' => $assigned->id,
        ]);

        $this->assertFalse(MatterScopedAccess::canAccessClientMatter((int) $matter->id, $other));
    }

    #[Test]
    public function temporary_matter_grant_allows_access(): void
    {
        config(['crm_access.allocation_enabled' => true, 'crm_access.matter_scoped_access_enabled' => true]);

        $assigned = Staff::factory()->create(['status' => 1, 'role' => 14]);
        $visitor = Staff::factory()->create(['status' => 1, 'role' => 14]);
        $client = \App\Models\Admin::factory()->create(['type' => 'client', 'user_id' => $assigned->id]);

        $matter = ClientMatter::create([
            'client_id' => $client->id,
            'matter_status' => 1,
            'client_unique_matter_no' => 'MAT-C',
            'sel_person_responsible' => $assigned->id,
        ]);

        ClientAccessGrant::query()->create([
            'staff_id' => $visitor->id,
            'admin_id' => $client->id,
            'record_type' => 'client',
            'grant_type' => MatterScopedAccess::GRANT_TYPE_MATTER_TEMP,
            'access_type' => MatterScopedAccess::ACCESS_TYPE_MATTER,
            'status' => 'active',
            'requester_note' => MatterScopedAccess::matterGrantNote((int) $matter->id),
            'requested_at' => now(),
            'starts_at' => now(),
            'ends_at' => now()->addHour(),
        ]);

        $this->assertTrue(MatterScopedAccess::canAccessClientMatter((int) $matter->id, $visitor));
    }

    #[Test]
    public function active_temporary_grants_list_includes_only_current_matter(): void
    {
        config(['crm_access.allocation_enabled' => true, 'crm_access.matter_scoped_access_enabled' => true]);

        $client = \App\Models\Admin::factory()->create(['type' => 'client']);
        $visitor = Staff::factory()->create(['status' => 1, 'role' => 14]);

        $matterA = ClientMatter::create([
            'client_id' => $client->id,
            'matter_status' => 1,
            'client_unique_matter_no' => 'MAT-D',
        ]);
        $matterB = ClientMatter::create([
            'client_id' => $client->id,
            'matter_status' => 1,
            'client_unique_matter_no' => 'MAT-E',
        ]);

        ClientAccessGrant::query()->create([
            'staff_id' => $visitor->id,
            'admin_id' => $client->id,
            'record_type' => 'client',
            'grant_type' => MatterScopedAccess::GRANT_TYPE_MATTER_TEMP,
            'access_type' => MatterScopedAccess::ACCESS_TYPE_MATTER,
            'status' => 'active',
            'requester_note' => MatterScopedAccess::matterGrantNote((int) $matterA->id),
            'requested_at' => now(),
            'starts_at' => now(),
            'ends_at' => now()->addHour(),
        ]);

        $grants = MatterScopedAccess::activeTemporaryGrantsForMatter((int) $matterA->id);
        $this->assertCount(1, $grants);
        $this->assertCount(0, MatterScopedAccess::activeTemporaryGrantsForMatter((int) $matterB->id));
    }

    #[Test]
    public function only_super_admin_role_may_manage_matter_access_grants(): void
    {
        $superAdmin = Staff::factory()->create(['status' => 1, 'role' => 1]);
        $other = Staff::factory()->create(['status' => 1, 'role' => 14, 'grant_super_admin_access' => 1]);
        $delegated = Staff::factory()->create([
            'status' => 1,
            'role' => 14,
            'can_manage_matter_team_access' => true,
        ]);

        $this->assertTrue(MatterScopedAccess::canManageTemporaryGrants($superAdmin));
        $this->assertFalse(MatterScopedAccess::canManageTemporaryGrants($other));
        $this->assertTrue(MatterScopedAccess::canManageTemporaryGrants($delegated));
    }

    #[Test]
    public function matter_temp_grant_allows_client_file_visibility(): void
    {
        config(['crm_access.allocation_enabled' => true, 'crm_access.matter_scoped_access_enabled' => true]);

        $assigned = Staff::factory()->create(['status' => 1, 'role' => 14]);
        $visitor = Staff::factory()->create(['status' => 1, 'role' => 14]);
        $client = \App\Models\Admin::factory()->create(['type' => 'client', 'user_id' => $assigned->id]);

        $matter = ClientMatter::create([
            'client_id' => $client->id,
            'matter_status' => 1,
            'client_unique_matter_no' => 'MAT-F',
            'sel_person_responsible' => $assigned->id,
        ]);

        ClientAccessGrant::query()->create([
            'staff_id' => $visitor->id,
            'admin_id' => $client->id,
            'record_type' => 'client',
            'grant_type' => MatterScopedAccess::GRANT_TYPE_MATTER_TEMP,
            'access_type' => MatterScopedAccess::ACCESS_TYPE_MATTER,
            'status' => 'active',
            'requester_note' => MatterScopedAccess::matterGrantNote((int) $matter->id, 'person_responsible'),
            'requested_at' => now(),
            'starts_at' => now(),
            'ends_at' => now()->addHour(),
        ]);

        $this->assertTrue(MatterScopedAccess::staffHasActiveMatterTempGrantForClient((int) $visitor->id, (int) $client->id));
        $this->assertTrue(\App\Support\StaffClientVisibility::canAccessClientOrLead((int) $client->id, $visitor));
        $this->assertTrue(MatterScopedAccess::canAccessClientMatter((int) $matter->id, $visitor));
    }

    #[Test]
    public function purge_staff_from_matter_revokes_grants_and_blocks_access(): void
    {
        config(['crm_access.allocation_enabled' => true, 'crm_access.matter_scoped_access_enabled' => true]);

        $assigned = Staff::factory()->create(['status' => 1, 'role' => 14]);
        $visitor = Staff::factory()->create(['status' => 1, 'role' => 14]);
        $client = \App\Models\Admin::factory()->create(['type' => 'client', 'user_id' => $assigned->id]);

        $matter = ClientMatter::create([
            'client_id' => $client->id,
            'matter_status' => 1,
            'client_unique_matter_no' => 'MAT-G',
            'sel_person_responsible' => $assigned->id,
            'sel_person_assisting' => $visitor->id,
        ]);

        ClientAccessGrant::query()->create([
            'staff_id' => $visitor->id,
            'admin_id' => $client->id,
            'record_type' => 'client',
            'grant_type' => MatterScopedAccess::GRANT_TYPE_MATTER_TEMP,
            'access_type' => MatterScopedAccess::ACCESS_TYPE_MATTER,
            'status' => 'active',
            'requester_note' => MatterScopedAccess::matterGrantNote((int) $matter->id, 'person_assisting'),
            'requested_at' => now(),
            'starts_at' => now(),
            'ends_at' => now()->addDay(),
        ]);

        $this->assertTrue(MatterScopedAccess::canAccessClientMatter((int) $matter->id, $visitor));

        MatterScopedAccess::purgeStaffFromMatter((int) $matter->id, (int) $visitor->id, 'test purge');

        $matter->refresh();
        $this->assertNotSame((int) $visitor->id, (int) ($matter->sel_person_assisting ?? 0));
        $this->assertFalse(MatterScopedAccess::canAccessClientMatter((int) $matter->id, $visitor));
        $this->assertFalse(MatterScopedAccess::staffHasActiveMatterTempGrantForClient((int) $visitor->id, (int) $client->id));
    }

    #[Test]
    public function client_record_owner_does_not_bypass_matter_team_without_assignment(): void
    {
        config(['crm_access.allocation_enabled' => true, 'crm_access.matter_scoped_access_enabled' => true]);

        $owner = Staff::factory()->create(['status' => 1, 'role' => 14]);
        $lp = Staff::factory()->create(['status' => 1, 'role' => 14]);
        $client = \App\Models\Admin::factory()->create(['type' => 'client', 'user_id' => $owner->id]);

        $matter = ClientMatter::create([
            'client_id' => $client->id,
            'matter_status' => 1,
            'client_unique_matter_no' => 'MAT-H',
            'sel_legal_practitioner' => $lp->id,
        ]);

        $this->assertFalse(MatterScopedAccess::canAccessClientMatter((int) $matter->id, $owner));
        $this->assertTrue(MatterScopedAccess::canAccessClientMatter((int) $matter->id, $lp));
    }

    #[Test]
    public function allocation_exempt_role_still_requires_matter_team_or_grant(): void
    {
        config([
            'crm_access.allocation_enabled' => true,
            'crm_access.matter_scoped_access_enabled' => true,
            'crm_access.exempt_role_ids' => [1, 17],
            'crm_access.matter_scope_bypass_role_ids' => [1],
        ]);

        \Illuminate\Support\Facades\DB::table('user_roles')->insertOrIgnore([
            ['id' => 17, 'name' => 'Exempt', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $lp = Staff::factory()->create(['status' => 1, 'role' => 14]);
        $exempt = Staff::factory()->create(['status' => 1, 'role' => 17]);
        $client = \App\Models\Admin::factory()->create(['type' => 'client']);

        $matter = ClientMatter::create([
            'client_id' => $client->id,
            'matter_status' => 1,
            'client_unique_matter_no' => 'MAT-EXEMPT',
            'sel_legal_practitioner' => $lp->id,
        ]);

        $this->assertFalse(MatterScopedAccess::canAccessClientMatter((int) $matter->id, $exempt));
        $this->assertFalse(MatterScopedAccess::canAccessMatterByReference((int) $client->id, 'MAT-EXEMPT', $exempt));
    }

    #[Test]
    public function purge_clears_legacy_matter_user_id_for_staff(): void
    {
        config(['crm_access.allocation_enabled' => true, 'crm_access.matter_scoped_access_enabled' => true]);

        $staff = Staff::factory()->create(['status' => 1, 'role' => 14]);
        $client = \App\Models\Admin::factory()->create(['type' => 'client']);

        $matter = ClientMatter::create([
            'client_id' => $client->id,
            'matter_status' => 1,
            'client_unique_matter_no' => 'MAT-UID',
            'user_id' => $staff->id,
        ]);

        MatterScopedAccess::purgeStaffFromMatter((int) $matter->id, (int) $staff->id, 'test');

        $matter->refresh();
        $this->assertNull($matter->user_id);
    }
}
