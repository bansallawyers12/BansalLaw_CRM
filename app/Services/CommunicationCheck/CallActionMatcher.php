<?php

namespace App\Services\CommunicationCheck;

use App\Models\ActivitiesLog;
use App\Models\Admin;
use App\Models\ClientContact;
use App\Models\Note;
use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Match call / Recents screenshots to Call Actions and notes.
 * Honest about weak evidence: no PBX CDR — phone + time window only.
 */
class CallActionMatcher
{
    /**
     * @param  array<string, mixed>  $extracted
     * @return array{
     *     candidates: list<array<string, mixed>>,
     *     best: array<string, mixed>|null,
     *     match_confidence: int,
     *     matched_by: list<string>,
     *     client_suggestions: list<array<string, mixed>>,
     *     search: array<string, mixed>,
     *     insufficient_data: bool,
     *     insufficient_reason: ?string
     * }
     */
    public function match(array $extracted, int $lookbackDays): array
    {
        $phone = $this->resolvePhone($extracted);
        $anchor = $this->resolveAnchorDate($extracted);
        $windowMinutes = (int) config('crm.communication_check.call_window_minutes', 30);
        $snippet = trim((string) ($extracted['snippet'] ?? $extracted['notes'] ?? ''));
        $direction = strtolower((string) ($extracted['direction'] ?? 'unknown'));

        $base = [
            'candidates' => [],
            'best' => null,
            'match_confidence' => 0,
            'matched_by' => [],
            'client_suggestions' => [],
            'search' => [
                'phone' => $phone,
                'phone_last9' => $phone ? PhoneNormalizer::lastDigits($phone) : null,
                'anchor' => $anchor?->toIso8601String(),
                'datetime_raw' => $extracted['datetime_raw'] ?? null,
                'lookback_days' => $lookbackDays,
                'window_minutes' => $windowMinutes,
                'rows_considered' => 0,
            ],
            'insufficient_data' => false,
            'insufficient_reason' => null,
        ];

        // Recents screenshots often lack a real number and only show "Missed · yesterday".
        if ($phone === null || PhoneNormalizer::lastDigits($phone) === '') {
            $base['insufficient_data'] = true;
            $base['insufficient_reason'] = 'No phone number visible. Without a number (or a PBX/VoIP call log), '
                . 'a Recents screenshot cannot be proven in CRM — only that no Call Action matched a guess.';

            return $base;
        }

        $base['client_suggestions'] = $this->suggestClientsByPhone($phone);

        if (! $anchor) {
            $base['insufficient_data'] = true;
            $base['insufficient_reason'] = 'No absolute datetime could be resolved from the screenshot '
                . '(relative times like “yesterday” / “2 min ago” are unreliable). '
                . 'CRM was still searched by phone over the lookback window.';
            // Continue with lookback-only search — weaker confidence.
        }

        $candidates = [];
        $notesConsidered = 0;
        $activitiesConsidered = 0;

        if (Schema::hasTable('notes')) {
            [$noteCandidates, $notesConsidered] = $this->matchNotes(
                $phone,
                $anchor,
                $lookbackDays,
                $windowMinutes,
                $snippet,
                $direction
            );
            $candidates = array_merge($candidates, $noteCandidates);
        }

        if (Schema::hasTable('activities_logs')) {
            [$activityCandidates, $activitiesConsidered] = $this->matchActivities(
                $phone,
                $anchor,
                $lookbackDays,
                $windowMinutes,
                $snippet
            );
            $candidates = array_merge($candidates, $activityCandidates);
        }

        usort($candidates, static fn (array $a, array $b) => $b['confidence'] <=> $a['confidence']);
        $candidates = array_slice($candidates, 0, 5);
        $best = $candidates[0] ?? null;

        $base['candidates'] = $candidates;
        $base['best'] = $best;
        $base['match_confidence'] = (int) ($best['confidence'] ?? 0);
        $base['matched_by'] = $best['matched_by'] ?? [];
        $base['search']['rows_considered'] = $notesConsidered + $activitiesConsidered;

        // Phone present but no Call Action → not "insufficient", it's a Gap candidate for the scorer.
        if ($best === null && $base['insufficient_data'] && $anchor === null) {
            // Keep insufficient flag — weak time evidence.
        } elseif ($best === null) {
            $base['insufficient_data'] = false;
            $base['insufficient_reason'] = null;
        }

        return $base;
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function matchNotes(
        string $phone,
        ?Carbon $anchor,
        int $lookbackDays,
        int $windowMinutes,
        string $snippet,
        string $direction
    ): array {
        $last9 = PhoneNormalizer::lastDigits($phone);
        $clientIds = $this->clientIdsForPhone($phone);

        $query = Note::query()
            ->with(['user:id,first_name,last_name,email', 'assignedUser:id,first_name,last_name,email', 'client:id,first_name,last_name,email,phone'])
            ->where(function ($q) {
                $q->where('task_group', 'Call')
                    ->orWhere(function ($q2) {
                        $q2->where('is_action', 1)
                            ->where(function ($q3) {
                                $q3->where('title', 'like', '%call%')
                                    ->orWhere('description', 'like', '%call%');
                            });
                    });
            });

        if ($anchor) {
            $from = $anchor->copy()->subMinutes($windowMinutes);
            $to = $anchor->copy()->addMinutes($windowMinutes);
            $query->where(function ($q) use ($from, $to) {
                $q->whereBetween('created_at', [$from, $to])
                    ->orWhereBetween('action_date', [$from, $to]);
            });
        } else {
            $from = now()->subDays(max(1, $lookbackDays))->startOfDay();
            $query->where(function ($q) use ($from) {
                $q->where('created_at', '>=', $from)
                    ->orWhere('action_date', '>=', $from);
            });
        }

        $query->where(function ($q) use ($last9, $clientIds) {
            $q->where('mobile_number', 'like', '%' . $last9 . '%');
            if ($clientIds !== []) {
                $q->orWhereIn('client_id', $clientIds);
            }
        });

        $rows = $query->orderByDesc('id')->limit(40)->get();
        $out = [];

        foreach ($rows as $note) {
            $score = $this->scoreNote($note, $phone, $anchor, $windowMinutes, $snippet, $clientIds);
            if ($score['confidence'] < 35) {
                continue;
            }
            $out[] = $this->formatNoteCandidate($note, $score);
        }

        return [$out, $rows->count()];
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function matchActivities(
        string $phone,
        ?Carbon $anchor,
        int $lookbackDays,
        int $windowMinutes,
        string $snippet
    ): array {
        $clientIds = $this->clientIdsForPhone($phone);
        if ($clientIds === []) {
            return [[], 0];
        }

        $query = ActivitiesLog::query()
            ->where('task_group', 'Call')
            ->whereIn('client_id', $clientIds);

        if ($anchor) {
            $from = $anchor->copy()->subMinutes($windowMinutes);
            $to = $anchor->copy()->addMinutes($windowMinutes);
            $query->whereBetween('created_at', [$from, $to]);
        } else {
            $query->where('created_at', '>=', now()->subDays(max(1, $lookbackDays))->startOfDay());
        }

        $rows = $query->orderByDesc('id')->limit(40)->get();
        $staffIds = $rows->pluck('created_by')->filter()->unique()->all();
        $staffNames = $staffIds
            ? Staff::query()->whereIn('id', $staffIds)->get()->keyBy('id')
            : collect();
        $clients = Admin::query()->whereIn('id', $clientIds)->get(['id', 'first_name', 'last_name'])->keyBy('id');

        $out = [];
        foreach ($rows as $row) {
            $confidence = 45; // client phone match + Call activity in window
            $matchedBy = ['client_phone', 'task_group_call'];

            if ($anchor && $row->created_at) {
                $mins = abs(Carbon::parse($row->created_at)->diffInMinutes($anchor));
                if ($mins <= 15) {
                    $confidence += 30;
                    $matchedBy[] = 'datetime_close';
                } elseif ($mins <= $windowMinutes) {
                    $confidence += 18;
                    $matchedBy[] = 'datetime_window';
                }
            }

            if ($snippet !== '' && $row->subject
                && mb_stripos((string) $row->subject, mb_substr($snippet, 0, 30)) !== false) {
                $confidence += 8;
                $matchedBy[] = 'snippet';
            }

            $staff = $row->created_by ? $staffNames->get($row->created_by) : null;
            $client = $clients->get($row->client_id);

            $out[] = [
                'record_type' => 'activity_call',
                'activity_log_id' => (int) $row->id,
                'note_id' => null,
                'confidence' => min(100, $confidence),
                'matched_by' => array_values(array_unique($matchedBy)),
                'title' => $row->subject ?: 'Call activity',
                'task_group' => $row->task_group,
                'phone' => $phone,
                'status' => (int) ($row->task_status ?? 0) === 1 ? 'completed' : 'open',
                'created_at' => $row->created_at?->toIso8601String(),
                'client_id' => $row->client_id ? (int) $row->client_id : null,
                'client_name' => $client ? trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? '')) : null,
                'staff_id' => $row->created_by ? (int) $row->created_by : null,
                'staff_name' => $staff?->full_name,
                'links' => [
                    'client' => $row->client_id
                        ? route('clients.detail', ['client_id' => $row->client_id])
                        : null,
                ],
            ];
        }

        return [$out, $rows->count()];
    }

