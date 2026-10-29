<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\Note;
use App\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MatterTaskAssignmentAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Auth::guard('admin')->logout();

        \Illuminate\Support\Facades\DB::table('user_roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'Super Admin', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 16, 'name' => 'Solicitor', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 17, 'name' => 'Admin', 'created_at' => now(), 'updated_at' => now()],
        ]);

        config([
            'crm_access.allocation_enabled' => true,
            'crm_access.assignment_only_access_enabled' => true,
            'crm_access.matter_scoped_access_enabled' => true,
            'crm_access.exempt_role_ids' => [1, 17],
            'crm_access.matter_scope_bypass_role_ids' => [1],
        ]);
    }

    #[Test]
    public function staff_not_on_the_matter_cannot_tag_someone_on_that_matter(): void
    {
        $client = Admin::factory()->create(['type' => 'client', 'is_archived' => 0]);
        $michael = Staff::factory()->create([
            'status' => 1,
            'role' => 17,
            'first_name' => 'Michael',
            'last_name' => 'Saleh',
        ]);
        $ajay = Staff::factory()->create([
            'status' => 1,
            'role' => 16,
            'first_name' => 'Ajay',
            'last_name' => 'Bansal',
        ]);

        ClientMatter::create([
            'client_id' => $client->id,
            'matter_status' => 1,
            'client_unique_matter_no' => 'FAM_1',
            'sel_person_responsible' => $michael->id,
        ]);
        $otherMatter = ClientMatter::create([
            'client_id' => $client->id,
            'matter_status' => 1,
            'client_unique_matter_no' => 'CRM_2',
            'sel_legal_practitioner' => $ajay->id,
        ]);

        $this->actingAs($michael, 'admin');

        $this->postJson(route('clients.tasks.personal.store'), [
            'client_id' => $client->id,
            'matter_id' => $otherMatter->id,
            'description' => 'Please review this file',
            'rem_cat' => [$ajay->id],
            'task_group' => 'Review',
            'followup_datetime' => now()->addDay()->toDateString(),
        ])->assertForbidden()
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('notes', [
            'client_id' => $client->id,
            'matter_id' => $otherMatter->id,
            'user_id' => $michael->id,
            'assigned_to' => $ajay->id,
        ]);
    }

    #[Test]
    public function staff_on_the_matter_can_still_assign_a_task(): void
    {
        $client = Admin::factory()->create(['type' => 'client', 'is_archived' => 0]);
        $michael = Staff::factory()->create([
            'status' => 1,
            'role' => 17,
            'first_name' => 'Michael',
            'last_name' => 'Saleh',
        ]);
        $ajay = Staff::factory()->create([
            'status' => 1,
            'role' => 16,
            'first_name' => 'Ajay',
            'last_name' => 'Bansal',
        ]);

        $matter = ClientMatter::create([
            'client_id' => $client->id,
            'matter_status' => 1,
            'client_unique_matter_no' => 'FAM_1',
            'sel_person_responsible' => $michael->id,
            'sel_legal_practitioner' => $ajay->id,
        ]);

        $this->actingAs($michael, 'admin');

        $this->postJson(route('clients.tasks.personal.store'), [
            'client_id' => $client->id,
            'matter_id' => $matter->id,
            'description' => 'Please review this file',
            'rem_cat' => [$ajay->id],
            'task_group' => 'Review',
            'followup_datetime' => now()->addDay()->toDateString(),
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->assertTrue(Note::query()
            ->where('client_id', $client->id)
            ->where('matter_id', $matter->id)
            ->where('user_id', $michael->id)
            ->where('assigned_to', $ajay->id)
            ->exists());
    }

    #[Test]
    public function matter_task_list_is_closed_when_staff_is_not_on_that_matter(): void
    {
        $client = Admin::factory()->create(['type' => 'client', 'is_archived' => 0]);
        $michael = Staff::factory()->create([
            'status' => 1,
            'role' => 17,
            'first_name' => 'Michael',
            'last_name' => 'Saleh',
        ]);
        $ajay = Staff::factory()->create([
            'status' => 1,
            'role' => 16,
            'first_name' => 'Ajay',
            'last_name' => 'Bansal',
        ]);

        ClientMatter::create([
            'client_id' => $client->id,
            'matter_status' => 1,
            'client_unique_matter_no' => 'FAM_1',
            'sel_person_responsible' => $michael->id,
        ]);
        $otherMatter = ClientMatter::create([
            'client_id' => $client->id,
            'matter_status' => 1,
            'client_unique_matter_no' => 'CRM_2',
            'sel_legal_practitioner' => $ajay->id,
        ]);

        $this->actingAs($michael, 'admin');

        $this->postJson(route('clients.matterTask.store'), [
            'client_id' => $client->id,
            'matter_id' => $otherMatter->id,
            'title' => 'Off-matter task',
            'due_date' => now()->addDays(3)->toDateString(),
        ])->assertForbidden();
    }
}
