<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Note;
use Illuminate\Support\Facades\Auth;

class LeadFollowUpNoteService
{
    public const TASK_GROUP = 'Follow Up';

    public const UNIQUE_PREFIX = 'lead_follow_up_';

    /** @return list<string> */
    public static function pipelineStatuses(): array
    {
        return ['new', 'follow_up', 'not_qualified', 'hostile'];
    }

    /**
     * Active = visible on default lead list (status 1).
     * Only explicit terminal / converted values are inactive so legacy DB values
     * (e.g. contacted, lost from old imports) are not hidden by mistake.
     */
    public static function isActivePipeline(?string $leadStatus): bool
    {
        if ($leadStatus === null || $leadStatus === '') {
            return true;
        }

        return ! in_array($leadStatus, ['not_qualified', 'hostile', 'converted'], true);
    }

    public static function adminsStatusForLeadStatus(?string $leadStatus): int
    {
        return self::isActivePipeline($leadStatus) ? 1 : 0;
    }

    /**
     * Complete open "Follow Up" action notes for this CRM record (admins.id).
     */
    public function completeOpenFollowUpNotes(int $adminId): void
    {
        Note::query()
            ->where('client_id', $adminId)
            ->where('type', 'client')
            ->where('is_action', 1)
            ->where('task_group', self::TASK_GROUP)
            ->where('status', '<>', '1')
            ->update(['status' => '1']);
    }

    /**
     * After lead is saved: sync notes when leaving or entering follow_up.
     */
    public function syncNotesForLead(Lead $lead, ?string $previousLeadStatus): void
    {
        $current = $lead->lead_status !== null && $lead->lead_status !== ''
            ? trim((string) $lead->lead_status)
            : null;

        $prev = $previousLeadStatus !== null && $previousLeadStatus !== ''
            ? trim((string) $previousLeadStatus)
            : null;

        if ($prev === 'follow_up' && $current !== 'follow_up') {
            $this->completeOpenFollowUpNotes((int) $lead->id);
        }

        if ($current === 'follow_up') {
            $this->ensureOpenFollowUpNote($lead);
        }
    }

    protected function ensureOpenFollowUpNote(Lead $lead): void
    {
        $assigneeId = $lead->user_id ?: Auth::guard('admin')->id();
        if (!$assigneeId) {
            return;
        }

        $note = Note::query()
            ->where('client_id', $lead->id)
            ->where('type', 'client')
            ->where('is_action', 1)
            ->where('task_group', self::TASK_GROUP)
            ->where('status', '<>', '1')
            ->orderByDesc('id')
            ->first();

        $actionDate = $lead->followup_date;

        if ($note) {
            $note->assigned_to = $assigneeId;
            $note->action_date = $actionDate;
            $note->save();

            return;
        }

        $creatorId = Auth::guard('admin')->id() ?: $assigneeId;

        Note::create([
            'user_id' => $creatorId,
            'client_id' => $lead->id,
            'type' => 'client',
            'is_action' => 1,
            'pin' => 0,
            'status' => '0',
            'task_group' => self::TASK_GROUP,
            'assigned_to' => $assigneeId,
            'action_date' => $actionDate,
            'description' => 'Lead follow-up',
            'title' => '',
            'unique_group_id' => self::UNIQUE_PREFIX . $lead->id,
        ]);
    }
}
