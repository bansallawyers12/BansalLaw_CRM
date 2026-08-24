<?php

namespace App\Services;

use App\Models\ActivitiesLog;
use App\Models\ClientMatter;
use App\Models\ClientMatterTask;
use App\Models\Note;
use App\Models\Staff;
use App\Support\ClientActivity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Writes detailed activity-feed (timeline) rows for Tasks-page notes and matter checklist tasks.
 */
class TaskTimelineService
{
    /**
     * Log creation of a Tasks-page note (notes.is_action = 1) on the client timeline.
     */
    public function logTaskNoteCreated(
        Note $taskNote,
        string $clientLabel = '',
        ?string $assigneeName = null,
        string $verb = 'Set task'
    ): ?ActivitiesLog {
        $clientId = (int) ($taskNote->client_id ?? 0);
        if ($clientId < 1) {
            return null;
        }

        try {
            $assigneeName = $assigneeName ?: $this->staffName($taskNote->assigned_to);
            $subject = ($clientLabel !== '' ? $clientLabel . ' — ' : '')
                . $verb . ' for ' . $assigneeName;

            $createdBy = $this->resolveCreatedBy($taskNote->user_id);
            if ($createdBy === null) {
                return null;
            }

            return ClientActivity::log(
                $clientId,
                $subject,
                ClientActivity::TYPE_ACTIVITY,
                $this->buildTaskNoteDescription($taskNote, $assigneeName),
                [
                    'created_by' => $createdBy,
                    'task_status' => ((string) $taskNote->status === '1') ? 1 : 0,
                    'use_for' => $this->useForAssignee($taskNote->assigned_to),
                    'followup_date' => $taskNote->action_date ?: null,
                    'task_group' => $taskNote->task_group ?? null,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('TaskTimeline: failed to log task note created', [
                'note_id' => $taskNote->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Log a Tasks-page note update on the client timeline.
     */
    public function logTaskNoteUpdated(
        Note $taskNote,
        string $clientLabel = '',
        ?string $assigneeName = null
    ): ?ActivitiesLog {
        return $this->logTaskNoteCreated($taskNote, $clientLabel, $assigneeName, 'Updated task');
    }

    /**
     * Log creation of a matter checklist task on the client timeline.
     */
    public function logTaskCreated(ClientMatterTask $task, ?ClientMatter $matter = null): ?ActivitiesLog
    {
        $clientId = (int) ($task->client_id ?? 0);
        if ($clientId < 1) {
            return null;
        }

        try {
            $matter = $matter ?: $task->clientMatter;
            $matterRef = $matter?->client_unique_matter_no ?? '';
            $creatorName = $this->staffName($task->created_by);

            $subject = $matterRef !== ''
                ? "Created task — {$matterRef}"
                : 'Created task';

            $createdBy = $this->resolveCreatedBy($task->created_by);
            if ($createdBy === null) {
                return null;
            }

            return ClientActivity::log(
                $clientId,
                $subject,
                ClientActivity::TYPE_ACTIVITY,
                $this->buildTaskDescription($task, $matterRef, $creatorName),
                [
                    'created_by' => $createdBy,
                    'task_status' => $task->is_done ? 1 : 0,
                    'followup_date' => $task->due_date?->toDateString(),
                    'task_group' => ClientMatterTaskSyncService::DEFAULT_TASK_GROUP,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('TaskTimeline: failed to log checklist task created', [
                'task_id' => $task->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Log checklist task updates (complete / title / due date) on the timeline.
     */
    public function logTaskUpdated(
        ClientMatterTask $task,
        array $changes,
        ?ClientMatter $matter = null
    ): ?ActivitiesLog {
        $clientId = (int) ($task->client_id ?? 0);
        if ($clientId < 1 || $changes === []) {
            return null;
        }

        try {
            $matter = $matter ?: $task->clientMatter;
            $matterRef = $matter?->client_unique_matter_no ?? '';

            if (isset($changes['Status']) || isset($changes['is_done'])) {
                $verb = $task->is_done ? 'Completed task' : 'Reopened task';
            } else {
                $verb = 'Updated task';
            }

            $subject = $matterRef !== '' ? "{$verb} — {$matterRef}" : $verb;

            $rows = '';
            foreach ($changes as $label => $change) {
                if (! is_array($change) || ! array_key_exists('old', $change) || ! array_key_exists('new', $change)) {
                    continue;
                }
                $rows .= '<div style="margin-bottom: 6px;"><strong>'
                    . htmlspecialchars((string) $label, ENT_QUOTES, 'UTF-8')
                    . ':</strong> '
                    . '<span style="color: #dc3545; text-decoration: line-through;">'
                    . $this->formatDisplayValue($change['old'])
                    . '</span> → <span style="color: #28a745; font-weight: 600;">'
                    . $this->formatDisplayValue($change['new'])
                    . '</span></div>';
            }

            $description = '<div class="activity-task-details">'
                . '<p><span class="text-semi-bold">' . htmlspecialchars((string) $task->title, ENT_QUOTES, 'UTF-8') . '</span></p>'
                . $rows
                . '</div>';

            $createdBy = $this->resolveCreatedBy($task->created_by);
            if ($createdBy === null) {
                return null;
            }

            return ClientActivity::log(
                $clientId,
                $subject,
                ClientActivity::TYPE_ACTIVITY,
                $description,
                [
                    'created_by' => $createdBy,
                    'task_status' => $task->is_done ? 1 : 0,
                    'followup_date' => $task->due_date?->toDateString(),
                    'task_group' => ClientMatterTaskSyncService::DEFAULT_TASK_GROUP,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('TaskTimeline: failed to log checklist task updated', [
                'task_id' => $task->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function buildTaskNoteDescription(Note $taskNote, string $assigneeName): string
    {
        $title = trim((string) ($taskNote->title ?? ''));
        $body = (string) ($taskNote->description ?? '');
        $group = trim((string) ($taskNote->task_group ?? ''));
        $due = $this->formatDate($taskNote->action_date);
        $matterRef = $this->matterRefForNote($taskNote);

        $parts = [];
        if ($title !== '') {
            $parts[] = '<p><span class="text-semi-bold">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</span></p>';
        }
        $meta = [];
        if ($group !== '') {
            $meta[] = '<strong>Type:</strong> ' . htmlspecialchars($group, ENT_QUOTES, 'UTF-8');
        }
        if ($assigneeName !== '' && $assigneeName !== 'Unknown') {
            $meta[] = '<strong>Assignee:</strong> ' . htmlspecialchars($assigneeName, ENT_QUOTES, 'UTF-8');
        }
        if ($due !== '') {
            $meta[] = '<strong>Due:</strong> ' . htmlspecialchars($due, ENT_QUOTES, 'UTF-8');
        }
        if ($matterRef !== '') {
            $meta[] = '<strong>Matter:</strong> ' . htmlspecialchars($matterRef, ENT_QUOTES, 'UTF-8');
        }
        if ($meta !== []) {
            $parts[] = '<p>' . implode(' &nbsp;|&nbsp; ', $meta) . '</p>';
        }
        if (trim(strip_tags($body)) !== '') {
            $parts[] = '<div class="activity-action-body">' . $body . '</div>';
        }

        return '<div class="activity-action-details">' . implode('', $parts) . '</div>';
    }

    private function buildTaskDescription(ClientMatterTask $task, string $matterRef, string $creatorName): string
    {
        $parts = [];
        $parts[] = '<p><span class="text-semi-bold">' . htmlspecialchars((string) $task->title, ENT_QUOTES, 'UTF-8') . '</span></p>';

        $meta = [];
        if ($creatorName !== '' && $creatorName !== 'Unknown') {
            $meta[] = '<strong>Created by:</strong> ' . htmlspecialchars($creatorName, ENT_QUOTES, 'UTF-8');
        }
        $due = $task->due_date ? $task->due_date->format('d/m/Y') : '';
        if ($due !== '') {
            $meta[] = '<strong>Due:</strong> ' . htmlspecialchars($due, ENT_QUOTES, 'UTF-8');
        }
        if ($matterRef !== '') {
            $meta[] = '<strong>Matter:</strong> ' . htmlspecialchars($matterRef, ENT_QUOTES, 'UTF-8');
        }
        if ($meta !== []) {
            $parts[] = '<p>' . implode(' &nbsp;|&nbsp; ', $meta) . '</p>';
        }

        return '<div class="activity-task-details">' . implode('', $parts) . '</div>';
    }

    private function matterRefForNote(Note $taskNote): string
    {
        $matterId = (int) ($taskNote->matter_id ?? 0);
        if ($matterId < 1) {
            return '';
        }

        $ref = ClientMatter::where('id', $matterId)->value('client_unique_matter_no');

        return $ref ? (string) $ref : '';
    }

    private function resolveCreatedBy($fallbackUserId): ?int
    {
        $id = (int) (Auth::id() ?? $fallbackUserId ?? 0);

        return $id > 0 ? $id : null;
    }

    private function staffName($staffId): string
    {
        $id = (int) $staffId;
        if ($id < 1) {
            return 'Unknown';
        }

        $staff = Staff::find($id);

        return $staff
            ? trim(($staff->first_name ?? '') . ' ' . ($staff->last_name ?? ''))
            : 'Unknown';
    }

    private function useForAssignee($assigneeId): ?string
    {
        $id = (int) $assigneeId;
        if ($id < 1) {
            return null;
        }

        $actorId = (int) (Auth::id() ?? 0);
        if ($actorId > 0 && $actorId !== $id) {
            return (string) $id;
        }

        return null;
    }

    private function formatDate($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        try {
            if ($value instanceof \DateTimeInterface) {
                return $value->format('d/m/Y');
            }
            $ts = is_numeric($value) ? (int) $value : strtotime((string) $value);

            return $ts ? date('d/m/Y', $ts) : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function formatDisplayValue($value): string
    {
        if ($value === null || $value === '') {
            return '<em style="color: #999;">(empty)</em>';
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