    /**
     * @param  list<int>  $clientIds
     * @return array{confidence: int, matched_by: list<string>}
     */
    private function scoreNote(
        Note $note,
        string $phone,
        ?Carbon $anchor,
        int $windowMinutes,
        string $snippet,
        array $clientIds
    ): array {
        $confidence = 0;
        $matchedBy = [];

        if ($note->mobile_number && PhoneNormalizer::matches($phone, $note->mobile_number)) {
            $confidence += 50;
            $matchedBy[] = 'note_phone';
        } elseif ($note->client_id && in_array((int) $note->client_id, $clientIds, true)) {
            $confidence += 35;
            $matchedBy[] = 'client_phone';
        }

        if (strcasecmp((string) $note->task_group, 'Call') === 0) {
            $confidence += 15;
            $matchedBy[] = 'task_group_call';
        }

        $at = $note->action_date ?? $note->created_at;
        if ($anchor && $at) {
            try {
                $mins = abs(Carbon::parse($at)->diffInMinutes($anchor));
                if ($mins <= 15) {
                    $confidence += 30;
                    $matchedBy[] = 'datetime_close';
                } elseif ($mins <= $windowMinutes) {
                    $confidence += 18;
                    $matchedBy[] = 'datetime_window';
                } elseif ($mins <= 120) {
                    $confidence += 8;
                    $matchedBy[] = 'datetime_loose';
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        if ($snippet !== '') {
            $hay = trim(($note->title ?? '') . ' ' . strip_tags((string) ($note->description ?? '')));
            if ($hay !== '' && mb_stripos($hay, mb_substr($snippet, 0, 30)) !== false) {
                $confidence += 8;
                $matchedBy[] = 'snippet';
            }
        }

        return [
            'confidence' => min(100, $confidence),
            'matched_by' => array_values(array_unique($matchedBy)),
        ];
    }

    /**
     * @param  array{confidence: int, matched_by: list<string>}  $score
     * @return array<string, mixed>
     */
    private function formatNoteCandidate(Note $note, array $score): array
    {
        $client = $note->client;
        $staff = $note->user;
        $assignee = $note->assignedUser;
        $at = $note->action_date ?? $note->created_at;
        $completed = (string) ($note->status ?? '0') === '1';

        return [
            'record_type' => 'note_call',
            'note_id' => (int) $note->id,
            'activity_log_id' => null,
            'confidence' => $score['confidence'],
            'matched_by' => $score['matched_by'],
            'title' => $note->title ?: 'Call Action',
            'task_group' => $note->task_group,
            'phone' => $note->mobile_number,
            'status' => $completed ? 'completed' : 'open',
            'is_action' => (int) ($note->is_action ?? 0) === 1,
            'created_at' => $at?->toIso8601String(),
            'client_id' => $note->client_id ? (int) $note->client_id : null,
            'client_name' => $client ? trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? '')) : null,
            'staff_id' => $note->user_id ? (int) $note->user_id : null,
            'staff_name' => $staff?->full_name,
            'assigned_to' => $note->assigned_to ? (int) $note->assigned_to : null,
            'assignee_name' => $assignee?->full_name,
            'links' => [
                'client' => $note->client_id
                    ? route('clients.detail', ['client_id' => $note->client_id])
                    : null,
            ],
        ];
    }

    /**
     * @return list<int>
     */
    private function clientIdsForPhone(string $phone): array
    {
        $last9 = PhoneNormalizer::lastDigits($phone);
        if ($last9 === '') {
            return [];
        }

        $ids = [];

        if (Schema::hasTable('client_contacts')) {
            $contacts = ClientContact::query()
                ->where('phone', 'like', '%' . $last9)
                ->limit(20)
                ->get(['client_id', 'admin_id']);
            foreach ($contacts as $c) {
                $id = (int) ($c->client_id ?: $c->admin_id);
                if ($id > 0) {
                    $ids[$id] = true;
                }
            }
        }

        $admins = Admin::query()
            ->where('phone', 'like', '%' . $last9)
            ->limit(20)
            ->pluck('id');
        foreach ($admins as $id) {
            $ids[(int) $id] = true;
        }

        return array_map('intval', array_keys($ids));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function suggestClientsByPhone(?string $phone): array
    {
        if ($phone === null || PhoneNormalizer::lastDigits($phone) === '') {
            return [];
        }

        $suggestions = [];
        foreach ($this->clientIdsForPhone($phone) as $clientId) {
            $client = Admin::query()->find($clientId, ['id', 'first_name', 'last_name', 'email', 'phone']);
            if (! $client) {
                continue;
            }
            $suggestions[] = [
                'client_id' => $clientId,
                'client_name' => trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? '')),
                'email' => $client->email,
                'phone' => $client->phone,
                'confidence' => 60,
                'matched_by' => ['client_phone'],
                'record_type' => 'client',
            ];
        }

        return array_slice($suggestions, 0, 5);
    }

