<?php

namespace App\Services;

use App\Models\Note;

class ClientPortalActionNoteService
{
    /**
     * Create Action-page notes (task_group Client Portal) for Person Responsible and Person Assisting,
     * sharing one unique_group_id so marking complete updates every row together.
     *
     * @param int $clientId Client record id on the note
     * @param int|null $matterId Matter id (nullable e.g. invoice edge cases)
     * @param string $description Action description text
     * @param int $actorUserId Note user_id (staff completing the action, or client portal user id from API)
     * @param object|null $matter Model or row with sel_person_responsible / sel_person_assisting
     * @param int|null $fallbackAssigneeStaffId When matter has no PR/PA (e.g. Auth::id() or first staff recipient)
     * @param int|null $crmStaffActorId CRM staff performing the action: removed from assignees when they are PR/PA so they do not get self-assigned Client Portal rows (client/API flows omit this)
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

        $uniqueGroupId = 'group_' . uniqid('', true);
        foreach ($ids as $assignedToStaffId) {
            $actionNote = new Note();
            $actionNote->user_id = $actorUserId;
            $actionNote->client_id = $clientId;
            $actionNote->matter_id = $matterId;
            $actionNote->assigned_to = $assignedToStaffId;
            $actionNote->description = $description;
            $actionNote->action_date = now()->toDateString();
            $actionNote->task_group = 'Client Portal';
            $actionNote->type = 'client';
            $actionNote->is_action = 1;
            $actionNote->status = '0';
            $actionNote->pin = 0;
            $actionNote->unique_group_id = $uniqueGroupId;
            $actionNote->save();
        }
    }
}
