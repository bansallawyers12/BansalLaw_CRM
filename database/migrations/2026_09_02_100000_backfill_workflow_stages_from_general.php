<?php

use App\Support\WorkflowStageCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backfill core workflow stages on every workflow (live may only have "New").
     */
    public function up(): void
    {
        if (! Schema::hasTable('workflows') || ! Schema::hasTable('workflow_stages')) {
            return;
        }

        WorkflowStageCatalog::ensureGeneralWorkflowStages();

        $workflowIds = DB::table('workflows')->pluck('id');
        foreach ($workflowIds as $workflowId) {
            WorkflowStageCatalog::ensureWorkflowStages((int) $workflowId);
        }
    }

    public function down(): void
    {
        // No-op: do not remove stages that may be in use on matters.
    }
};