    private function resolvePhone(array $extracted): ?string
    {
        foreach (['phone', 'from', 'to'] as $key) {
            $value = $extracted[$key] ?? null;
            $found = PhoneNormalizer::extractFromText(is_string($value) ? $value : null);
            if ($found) {
                return $found;
            }
        }

        return null;
    }

    private function resolveAnchorDate(array $extracted): ?Carbon
    {
        $raw = $extracted['datetime'] ?? null;
        if (is_string($raw) && trim($raw) !== '') {
            try {
                return Carbon::parse($raw);
            } catch (\Throwable) {
                // fall through
            }
        }

        $rawText = strtolower(trim((string) ($extracted['datetime_raw'] ?? '')));
        if ($rawText === '') {
            return null;
        }

        // Conservative relative parsing — only clear patterns.
        if (preg_match('/\b(\d+)\s*min/', $rawText, $m)) {
            return now()->subMinutes((int) $m[1]);
        }
        if (str_contains($rawText, 'yesterday')) {
            return now()->subDay()->setTime(12, 0);
        }
        if (preg_match('/\b(\d+)\s*h(our)?s?\b/', $rawText, $m)) {
            return now()->subHours((int) $m[1]);
        }

        try {
            return Carbon::parse($rawText);
        } catch (\Throwable) {
            return null;
        }
    }
}
