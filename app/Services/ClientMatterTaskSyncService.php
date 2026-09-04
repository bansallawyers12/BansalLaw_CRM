<?php

namespace App\Services;

use App\Models\ClientMatter;
use App\Models\ClientMatterTask;
use App\Models\Note;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Bidirectional mirror between client checklist tasks (client_matter_tasks) and Tasks page rows (notes).
 */
class ClientMatterTaskSyncService
{
    public const DEFAULT_TASK_GROUP = 'Personal Task';

    /**
     * After a client checklist task is saved, create the linked Action (Note) if missing.
     */
    public function mirrorClientTaskToTaskNote(ClientMatterTask $task): ?Note
    {
        if ($task->note_id) {
            $existing = Note::find($task->note_id);

            return $existing instanceof Note ? $existing : null;
        }

        $assigneeId = (int) ($task->created_by ?: Auth::id());
        if ($assigneeId < 1) {
            return null;
        }

        try {
            $note = new Note;
            $note->client_id = (int) $task->client_id;
            $note->user_id = (int) (Auth::id() ?: $task->created_by);
            $note->matter_id = ((int) $task->client_matter_id) ?: null;
            $note->description = $task->title;
            $note->is_action = 1;
            $note->type = 'client';
            $note->task_group = self::DEFAULT_TASK_GROUP;
            $note->assigned_to = $assigneeId;
            $note->status = $task->is_done ? '1' : '0';
            $note->pin = 0;
            $note->action_date = $task->due_date
                ? $task->due_date->toDateString()
                : now()->toDateString();
            $note->unique_group_id = 'group_' . uniqid('', true);
            $note->save();

            $task->note_id = $note->id;
            $task->saveQuietly();

            return $note;
        } catch (\Throwable $e) {
            Log::warning('ClientMatterTaskSync: failed to mirror client task to task note', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * After an Action (Note) with a client is saved, create the linked checklist task if missing.
     * Only one checklist row per unique_group_id (multi-assignee actions share one client task).
     */
    public function mirrorTaskNoteToClientTask(Note $note): ?ClientMatterTask
    {
        if ((int) $note->is_action !== 1 || ! $note->client_id) {
            return null;
        }

        $existing = $this->findLinkedTaskForNote($note);
        if ($existing) {
            if ((int) ($existing->note_id ?? 0) < 1) {
                $existing->note_id = (int) $note->id;
                $existing->saveQuietly();
            }
            $this->repairCreatedByFromLinkedNotes([$existing]);

            return $existing->fresh(['creator:id,first_name,last_name']);
        }

        $matter = null;
        if (! empty($note->matter_id)) {
            $matter = ClientMatter::where('id', (int) $note->matter_id)
                ->where('client_id', (int) $note->client_id)
                ->first();
        }
        if (! $matter) {
            $matter = $this->resolveDefaultMatterForClient((int) $note->client_id);
        }
        if (! $matter) {
            return null;
        }

        // Keep Action ↔ matter in sync when the note had no matter yet.
        if (empty($note->matter_id)) {
            $note->matter_id = $matter->id;
            $note->saveQuietly();
        }

        $title = $this->titleFromNote($note);
        if ($title === '') {
            return null;
        }

        try {
            $maxSort = (int) ClientMatterTask::where('client_id', $note->client_id)->max('sort_order');

            $task = new ClientMatterTask;
            $task->client_matter_id = $matter->id;
            $task->client_id = (int) $note->client_id;
            $task->title = $title;
            $task->due_date = ! empty($note->action_date)
                ? \Carbon\Carbon::parse($note->action_date)->toDateString()
                : null;
            $task->is_done = (string) $note->status === '1';
            $task->sort_order = $maxSort + 1;
            // "Added by" must be the note creator (user_id), not the assignee.
            $task->created_by = (int) ($note->user_id ?: Auth::id() ?: $note->assigned_to);
            $task->note_id = $note->id;
            $task->save();

            return $task;
        } catch (\Throwable $e) {
            Log::warning('ClientMatterTaskSync: failed to mirror task note to client task', [
                'note_id' => $note->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function syncCompletionFromClientTask(ClientMatterTask $task): void
    {
        if (! $task->note_id) {
            return;
        }

        Note::where('id', $task->note_id)->update([
            'status' => $task->is_done ? '1' : '0',
        ]);
    }

    public function syncCompletionFromNote(Note $note, bool $completed): void
    {
        $isDone = $completed;

        $task = ClientMatterTask::where('note_id', $note->id)->first();
        if ($task) {
            $task->is_done = $isDone;
            $task->saveQuietly();

            return;
        }

        if (! empty($note->unique_group_id)) {
            $noteIds = Note::where('unique_group_id', $note->unique_group_id)->pluck('id');
            ClientMatterTask::whereIn('note_id', $noteIds)->update(['is_done' => $isDone]);
        }
    }

    public function syncTitleFromClientTask(ClientMatterTask $task): void
    {
        if (! $task->note_id) {
            return;
        }

        Note::where('id', $task->note_id)->update(['description' => $task->title]);
    }

    public function syncDueDateFromClientTask(ClientMatterTask $task): void
    {
        if (! $task->note_id) {
            return;
        }

        Note::where('id', $task->note_id)->update([
            'action_date' => $task->due_date
                ? $task->due_date->toDateString()
                : now()->toDateString(),
        ]);
    }

    /**
     * Fix checklist rows that stored assignee as created_by when mirrored from Actions.
     * "Added by" should match notes.user_id (creator), not notes.assigned_to.
     *
     * @param  \Illuminate\Support\Collection<int, ClientMatterTask>|iterable<ClientMatterTask>  $tasks
     */
    public function repairCreatedByFromLinkedNotes(iterable $tasks): void
    {
        $tasks = collect($tasks)->filter(static fn ($t) => $t instanceof ClientMatterTask && (int) ($t->note_id ?? 0) > 0);
        if ($tasks->isEmpty()) {
            return;
        }

        $noteIds = $tasks->pluck('note_id')->filter()->unique()->values();
        $notes = Note::query()
            ->whereIn('id', $noteIds)
            ->get(['id', 'user_id', 'assigned_to'])
            ->keyBy('id');

        foreach ($tasks as $task) {
            $note = $notes->get($task->note_id);
            if (! $note) {
                continue;
            }

            $creatorId = (int) ($note->user_id ?? 0);
            if ($creatorId < 1) {
                continue;
            }

            if ((int) ($task->created_by ?? 0) === $creatorId) {
                continue;
            }

            $task->created_by = $creatorId;
            $task->saveQuietly();
            if ($task->relationLoaded('creator')) {
                $task->unsetRelation('creator');
                $task->load('creator:id,first_name,last_name');
            }
        }
    }

    /**
     * When a checklist task is removed, mark the linked Action complete (keeps audit trail).
     */
    public function onClientTaskDeleted(ClientMatterTask $task): void
    {
        if (! $task->note_id) {
            return;
        }

        Note::where('id', $task->note_id)->update(['status' => '1']);
    }

    private function findLinkedTaskForNote(Note $note): ?ClientMatterTask
    {
        $byNote = ClientMatterTask::where('note_id', $note->id)->first();
        if ($byNote) {
            return $byNote;
        }

        if (! empty($note->unique_group_id)) {
            $noteIds = Note::where('unique_group_id', $note->unique_group_id)->pluck('id');
            $byGroup = ClientMatterTask::whereIn('note_id', $noteIds)->first();
            if ($byGroup) {
                return $byGroup;
            }
        }

        // Legacy rows mirrored before note_id was set: match open checklist by matter + title.
        $title = $this->titleFromNote($note);
        if ($title === '' || (int) ($note->client_id ?? 0) < 1) {
            return null;
        }

        $matterId = (int) ($note->matter_id ?? 0);
        $query = ClientMatterTask::query()
            ->where('client_id', (int) $note->client_id)
            ->where('title', $title)
            ->where(function ($q) {
                $q->whereNull('note_id')->orWhere('note_id', 0);
            })
            ->orderByDesc('id');

        if ($matterId > 0) {
            $query->where('client_matter_id', $matterId);
        }

        return $query->first();
    }

    private function resolveDefaultMatterForClient(int $clientId): ?ClientMatter
    {
        if ($clientId < 1) {
            return null;
        }

        return ClientMatter::query()
            ->where('client_id', $clientId)
            ->where(function ($q) {
                $q->where('matter_status', 1)->orWhere('matter_status', '1');
            })
            ->orderByDesc('id')
            ->first();
    }

    private function titleFromNote(Note $note): string
    {
        $raw = trim(strip_tags((string) ($note->description ?? '')));
        if ($raw !== '') {
            return mb_substr($raw, 0, 500);
        }

        $raw = trim(strip_tags((string) ($note->title ?? '')));

        return $raw !== '' ? mb_substr($raw, 0, 500) : 'Task';
    }
}
