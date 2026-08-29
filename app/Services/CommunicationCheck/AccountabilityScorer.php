<?php

namespace App\Services\CommunicationCheck;

use App\Models\ActivitiesLog;
use App\Models\EmailLog;
use App\Models\Note;
use App\Models\SmsLog;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Deterministic accountability score: Logged | Worked | Gap.
 */
class AccountabilityScorer
{
    /**
     * @param  array<string, mixed>  $extracted
     * @param  array<string, mixed>  $matchResult
     * @return array<string, mixed>
     */
    public function score(array $extracted, array $matchResult): array
    {
        $channel = (string) ($extracted['channel'] ?? 'unknown');

        return match ($channel) {
            'email', 'unknown' => $this->scoreEmail($extracted, $matchResult),
            'sms' => $this->scoreSms($extracted, $matchResult),
            'call' => $this->scoreCall($extracted, $matchResult),
            default => [
                'verdict' => 'unsupported',
                'label' => 'Not enough CRM data (channel not supported yet)',
                'confidence' => (int) ($extracted['extract_confidence'] ?? 0),
                'reasons' => [
                    'Unsupported channel for Communication Check.',
                ],
                'matched_record' => null,
                'follow_ups' => [],
                'staff' => [],
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $extracted
     * @param  array<string, mixed>  $matchResult
     * @return array<string, mixed>
     */
    private function scoreEmail(array $extracted, array $matchResult): array
    {
        $best = $matchResult['best'] ?? null;
        $matchConfidence = (int) ($matchResult['match_confidence'] ?? 0);
        $extractConfidence = (int) ($extracted['extract_confidence'] ?? 0);
        $combinedConfidence = (int) round(($matchConfidence * 0.65) + ($extractConfidence * 0.35));

        if (! $best || $matchConfidence < 40) {
            return [
                'verdict' => 'gap',
                'label' => 'Gap — not found in CRM',
                'confidence' => max($extractConfidence, 20),
                'reasons' => [
                    $best
                        ? 'Closest email_logs match was below confidence threshold (' . $matchConfidence . ').'
                        : 'No matching email_logs row for subject/from/to/time window.',
                    'Treat as assistive — confirm manually before relying on this for HR.',
                ],
                'matched_record' => $best,
                'follow_ups' => [],
                'staff' => [],
                'client_suggestions' => $matchResult['client_suggestions'] ?? [],
            ];
        }

        $emailLogId = (int) ($best['email_log_id'] ?? 0);
        $email = EmailLog::with('uploader')->find($emailLogId);
        if (! $email) {
            return [
                'verdict' => 'gap',
                'label' => 'Gap — matched row missing',
                'confidence' => 30,
                'reasons' => ['Matched email_logs id no longer exists.'],
                'matched_record' => $best,
                'follow_ups' => [],
                'staff' => [],
            ];
        }

        $receivedAt = $this->emailTimestamp($email) ?? now()->subDay();
        $followupHours = (int) config('crm.communication_check.followup_hours', 24);
        $windowEnd = $receivedAt->copy()->addHours($followupHours);

        $followUps = [];
        $staffMap = [];

        if ($email->user_id) {
            $name = $email->uploader?->full_name ?? ('Staff #' . $email->user_id);
            $staffMap[(int) $email->user_id] = $name;
            $followUps[] = [
                'type' => 'assigned',
                'at' => $email->created_at?->toIso8601String(),
                'staff_id' => (int) $email->user_id,
                'staff_name' => $name,
                'detail' => 'Email assigned / logged by ' . $name,
            ];
        }

        if ($email->mail_is_read) {
            $followUps[] = [
                'type' => 'read',
                'at' => $email->updated_at?->toIso8601String(),
                'staff_id' => $email->user_id ? (int) $email->user_id : null,
                'staff_name' => $email->uploader?->full_name,
                'detail' => 'Marked read in CRM',
            ];
        }

        $this->appendThreadReplies($email, $receivedAt, $windowEnd, $followUps, $staffMap);
        if ($email->client_id) {
            $this->appendNotes((int) $email->client_id, $receivedAt, $windowEnd, $followUps, $staffMap);
            $this->appendActivities((int) $email->client_id, $receivedAt, $windowEnd, $followUps, $staffMap);
            $this->appendSms((int) $email->client_id, $receivedAt, $windowEnd, $followUps, $staffMap);
        }

        return $this->finalizeVerdict(
            $followUps,
            $staffMap,
            $combinedConfidence,
            $best,
            $matchResult['client_suggestions'] ?? [],
            $receivedAt,
            $windowEnd,
            $followupHours,
            'email_logs #' . $email->id
        );
    }

    /**
     * @param  array<string, mixed>  $extracted
     * @param  array<string, mixed>  $matchResult
     * @return array<string, mixed>
     */
    private function scoreSms(array $extracted, array $matchResult): array
    {
        $best = $matchResult['best'] ?? null;
        $matchConfidence = (int) ($matchResult['match_confidence'] ?? 0);
        $extractConfidence = (int) ($extracted['extract_confidence'] ?? 0);
        $combinedConfidence = (int) round(($matchConfidence * 0.65) + ($extractConfidence * 0.35));
        $inboundWarning = $matchResult['inbound_warning'] ?? null;
        $extraReasons = [];
        if (is_string($inboundWarning) && $inboundWarning !== '') {
            $extraReasons[] = $inboundWarning;
        }

        if (! $best || $matchConfidence < 40) {
            return [
                'verdict' => 'gap',
                'label' => 'Gap — not found in CRM',
                'confidence' => max($extractConfidence, 20),
                'reasons' => array_values(array_filter(array_merge($extraReasons, [
                    $best
                        ? 'Closest sms_logs match was below confidence threshold (' . $matchConfidence . ').'
                        : 'No matching sms_logs row for phone + time window.',
                    'Phone + time are primary; body text is only a tie-break.',
                    'Treat as assistive — confirm manually before relying on this for HR.',
                ]))),
                'matched_record' => $best,
                'follow_ups' => [],
                'staff' => [],
                'client_suggestions' => $matchResult['client_suggestions'] ?? [],
            ];
        }

        $smsLogId = (int) ($best['sms_log_id'] ?? 0);
        $sms = SmsLog::with(['sender', 'activity'])->find($smsLogId);
        if (! $sms) {
            return [
                'verdict' => 'gap',
                'label' => 'Gap — matched row missing',
                'confidence' => 30,
                'reasons' => ['Matched sms_logs id no longer exists.'],
                'matched_record' => $best,
                'follow_ups' => [],
                'staff' => [],
            ];
        }

        $receivedAt = $this->smsTimestamp($sms) ?? now()->subDay();
        $followupHours = (int) config('crm.communication_check.followup_hours', 24);
        $windowEnd = $receivedAt->copy()->addHours($followupHours);

        $followUps = [];
        $staffMap = [];

        if ($sms->sender_id) {
            $name = $sms->sender?->full_name ?? ('Staff #' . $sms->sender_id);
            $staffMap[(int) $sms->sender_id] = $name;
            $followUps[] = [
                'type' => 'assigned',
                'at' => $receivedAt->toIso8601String(),
                'staff_id' => (int) $sms->sender_id,
                'staff_name' => $name,
                'detail' => 'Outbound SMS logged by ' . $name . ' (status: ' . ($sms->status ?: 'unknown') . ')',
            ];
            $followUps[] = [
                'type' => 'sms',
                'at' => $receivedAt->toIso8601String(),
                'staff_id' => (int) $sms->sender_id,
                'staff_name' => $name,
                'detail' => 'Staff-sent SMS in CRM',
            ];
        } else {
            $followUps[] = [
                'type' => 'assigned',
                'at' => $receivedAt->toIso8601String(),
                'staff_id' => null,
                'staff_name' => null,
                'detail' => 'Inbound SMS row in sms_logs (no sender_id)',
            ];
        }

        if ($sms->activity) {
            $activity = $sms->activity;
            $name = null;
            if ($activity->created_by) {
                $staff = Staff::find($activity->created_by);
                $name = $staff?->full_name ?? ('Staff #' . $activity->created_by);
                $staffMap[(int) $activity->created_by] = $name;
            }
            $followUps[] = [
                'type' => 'note',
                'at' => $activity->created_at?->toIso8601String(),
                'staff_id' => $activity->created_by ? (int) $activity->created_by : null,
                'staff_name' => $name,
                'detail' => 'Activity linked to SMS #' . $sms->id,
            ];
        }

        if ($sms->client_id) {
            $this->appendNotes((int) $sms->client_id, $receivedAt, $windowEnd, $followUps, $staffMap);
            $this->appendActivities((int) $sms->client_id, $receivedAt, $windowEnd, $followUps, $staffMap);
            $this->appendRelatedSms((int) $sms->client_id, (int) $sms->id, $receivedAt, $windowEnd, $followUps, $staffMap);
        }

        $result = $this->finalizeVerdict(
            $followUps,
            $staffMap,
            $combinedConfidence,
            $best,
            $matchResult['client_suggestions'] ?? [],
            $receivedAt,
            $windowEnd,
            $followupHours,
            'sms_logs #' . $sms->id
        );

        if ($extraReasons !== []) {
            $result['reasons'] = array_values(array_unique(array_merge($extraReasons, $result['reasons'] ?? [])));
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $extracted
     * @param  array<string, mixed>  $matchResult
     * @return array<string, mixed>
     */
    private function scoreCall(array $extracted, array $matchResult): array
    {
        $extractConfidence = (int) ($extracted['extract_confidence'] ?? 0);
        $matchConfidence = (int) ($matchResult['match_confidence'] ?? 0);
        $combinedConfidence = (int) round(($matchConfidence * 0.65) + ($extractConfidence * 0.35));
        $best = $matchResult['best'] ?? null;
        $insufficient = (bool) ($matchResult['insufficient_data'] ?? false);
        $insufficientReason = $matchResult['insufficient_reason'] ?? null;

        if ($insufficient && ! $best) {
            return [
                'verdict' => 'unsupported',
                'label' => 'Not enough CRM data',
                'confidence' => max($extractConfidence, 15),
                'reasons' => array_values(array_filter([
                    is_string($insufficientReason) ? $insufficientReason : null,
                    'There is no first-class call log (PBX/VoIP CDR). Calls are only proven when staff logged a Call Action/note.',
                    'AI cannot invent a handler from a Recents screenshot alone.',
                    'Treat as assistive — confirm manually.',
                ])),
                'matched_record' => null,
                'follow_ups' => [],
                'staff' => [],
                'client_suggestions' => $matchResult['client_suggestions'] ?? [],
            ];
        }

        if (! $best || $matchConfidence < 40) {
            $reasons = [
                $best
                    ? 'Closest Call Action/activity was below confidence threshold (' . $matchConfidence . ').'
                    : 'No matching Call Action or Call activity for phone + time window (±'
                        . (int) config('crm.communication_check.call_window_minutes', 30) . ' min).',
                'Without a PBX log, absence of a Call Action usually means the call was not logged — not that it was handled.',
                'Treat as assistive — confirm manually before relying on this for HR.',
            ];
            if (is_string($insufficientReason) && $insufficientReason !== '') {
                array_unshift($reasons, $insufficientReason);
            }

            return [
                'verdict' => 'gap',
                'label' => 'Gap — no Call Action in CRM',
                'confidence' => max($extractConfidence, 20),
                'reasons' => $reasons,
                'matched_record' => $best,
                'follow_ups' => [],
                'staff' => [],
                'client_suggestions' => $matchResult['client_suggestions'] ?? [],
            ];
        }

        $followUps = [];
        $staffMap = [];
        $receivedAt = null;
        if (! empty($best['created_at'])) {
            try {
                $receivedAt = Carbon::parse($best['created_at']);
            } catch (\Throwable) {
                $receivedAt = now()->subDay();
            }
        } else {
            $receivedAt = now()->subDay();
        }

        $followupHours = (int) config('crm.communication_check.followup_hours', 24);
        $windowEnd = $receivedAt->copy()->addHours($followupHours);

        if (! empty($best['staff_id'])) {
            $name = $best['staff_name'] ?? ('Staff #' . $best['staff_id']);
            $staffMap[(int) $best['staff_id']] = $name;
            $followUps[] = [
                'type' => 'assigned',
                'at' => $receivedAt->toIso8601String(),
                'staff_id' => (int) $best['staff_id'],
                'staff_name' => $name,
                'detail' => 'Call logged by ' . $name,
            ];
            $followUps[] = [
                'type' => 'action',
                'at' => $receivedAt->toIso8601String(),
                'staff_id' => (int) $best['staff_id'],
                'staff_name' => $name,
                'detail' => 'Call Action/activity recorded in CRM',
            ];
        } elseif (! empty($best['assignee_name']) || ! empty($best['assigned_to'])) {
            $aid = (int) ($best['assigned_to'] ?? 0);
            $name = $best['assignee_name'] ?? ('Staff #' . $aid);
            if ($aid > 0) {
                $staffMap[$aid] = $name;
            }
            $followUps[] = [
                'type' => 'assigned',
                'at' => $receivedAt->toIso8601String(),
                'staff_id' => $aid ?: null,
                'staff_name' => $name,
                'detail' => 'Call Action assigned to ' . $name,
            ];
        } else {
            $followUps[] = [
                'type' => 'assigned',
                'at' => $receivedAt->toIso8601String(),
                'staff_id' => null,
                'staff_name' => null,
                'detail' => 'Call Action found in CRM (no staff attributed)',
            ];
        }

        if (($best['status'] ?? '') === 'completed') {
            $followUps[] = [
                'type' => 'action',
                'at' => $receivedAt->toIso8601String(),
                'staff_id' => $best['staff_id'] ?? null,
                'staff_name' => $best['staff_name'] ?? null,
                'detail' => 'Call Action marked completed',
            ];
        }

        if (! empty($best['client_id'])) {
            $this->appendNotes((int) $best['client_id'], $receivedAt, $windowEnd, $followUps, $staffMap);
            $this->appendActivities((int) $best['client_id'], $receivedAt, $windowEnd, $followUps, $staffMap);
            $this->appendSms((int) $best['client_id'], $receivedAt, $windowEnd, $followUps, $staffMap);
        }

        $foundLabel = ! empty($best['note_id'])
            ? 'notes (Call Action) #' . $best['note_id']
            : 'activities_logs (Call) #' . ($best['activity_log_id'] ?? '?');

        $result = $this->finalizeVerdict(
            $followUps,
            $staffMap,
            $combinedConfidence,
            $best,
            $matchResult['client_suggestions'] ?? [],
            $receivedAt,
            $windowEnd,
            $followupHours,
            $foundLabel
        );

        if (is_string($insufficientReason) && $insufficientReason !== '') {
            $result['reasons'] = array_values(array_unique(array_merge(
                [$insufficientReason],
                $result['reasons'] ?? []
            )));
        }

        $result['reasons'][] = 'Call matching is weak without PBX data — confirm the Action relates to this Recents entry.';

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $followUps
     * @param  array<int, string>  $staffMap
     * @param  array<string, mixed>|null  $best
     * @param  list<array<string, mixed>>  $clientSuggestions
     * @return array<string, mixed>
     */
    private function finalizeVerdict(
        array $followUps,
        array $staffMap,
        int $combinedConfidence,
        ?array $best,
        array $clientSuggestions,
        Carbon $receivedAt,
        Carbon $windowEnd,
        int $followupHours,
        string $foundLabel
    ): array {
        $workedSignals = array_values(array_filter(
            $followUps,
            static fn (array $f) => in_array($f['type'], ['read', 'reply', 'note', 'action', 'sms'], true)
        ));

        if ($workedSignals !== []) {
            $verdict = 'worked';
            $label = 'Worked — logged and staff activity after receipt';
            $reasons = [
                'Found in ' . $foundLabel . '.',
                count($workedSignals) . ' follow-up signal(s) within ' . $followupHours . 'h.',
            ];
        } else {
            $verdict = 'logged';
            $label = 'Logged — in CRM, no clear staff follow-up in window';
            $reasons = [
                'Found in ' . $foundLabel . '.',
                'No read / reply / note / Action / SMS within ' . $followupHours . ' hours after receipt.',
            ];
            if ($followUps === []) {
                $verdict = 'gap';
                $label = 'Gap — in CRM with no staff activity';
            }
        }

        return [
            'verdict' => $verdict,
            'label' => $label,
            'confidence' => $combinedConfidence,
            'reasons' => $reasons,
            'matched_record' => $best,
            'follow_ups' => $followUps,
            'staff' => array_values(array_map(
                static fn ($id, $name) => ['id' => $id, 'name' => $name],
                array_keys($staffMap),
                $staffMap
            )),
            'client_suggestions' => $clientSuggestions,
            'window' => [
                'from' => $receivedAt->toIso8601String(),
                'to' => $windowEnd->toIso8601String(),
                'followup_hours' => $followupHours,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $followUps
     * @param  array<int, string>  $staffMap
     */
    private function appendThreadReplies(
        EmailLog $email,
        Carbon $from,
        Carbon $to,
        array &$followUps,
        array &$staffMap
    ): void {
        $subject = $this->normalizeSubject((string) $email->subject);
        if ($subject === '' || ! $email->client_id) {
            return;
        }

        $replies = EmailLog::query()
            ->with('uploader:id,first_name,last_name,email')
            ->where('id', '!=', $email->id)
            ->where('client_id', $email->client_id)
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('created_at', [$from, $to])
                    ->orWhereBetween('sent_at', [$from, $to])
                    ->orWhereBetween('fetch_mail_sent_time', [$from, $to]);
            })
            ->where(function ($q) use ($subject) {
                $q->where('subject', 'like', '%' . mb_substr($subject, 0, 60) . '%')
                    ->orWhere('mail_type', 'sent');
            })
            ->orderBy('id')
            ->limit(10)
            ->get();

        foreach ($replies as $reply) {
            $replySubject = $this->normalizeSubject((string) $reply->subject);
            $related = $replySubject === $subject
                || str_contains($replySubject, $subject)
                || str_contains($subject, $replySubject)
                || ($reply->mail_type === 'sent' && $reply->client_id == $email->client_id);

            if (! $related) {
                continue;
            }

            $name = null;
            if ($reply->user_id) {
                $name = $reply->uploader?->full_name ?? ('Staff #' . $reply->user_id);
                $staffMap[(int) $reply->user_id] = $name;
            }

            $followUps[] = [
                'type' => 'reply',
                'at' => $this->emailTimestamp($reply)?->toIso8601String(),
                'staff_id' => $reply->user_id ? (int) $reply->user_id : null,
                'staff_name' => $name,
                'detail' => 'Related email #' . $reply->id . ' (' . ($reply->mail_type ?: 'mail') . '): ' . ($reply->subject ?: '(no subject)'),
                'email_log_id' => (int) $reply->id,
            ];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $followUps
     * @param  array<int, string>  $staffMap
     */
    private function appendNotes(int $clientId, Carbon $from, Carbon $to, array &$followUps, array &$staffMap): void
    {
        if (! Schema::hasTable('notes')) {
            return;
        }

        $notes = Note::query()
            ->where('client_id', $clientId)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('id')
            ->limit(15)
            ->get(['id', 'user_id', 'title', 'is_action', 'task_group', 'created_at']);

        $staffIds = $notes->pluck('user_id')->filter()->unique()->all();
        $staffNames = $staffIds
            ? Staff::query()->whereIn('id', $staffIds)->get()->keyBy('id')
            : collect();

        foreach ($notes as $note) {
            $name = null;
            if ($note->user_id) {
                $staff = $staffNames->get($note->user_id);
                $name = $staff?->full_name ?? ('Staff #' . $note->user_id);
                $staffMap[(int) $note->user_id] = $name;
            }
            $isAction = (int) ($note->is_action ?? 0) === 1;
            $followUps[] = [
                'type' => $isAction ? 'action' : 'note',
                'at' => $note->created_at?->toIso8601String(),
                'staff_id' => $note->user_id ? (int) $note->user_id : null,
                'staff_name' => $name,
                'detail' => ($isAction ? 'Action' : 'Note') . ' #' . $note->id
                    . ($note->task_group ? ' [' . $note->task_group . ']' : '')
                    . ': ' . ($note->title ?: '(untitled)'),
            ];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $followUps
     * @param  array<int, string>  $staffMap
     */
    private function appendActivities(int $clientId, Carbon $from, Carbon $to, array &$followUps, array &$staffMap): void
    {
        if (! Schema::hasTable('activities_logs')) {
            return;
        }

        $rows = ActivitiesLog::query()
            ->where('client_id', $clientId)
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('id')
            ->limit(15)
            ->get(['id', 'created_by', 'subject', 'activity_type', 'task_group', 'created_at']);

        $staffIds = $rows->pluck('created_by')->filter()->unique()->all();
        $staffNames = $staffIds
            ? Staff::query()->whereIn('id', $staffIds)->get()->keyBy('id')
            : collect();

        foreach ($rows as $row) {
            $name = null;
            if ($row->created_by) {
                $staff = $staffNames->get($row->created_by);
                $name = $staff?->full_name ?? ('Staff #' . $row->created_by);
                $staffMap[(int) $row->created_by] = $name;
            }
            $type = strtolower((string) ($row->task_group ?: $row->activity_type ?: 'activity'));
            $isAction = in_array($type, ['call', 'checklist', 'review', 'query', 'urgent', 'personal task'], true)
                || str_contains(strtolower((string) $row->activity_type), 'followup');

            $followUps[] = [
                'type' => $isAction ? 'action' : 'note',
                'at' => $row->created_at?->toIso8601String(),
                'staff_id' => $row->created_by ? (int) $row->created_by : null,
                'staff_name' => $name,
                'detail' => 'Activity #' . $row->id
                    . ($row->task_group ? ' [' . $row->task_group . ']' : '')
                    . ': ' . ($row->subject ?: ($row->activity_type ?: 'activity')),
            ];
        }
    }

    /**
     * Related SMS after an email (email channel follow-up).
     *
     * @param  list<array<string, mixed>>  $followUps
     * @param  array<int, string>  $staffMap
     */
    private function appendSms(int $clientId, Carbon $from, Carbon $to, array &$followUps, array &$staffMap): void
    {
        if (! Schema::hasTable('sms_logs')) {
            return;
        }

        $rows = SmsLog::query()
            ->where('client_id', $clientId)
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('sent_at', [$from, $to])
                    ->orWhereBetween('created_at', [$from, $to]);
            })
            ->orderBy('id')
            ->limit(10)
            ->get();

        foreach ($rows as $row) {
            $senderId = $row->sender_id ?? null;
            $name = null;
            if ($senderId) {
                $staff = Staff::find($senderId);
                $name = $staff?->full_name ?? ('Staff #' . $senderId);
                $staffMap[(int) $senderId] = $name;
            }
            $followUps[] = [
                'type' => 'sms',
                'at' => ($row->sent_at ?? $row->created_at)?->toIso8601String(),
                'staff_id' => $senderId ? (int) $senderId : null,
                'staff_name' => $name,
                'detail' => 'SMS #' . $row->id,
            ];
        }
    }

    /**
     * Later SMS on same client (excluding the matched row) — reply / follow-up.
     *
     * @param  list<array<string, mixed>>  $followUps
     * @param  array<int, string>  $staffMap
     */
    private function appendRelatedSms(
        int $clientId,
        int $excludeSmsId,
        Carbon $from,
        Carbon $to,
        array &$followUps,
        array &$staffMap
    ): void {
        if (! Schema::hasTable('sms_logs')) {
            return;
        }

        $rows = SmsLog::query()
            ->where('client_id', $clientId)
            ->where('id', '!=', $excludeSmsId)
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('sent_at', [$from, $to])
                    ->orWhereBetween('created_at', [$from, $to]);
            })
            ->orderBy('id')
            ->limit(10)
            ->get();

        foreach ($rows as $row) {
            $senderId = $row->sender_id ?? null;
            $name = null;
            if ($senderId) {
                $staff = Staff::find($senderId);
                $name = $staff?->full_name ?? ('Staff #' . $senderId);
                $staffMap[(int) $senderId] = $name;
            }
            $followUps[] = [
                'type' => 'sms',
                'at' => ($row->sent_at ?? $row->created_at)?->toIso8601String(),
                'staff_id' => $senderId ? (int) $senderId : null,
                'staff_name' => $name,
                'detail' => 'Related SMS #' . $row->id . ($senderId ? ' (outbound)' : ' (inbound)'),
            ];
        }
    }

    private function emailTimestamp(EmailLog $email): ?Carbon
    {
        foreach (['received_date', 'fetch_mail_sent_time', 'sent_at', 'created_at'] as $field) {
            if ($email->{$field}) {
                try {
                    return Carbon::parse($email->{$field});
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return null;
    }

    private function smsTimestamp(SmsLog $sms): ?Carbon
    {
        foreach (['sent_at', 'delivered_at', 'created_at'] as $field) {
            if ($sms->{$field}) {
                try {
                    return Carbon::parse($sms->{$field});
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return null;
    }

    private function normalizeSubject(string $subject): string
    {
        $s = preg_replace('/^(re|fw|fwd)\s*:\s*/i', '', trim($subject)) ?? trim($subject);
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        return mb_strtolower(trim($s));
    }
}
