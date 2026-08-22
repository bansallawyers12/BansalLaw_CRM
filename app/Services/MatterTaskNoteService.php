<?php

namespace App\Services;

use App\Models\Note;
use App\Models\Staff;

class MatterTaskNoteService
{
    /**
     * Create Tasks-page notes (task_group Query) for Person Responsible and Person Assisting,
     * sharing one unique_group_id so marking complete updates every row together.
     */
    public static function createGroupedForMatter(
        int $clientId,
        ?int $matterId,
        string $description,
        int $actorUserId,
        ?object $matter = null,
        ?int $fallbackAssigneeStaffId = null,
        ?int $crmStaffActorId = null
    ): void {
        if ($clientId <= 0 || trim($description) === '') {
            return;
        }

        $assigneeIds = [];
        if ($matter !== null) {
            foreach (['sel_person_responsible', 'sel_person_assisting'] as $field) {
                $v = $matter->{$field} ?? null;
                if ($v !== null && $v !== '') {
                    $id = (int) $v;
                    if ($id > 0) {
                        $assigneeIds[$id] = true;
                    }
                }
            }
        }

        $ids = array_keys($assigneeIds);
        if ($ids === [] && $fallbackAssigneeStaffId !== null && (int) $fallbackAssigneeStaffId > 0) {
            $ids = [(int) $fallbackAssigneeStaffId];
        }

        if ($crmStaffActorId !== null && $crmStaffActorId > 0 && $matter !== null) {
            $prId = (int) ($matter->sel_person_responsible ?? 0);
            $paId = (int) ($matter->sel_person_assisting ?? 0);
            $actorIsPrOrPa = ($prId === $crmStaffActorId) || ($paId === $crmStaffActorId);
            if ($actorIsPrOrPa) {
                $ids = array_values(array_filter($ids, static fn (int $id): bool => $id !== $crmStaffActorId));
            }
        }

        if ($ids === []) {
            return;
        }

        $timeline = app(TaskTimelineService::class);
        $uniqueGroupId = 'group_' . uniqid('', true);
        foreach ($ids as $assignedToStaffId) {
            $taskNote = new Note();
            $taskNote->user_id = $actorUserId;
            $taskNote->client_id = $clientId;
            $taskNote->matter_id = $matterId;
            $taskNote->assigned_to = $assignedToStaffId;
            $taskNote->description = $description;
            $taskNote->action_date = now()->toDateString();
            $taskNote->task_group = 'Query';
            $taskNote->type = 'client';
            $taskNote->is_action = 1;
            $taskNote->status = '0';
            $taskNote->pin = 0;
            $taskNote->unique_group_id = $uniqueGroupId;
            $taskNote->save();

            $staff = Staff::find($assignedToStaffId);
            $assigneeName = $staff
                ? trim(($staff->first_name ?? '') . ' ' . ($staff->last_name ?? ''))
                : 'Unknown';
            $timeline->logTaskNoteCreated($taskNote, '', $assigneeName);
        }
    }
}
