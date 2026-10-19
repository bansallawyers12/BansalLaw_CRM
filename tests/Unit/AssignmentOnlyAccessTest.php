<?php

namespace Tests\Unit;

use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\Staff;
use App\Support\MatterScopedAccess;
use App\Support\StaffClientVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssignmentOnlyAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\DB::table('user_roles')->insertOrIgnore([
            ['id' => 14, 'name' => 'Calling', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 17, 'name' => 'Admin', 'created_at' => now(), 'updated_at' => now()],
        ]);

        config([
            'crm_access.allocation_enabled' => true,
            'crm_access.assignment_only_access_enabled' => true,
            'crm_access.matter_scoped_access_enabled' => true,
            'crm_access.assignment_only_bypass_role_ids' => [1],
            'crm_access.exempt_role_ids' => [1, 17],
        ]);
    }

    #[Test]
    public function admin_role_is_not_allocation_exempt_when_assignment_only_is_on(): void
    {
        $adminRole = Staff::factory()->create(['status' => 1, 'role' => 17]);

        $this->assertFalse(StaffClientVisibility::isExemptFromAllocation($adminRole));
    }

    #[Test]
    public function client_record_owner_without_matter_team_cannot_access_client(): void
    {
        $owner = Staff::factory()->create(['status' => 1, 'role' => 14]);
        $other = Staff::factory()->create(['status' => 1, 'role' => 14]);
        $client = Admin::factory()->create(['type' => 'client', 'user_id' => $owner->id]);

        ClientMatter::create([
            'client_id' => $client->id,
            'matter_status' => 1,
            'client_unique_matter_no' => 'ASN-1',
            'sel_legal_practitioner' => $owner->id,
        ]);

        $this->assertTrue(StaffClientVisibility::canAccessClientOrLead((int) $client->id, $owner));
        $this->assertFalse(StaffClientVisibility::canAccessClientOrLead((int) $client->id, $other));
    }

    #[Test]
    public function lead_owner_can_access_lead_without_matter_team(): void
    {
        $owner = Staff::factory()->create(['status' => 1, 'role' => 14]);
        $lead = Admin::factory()->create(['type' => 'lead', 'user_id' => $owner->id]);

        $this->assertTrue(StaffClientVisibility::canAccessClientOrLead((int) $lead->id, $owner));
    }

    #[Test]
    public function matter_team_member_can_access_client_file_and_that_matter(): void
    {
        $lp = Staff::factory()->create(['status' => 1, 'role' => 14]);
        $pr = Staff::factory()->create(['status' => 1, 'role' => 14]);
        $client = Admin::factory()->create(['type' => 'client']);

        $matter = ClientMatter::create([
            'client_id' => $client->id,
            'matter_status' => 1,
            'client_unique_matter_no' => 'LAB_1',
            'sel_legal_practitioner' => $lp->id,
            'sel_person_responsible' => $pr->id,
        ]);

        $this->assertTrue(StaffClientVisibility::canAccessClientOrLead((int) $client->id, $pr));
        $this->assertTrue(MatterScopedAccess::canAccessClientMatter((int) $matter->id, $pr));
        $this->assertTrue(MatterScopedAccess::canAccessMatterByReference((int) $client->id, 'LAB_1', $pr));
        $this->assertContains((int) $matter->id, MatterScopedAccess::accessibleMatterIdsForClient((int) $client->id, $pr));
    }
}
