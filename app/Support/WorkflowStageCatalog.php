<?php

namespace App\Support;

use App\Models\Workflow;
use App\Models\WorkflowStage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Canonical workflow stage names/order and idempotent backfill for a workflow.
 */
class WorkflowStageCatalog
{
    /** @return array<int, array{name: string, sort_order: int}> */
    public static function coreStageBlueprint(): array
    {
        return [
            ['name' => 'New', 'sort_order' => 1],
            ['name' => 'Checklist', 'sort_order' => 2],
            ['name' => 'Decision Received', 'sort_order' => 3],
            ['name' => 'Ready to Close', 'sort_order' => 4],
            ['name' => 'File Closed', 'sort_order' => 5],
        ];
    }

    public static function generalWorkflowId(): ?int
    {
        if (! Schema::hasTable('workflows')) {
            return null;
        }

        return DB::table('workflows')
            ->whereRaw('LOWER(name) = ?', ['general'])
            ->value('id');
    }

    /**
     * Ensure General workflow exists and has the full core stage path.
     */
    public static function ensureGeneralWorkflowStages(): ?int
    {
        $generalId = self::generalWorkflowId();
        if (! $generalId) {
            if (! Schema::hasTable('workflows')) {
                return null;
            }
            $generalId = DB::table('workflows')->insertGetId([
                'name' => 'General',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        self::ensureWorkflowStages((int) $generalId);

        return (int) $generalId;
    }

    /**
     * Insert any missing core stages (by name, case-insensitive) into the given workflow.
     */
    public static function ensureWorkflowStages(int $workflowId): void
    {
        if (! Schema::hasTable('workflow_stages') || $workflowId <= 0) {
            return;
        }

        $existing = DB::table('workflow_stages')
            ->where('workflow_id', $workflowId)
            ->get(['id', 'name', 'sort_order']);

        $existingByLower = [];
        foreach ($existing as $row) {
            $existingByLower[strtolower(trim((string) $row->name))] = $row;
        }

        foreach (self::coreStageBlueprint() as $stage) {
            $key = strtolower($stage['name']);
            if (isset($existingByLower[$key])) {
                $row = $existingByLower[$key];
                if ((int) ($row->sort_order ?? 0) !== $stage['sort_order']) {
                    DB::table('workflow_stages')
                        ->where('id', $row->id)
                        ->update([
                            'sort_order' => $stage['sort_order'],
                            'updated_at' => now(),
                        ]);
                }
                continue;
            }

            DB::table('workflow_stages')->insert([
                'name' => $stage['name'],
                'workflow_id' => $workflowId,
                'sort_order' => $stage['sort_order'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * First stage id for a workflow, creating core stages when the workflow is empty.
     */
    public static function firstStageIdForWorkflow(int $workflowId): ?int
    {
        self::ensureWorkflowStages($workflowId);

        return WorkflowStage::where('workflow_id', $workflowId)
            ->orderByRaw('COALESCE(sort_order, id) ASC')
            ->value('id');
    }
}
