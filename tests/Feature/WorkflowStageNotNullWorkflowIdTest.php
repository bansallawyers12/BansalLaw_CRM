<?php

namespace Tests\Feature;

use App\Models\Workflow;
use App\Models\WorkflowStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WorkflowStageNotNullWorkflowIdTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function stage_created_with_null_workflow_id_defaults_to_general_workflow()
    {
        $stage = WorkflowStage::create([
            'name' => 'Unassigned Stage',
            'workflow_id' => null,
            'sort_order' => 99,
        ]);

        $this->assertNotNull($stage->workflow_id);

        $generalWorkflow = Workflow::where('name', 'General')->first();
        $this->assertNotNull($generalWorkflow);
        $this->assertEquals($generalWorkflow->id, $stage->workflow_id);
    }

    #[Test]
    public function stage_created_with_explicit_workflow_id_preserves_id()
    {
        $customWorkflow = Workflow::create([
            'name' => 'Custom Workflow',
            'description' => 'Test Custom Workflow',
            'status' => 1,
        ]);

        $stage = WorkflowStage::create([
            'name' => 'Custom Stage',
            'workflow_id' => $customWorkflow->id,
            'sort_order' => 1,
        ]);

        $this->assertEquals($customWorkflow->id, $stage->workflow_id);
    }
}
