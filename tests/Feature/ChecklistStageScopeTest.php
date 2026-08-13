<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\Staff;
use App\Models\Workflow;
use App\Models\WorkflowStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ChecklistStageScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Auth::guard('admin')->logout();

        \Illuminate\Support\Facades\DB::table('user_roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'Admin', 'created_at' => now(), 'updated_at' => now()],
        ]);

        if (\Illuminate\Support\Facades\Schema::hasTable('cp_doc_checklists')) {
            \Illuminate\Support\Facades\Schema::table('cp_doc_checklists', function (\Illuminate\Database\Schema\Blueprint $table) {
                if (!\Illuminate\Support\Facades\Schema::hasColumn('cp_doc_checklists', 'client_matter_id')) {
                    $table->unsignedBigInteger('client_matter_id')->nullable();
                }
                if (!\Illuminate\Support\Facades\Schema::hasColumn('cp_doc_checklists', 'client_id')) {
                    $table->unsignedBigInteger('client_id')->nullable();
                }
                if (!\Illuminate\Support\Facades\Schema::hasColumn('cp_doc_checklists', 'wf_stage')) {
                    $table->string('wf_stage', 255)->nullable();
                }
                if (!\Illuminate\Support\Facades\Schema::hasColumn('cp_doc_checklists', 'wf_stage_id')) {
                    $table->unsignedBigInteger('wf_stage_id')->nullable();
                }
                if (!\Illuminate\Support\Facades\Schema::hasColumn('cp_doc_checklists', 'cp_checklist_name')) {
                    $table->string('cp_checklist_name', 255)->nullable();
                }
                if (!\Illuminate\Support\Facades\Schema::hasColumn('cp_doc_checklists', 'description')) {
                    $table->text('description')->nullable();
                }
                if (!\Illuminate\Support\Facades\Schema::hasColumn('cp_doc_checklists', 'allow_client')) {
                    $table->integer('allow_client')->default(1);
                }
            });
        }
    }

    #[Test]
    public function checklist_stage_resolution_does_not_collide_across_workflows(): void
    {
        $admin = Staff::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin.checklist@example.com',
            'password' => bcrypt('password'),
            'role' => 1,
        ]);

        $client = Admin::create([
            'first_name' => 'Client',
            'last_name' => 'Test',
            'email' => 'client.checklist@example.com',
            'password' => bcrypt('password'),
            'type' => 'client',
        ]);

        $workflowA = Workflow::create(['name' => 'Workflow A']);
        $stageA = WorkflowStage::create([
            'workflow_id' => $workflowA->id,
            'name' => 'Stage Alpha',
        ]);

        $workflowB = Workflow::create(['name' => 'Workflow B']);
        $stageB = WorkflowStage::create([
            'workflow_id' => $workflowB->id,
            'name' => 'Stage Beta',
        ]);

        $matter = ClientMatter::create([
            'client_id' => $client->id,
            'workflow_id' => $workflowA->id,
            'workflow_stage_id' => $stageA->id,
            'status' => '1',
        ]);

        $this->actingAs($admin, 'admin');

        // Request adding checklist for a stage name 'Stage Beta' (which only exists in Workflow B, NOT Workflow A)
        $response = $this->post('/add-checklists', [
            'client_matter_id' => $matter->id,
            'wf_stage' => 'Stage Beta',
            'cp_checklist_names' => ['Document Verification'],
        ]);

        $response->assertStatus(200);

        // Verify inserted item has wf_stage_id NULL (does not steal stageB->id from Workflow B)
        $this->assertDatabaseHas('cp_doc_checklists', [
            'client_matter_id' => $matter->id,
            'wf_stage' => 'Stage Beta',
            'wf_stage_id' => null,
        ]);
    }

    #[Test]
    public function checklist_stage_resolution_matches_same_workflow_stage(): void
    {
        $admin = Staff::create([
            'first_name' => 'Admin2',
            'last_name' => 'User2',
            'email' => 'admin.checklist2@example.com',
            'password' => bcrypt('password'),
            'role' => 1,
        ]);

        $client = Admin::create([
            'first_name' => 'Client',
            'last_name' => 'Test2',
            'email' => 'client.checklist2@example.com',
            'password' => bcrypt('password'),
            'type' => 'client',
        ]);

        $workflowA = Workflow::create(['name' => 'Workflow A2']);
        $stageA = WorkflowStage::create([
            'workflow_id' => $workflowA->id,
            'name' => 'Stage Alpha2',
        ]);

        $matter = ClientMatter::create([
            'client_id' => $client->id,
            'workflow_id' => $workflowA->id,
            'workflow_stage_id' => $stageA->id,
            'status' => '1',
        ]);

        $this->actingAs($admin, 'admin');

        $response = $this->post('/add-checklists', [
            'client_matter_id' => $matter->id,
            'wf_stage' => 'Stage Alpha2',
            'cp_checklist_names' => ['Passport Copy'],
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('cp_doc_checklists', [
            'client_matter_id' => $matter->id,
            'wf_stage' => 'Stage Alpha2',
            'wf_stage_id' => $stageA->id,
        ]);
    }
}
