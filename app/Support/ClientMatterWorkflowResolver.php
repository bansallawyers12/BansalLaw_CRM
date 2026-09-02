<?php

namespace App\Support;

use App\Models\ClientMatter;
use App\Models\Matter;
use App\Models\Workflow;
use App\Models\WorkflowStage;
use Illuminate\Support\Collection;

/**
 * Resolve which workflow stages to show for a client matter (Update Stage modal / workflow tab).
 */
class ClientMatterWorkflowResolver
{
    /**
     * @param  object|ClientMatter  $matter  Row with workflow_id, workflow_stage_id, sel_matter_id
     */
    public static function resolveWorkflowId(object $matter): ?int
    {
        $workflowStageId = isset($matter->workflow_stage_id) ? (int) $matter->workflow_stage_id : null;
        $matterWorkflowId = isset($matter->workflow_id) ? (int) $matter->workflow_id : null;
        $selMatterId = isset($matter->sel_matter_id) ? (int) $matter->sel_matter_id : null;

        $currentStage = $workflowStageId ? WorkflowStage::find($workflowStageId) : null;

        $candidates = [];
        if ($currentStage && $currentStage->workflow_id) {
            $candidates[] = (int) $currentStage->workflow_id;
        }
        if ($matterWorkflowId) {
            $candidates[] = $matterWorkflowId;
        }
        if ($selMatterId) {
            $typeWorkflowId = Matter::where('id', $selMatterId)->value('workflow_id');
            if ($typeWorkflowId) {
                $candidates[] = (int) $typeWorkflowId;
            }
        }

        $generalId = WorkflowStageCatalog::generalWorkflowId();
        if ($generalId) {
            $candidates[] = (int) $generalId;
        }

        $candidates = array_values(array_unique(array_filter($candidates)));

        if ($candidates === []) {
            return null;
        }

        $bestId = null;
        $bestCount = 0;

        foreach ($candidates as $workflowId) {
            $stages = WorkflowStage::where('workflow_id', $workflowId)
                ->orderByRaw('COALESCE(sort_order, id) ASC')
                ->get();

            if ($stages->isEmpty()) {
                continue;
            }

            $containsCurrent = ! $workflowStageId || $stages->contains('id', $workflowStageId);
            if (! $containsCurrent && $currentStage) {
                $needle = strtolower(trim((string) $currentStage->name));
                $containsCurrent = $stages->contains(function (WorkflowStage $stage) use ($needle) {
                    return strtolower(trim((string) $stage->name)) === $needle;
                });
            }

            if (! $containsCurrent) {
                continue;
            }

            $count = $stages->count();
            if ($count > $bestCount) {
                $bestId = $workflowId;
                $bestCount = $count;
            }
        }

        if ($bestId !== null) {
            return $bestId;
        }

        foreach ($candidates as $workflowId) {
            $count = WorkflowStage::where('workflow_id', $workflowId)->count();
            if ($count > $bestCount) {
                $bestId = $workflowId;
                $bestCount = $count;
            }
        }

        return $bestId;
    }

    /**
     * @param  object|ClientMatter  $matter
     */
    public static function stagesForMatter(object $matter): Collection
    {
        $workflowId = self::resolveWorkflowId($matter);

        if (! $workflowId) {
            return WorkflowStage::orderByRaw('COALESCE(sort_order, id) ASC')->get();
        }

        return WorkflowStage::where('workflow_id', $workflowId)
            ->orderByRaw('COALESCE(sort_order, id) ASC')
            ->get();
    }

    /**
     * Map matter's stored stage to the resolved workflow for UI highlighting.
     *
     * @param  object|ClientMatter  $matter
     */
    public static function resolveDisplayStageId(object $matter, Collection $stages): ?int
    {
        $storedId = isset($matter->workflow_stage_id) ? (int) $matter->workflow_stage_id : null;
        if (! $storedId) {
            return null;
        }

        if ($stages->contains('id', $storedId)) {
            return $storedId;
        }

        $stored = WorkflowStage::find($storedId);
        if (! $stored) {
            return $storedId;
        }

        $needle = strtolower(trim((string) $stored->name));
        $match = $stages->first(function (WorkflowStage $stage) use ($needle) {
            return strtolower(trim((string) $stage->name)) === $needle;
        });

        return $match ? (int) $match->id : $storedId;
    }
}
