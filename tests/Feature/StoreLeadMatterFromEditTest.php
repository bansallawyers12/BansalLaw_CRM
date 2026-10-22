<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\Matter;
use App\Models\Staff;
use App\Models\Workflow;
use App\Models\WorkflowStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StoreLeadMatterFromEditTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function super_admin_can_create_matter_for_lead_via_edit_modal_endpoint(): void
    {
        $staff = Staff::factory()->superAdmin()->create(['status' => 1]);
        $practitioner = Staff::factory()->create(['status' => 1, 'first_name' => 'Ajay', 'last_name' => 'Bansal']);

        $workflow = Workflow::query()->create(['name' => 'Family', 'status' => 1]);
        WorkflowStage::query()->create([
            'workflow_id' => $workflow->id,
            'name' => 'New',
            'sort_order' => 1,
        ]);

        $matterType = Matter::query()->create([
            'title' => 'Family Law',
            'nick_name' => 'FAM',
            'stream' => 'family',
            'status' => 1,
            'workflow_id' => $workflow->id,
        ]);

        $lead = Admin::factory()->create([
            'type' => 'lead',
            'lead_status' => 'engaged',
            'status' => 1,
            'client_id' => 'KABI2600249',
        ]);

        $this->actingAs($staff, 'admin')
            ->postJson(route('clients.storeLeadMatterFromEdit'), [
                'client_id' => $lead->id,
                'matter_id' => $matterType->id,
                'legal_practitioner' => $practitioner->id,
                'our_party_role' => 'respondent',
                'opposing_parties_json' => '[]',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('client_matters', [
            'client_id' => $lead->id,
            'sel_matter_id' => $matterType->id,
            'matter_status' => 1,
        ]);

        $this->assertSame('client', Admin::query()->find($lead->id)?->type);
    }

    #[Test]
    public function matter_reference_uses_generate_unique_matter_number(): void
    {
        $staff = Staff::factory()->superAdmin()->create(['status' => 1]);
        $practitioner = Staff::factory()->create(['status' => 1]);

        $workflow = Workflow::query()->create(['name' => 'General', 'status' => 1]);
        WorkflowStage::query()->create([
            'workflow_id' => $workflow->id,
            'name' => 'New',
            'sort_order' => 1,
        ]);

        $matterType = Matter::query()->create([
            'title' => 'Civil Law',
            'nick_name' => 'CIV',
            'stream' => 'general',
            'status' => 1,
            'workflow_id' => $workflow->id,
        ]);

        $lead = Admin::factory()->create(['type' => 'lead', 'status' => 1]);

        ClientMatter::query()->create([
            'client_id' => $lead->id,
            'sel_matter_id' => $matterType->id,
            'client_unique_matter_no' => 'CIV_1',
            'matter_status' => 0,
        ]);

        $response = $this->actingAs($staff, 'admin')
            ->postJson(route('clients.storeLeadMatterFromEdit'), [
                'client_id' => $lead->id,
                'matter_id' => $matterType->id,
                'legal_practitioner' => $practitioner->id,
                'our_party_role' => 'applicant',
                'opposing_parties_json' => '[]',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $ref = $response->json('matter.client_unique_matter_no');
        $this->assertSame('CIV_2', $ref);
    }
}
