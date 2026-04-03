<?php

namespace App\Support;

/**
 * Determines whether a workflow stage is frozen (non-editable / non-deletable).
 */
class WorkflowStageFreeze
{
    public static function isFrozen(?string $name): bool
    {
        if ($name === null || $name === '') {
            return false;
        }

        $normalized = mb_strtolower(trim($name));

        foreach (config('workflow.frozen_stage_names', []) as $exact) {
            if ($exact === null || $exact === '') {
                continue;
            }
            if ($normalized === mb_strtolower(trim((string) $exact))) {
                return true;
            }
        }

        foreach (config('workflow.frozen_stage_name_starts_with', []) as $prefix) {
            if ($prefix === null || $prefix === '') {
                continue;
            }
            $p = mb_strtolower(trim((string) $prefix));
            if ($p !== '' && str_starts_with($normalized, $p)) {
                return true;
            }
        }

        return false;
    }
}
