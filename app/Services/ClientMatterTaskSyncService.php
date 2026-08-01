<?php

namespace App\Services;

use App\Models\ClientMatter;
use App\Models\ClientMatterTask;
use App\Models\Note;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Bidirectional mirror between client checklist tasks (client_matter_tasks) and Action page rows (notes).
 */
class ClientMatterTaskSyncService
{
    public const DEFAULT_TASK_GROUP = 'Personal Action';

    /**
     * After a client checklist task is saved, create the linked Action (Note) if missing.
     */
    public function mirrorClientTaskToAction(ClientMatterTask $task): ?Note
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
            $note->action_date = now()->toDateString();
            $note->unique_group_id = 'group_' . uniqid('', true);
            $note->save();

            $task->note_id = $note->id;
            $task->saveQuietly();

            return $note;
        } catch (\Throwable $e) {
            Log::warning('ClientMatterTaskSync: failed to mirror client task to action', [
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
    public function mirrorActionToClientTask(Note $note): ?ClientMatterTask
    {
        if ((int) $note->is_action !== 1 || ! $note->client_id) {
            return null;
        }

        $existing = $this->findLinkedTaskForNote($note);
        if ($existing) {
            return $existing;
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
            $task->is_done = (string) $note->status === '1';
            $task->sort_order = $maxSort + 1;
            $task->created_by = (int) ($note->assigned_to ?: $note->user_id);
            $task->note_id = $note->id;
            $task->save();

            return $task;
        } catch (\Throwable $e) {
            Log::warning('ClientMatterTaskSync: failed to mirror action to client task', [
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

            return ClientMatterTask::whereIn('note_id', $noteIds)->first();
        }

        return null;
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

        return $raw !== '' ? mb_substr($raw, 0, 500) : 'Action';
    }
}
