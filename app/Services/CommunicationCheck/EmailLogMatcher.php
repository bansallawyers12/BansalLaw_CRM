<?php

namespace App\Services\CommunicationCheck;

use App\Models\EmailLog;
use App\Services\EmailMatchingService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Deterministic matcher: extracted email fields → email_logs (+ client suggestions).
 */
class EmailLogMatcher
{
    public function __construct(
        private EmailMatchingService $matchingService
    ) {
    }

    /**
     * @param  array<string, mixed>  $extracted
     * @return array{
     *     candidates: list<array<string, mixed>>,
     *     best: array<string, mixed>|null,
     *     client_suggestions: list<array<string, mixed>>,
     *     match_confidence: int,
     *     matched_by: list<string>,
     *     search: array<string, mixed>
     * }
     */
    public function match(array $extracted, int $lookbackDays): array
    {
        $from = $this->normalizeEmail($extracted['from'] ?? null);
        $to = $this->normalizeEmail($extracted['to'] ?? null);
        $subject = trim((string) ($extracted['subject'] ?? ''));
        $snippet = trim((string) ($extracted['snippet'] ?? ''));
        $anchor = $this->resolveAnchorDate($extracted);
        $windowHours = (int) config('crm.communication_check.datetime_window_hours', 48);

        $query = EmailLog::query()->with([
            'uploader:id,first_name,last_name,email',
            'client:id,first_name,last_name,email',
            'matter:id,client_id,client_unique_matter_no,sel_matter_id',
        ]);

        $fromDate = now()->subDays(max(1, $lookbackDays))->startOfDay();
        if ($anchor) {
            $fromDate = $anchor->copy()->subHours($windowHours);
            $toDate = $anchor->copy()->addHours($windowHours);
            $query->where(function ($q) use ($fromDate, $toDate) {
                $q->whereBetween('received_date', [$fromDate, $toDate])
                    ->orWhereBetween('fetch_mail_sent_time', [$fromDate, $toDate])
                    ->orWhereBetween('created_at', [$fromDate, $toDate]);
            });
        } else {
            $query->where(function ($q) use ($fromDate) {
                $q->where('received_date', '>=', $fromDate)
                    ->orWhere('fetch_mail_sent_time', '>=', $fromDate)
                    ->orWhere('created_at', '>=', $fromDate);
            });
        }

        if ($from || $to || $subject !== '') {
            $query->where(function ($q) use ($from, $to, $subject) {
                if ($from) {
                    $q->orWhere('from_mail', 'like', '%' . $from . '%')
                        ->orWhere('to_mail', 'like', '%' . $from . '%');
                }
                if ($to) {
                    $q->orWhere('to_mail', 'like', '%' . $to . '%')
                        ->orWhere('from_mail', 'like', '%' . $to . '%');
                }
                if ($subject !== '') {
                    $normalized = $this->normalizeSubject($subject);
                    if ($normalized !== '') {
                        $q->orWhere('subject', 'like', '%' . mb_substr($normalized, 0, 80) . '%');
                    }
                }
            });
        }

        /** @var Collection<int, EmailLog> $rows */
        $rows = $query->orderByDesc('id')->limit(40)->get();

        $scored = [];
        foreach ($rows as $row) {
            $score = $this->scoreRow($row, $from, $to, $subject, $snippet, $anchor);
            if ($score['confidence'] < 25) {
                continue;
            }
            $scored[] = $this->formatCandidate($row, $score);
        }

        usort($scored, static fn (array $a, array $b) => $b['confidence'] <=> $a['confidence']);
        $candidates = array_slice($scored, 0, 5);
        $best = $candidates[0] ?? null;

        $clientSuggestions = $this->matchingService->suggestMatches([
            'subject' => $subject,
            'text_preview' => $snippet,
            'sender_email' => $from,
            'from_mail' => $from,
            'to_recipients' => $to ? [$to] : [],
        ]);

        return [
            'candidates' => $candidates,
            'best' => $best,
            'client_suggestions' => $clientSuggestions['suggestions'] ?? [],
            'match_confidence' => (int) ($best['confidence'] ?? 0),
            'matched_by' => $best['matched_by'] ?? [],
            'search' => [
                'from' => $from,
                'to' => $to,
                'subject' => $subject,
                'anchor' => $anchor?->toIso8601String(),
                'lookback_days' => $lookbackDays,
                'rows_considered' => $rows->count(),
            ],
        ];
    }

    /**
     * @return array{confidence: int, matched_by: list<string>}
     */
    private function scoreRow(
        EmailLog $row,
        ?string $from,
        ?string $to,
        string $subject,
        string $snippet,
        ?Carbon $anchor
    ): array {
        $confidence = 0;
        $matchedBy = [];

        $rowFrom = $this->normalizeEmail($row->from_mail);
        $rowTo = $this->normalizeEmail($row->to_mail);
        $rowSubject = (string) ($row->subject ?? '');

        if ($from && $rowFrom && $from === $rowFrom) {
            $confidence += 40;
            $matchedBy[] = 'from_email';
        } elseif ($from && $rowTo && str_contains($rowTo, $from)) {
            $confidence += 25;
            $matchedBy[] = 'from_in_to';
        }

        if ($to && $rowTo && str_contains($rowTo, $to)) {
            $confidence += 20;
            $matchedBy[] = 'to_email';
        } elseif ($to && $rowFrom && $to === $rowFrom) {
            $confidence += 15;
            $matchedBy[] = 'to_as_from';
        }

        $subjectScore = $this->subjectSimilarity($subject, $rowSubject);
        if ($subjectScore >= 0.85) {
            $confidence += 35;
            $matchedBy[] = 'subject_exact';
        } elseif ($subjectScore >= 0.55) {
            $confidence += 20;
            $matchedBy[] = 'subject_partial';
        }

        if ($snippet !== '') {
            $preview = (string) ($row->text_preview ?? '');
            if ($preview !== '' && mb_stripos($preview, mb_substr($snippet, 0, 40)) !== false) {
                $confidence += 10;
                $matchedBy[] = 'snippet';
            }
        }

        if ($anchor) {
            $rowAt = $this->rowTimestamp($row);
            if ($rowAt) {
                $hours = abs($rowAt->diffInMinutes($anchor)) / 60;
                if ($hours <= 2) {
                    $confidence += 15;
                    $matchedBy[] = 'datetime_close';
                } elseif ($hours <= 24) {
                    $confidence += 8;
                    $matchedBy[] = 'datetime_day';
                }
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
    private function formatCandidate(EmailLog $row, array $score): array
    {
        $at = $this->rowTimestamp($row);
        $staff = $row->uploader;
        $client = $row->client;
        $matter = $row->matter;

        return [
            'email_log_id' => (int) $row->id,
            'confidence' => $score['confidence'],
            'matched_by' => $score['matched_by'],
            'subject' => $row->subject,
            'from_mail' => $row->from_mail,
            'to_mail' => EmailLog::resolveRecipientDisplay($row->to_mail, $row->type),
            'mail_type' => $row->mail_type,
            'mail_is_read' => (bool) $row->mail_is_read,
            'sync_source' => $row->sync_source,
            'sync_source_label' => EmailLog::syncSourceLabel($row->sync_source),
            'received_at' => $at?->toIso8601String(),
            'client_id' => $row->client_id ? (int) $row->client_id : null,
            'client_name' => $client ? trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? '')) : null,
            'client_matter_id' => $row->client_matter_id ? (int) $row->client_matter_id : null,
            'matter_ref' => $matter->client_unique_matter_no ?? null,
            'matter_name' => null,
            'user_id' => $row->user_id ? (int) $row->user_id : null,
            'staff_name' => $staff?->full_name,
            'links' => [
                'client' => $row->client_id
                    ? route('clients.detail', ['client_id' => $row->client_id])
                    : null,
                'email_body' => route('email-logs.body', ['id' => $row->id]),
            ],
        ];
    }

    private function resolveAnchorDate(array $extracted): ?Carbon
    {
        $raw = $extracted['datetime'] ?? null;
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    private function rowTimestamp(EmailLog $row): ?Carbon
    {
        foreach (['received_date', 'fetch_mail_sent_time', 'sent_at', 'created_at'] as $field) {
            $value = $row->{$field} ?? null;
            if ($value) {
                try {
                    return Carbon::parse($value);
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return null;
    }

    private function normalizeEmail(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value, $m)) {
            return strtolower($m[0]);
        }

        $trimmed = strtolower(trim($value));

        return filter_var($trimmed, FILTER_VALIDATE_EMAIL) ? $trimmed : null;
    }

    private function normalizeSubject(string $subject): string
    {
        $s = preg_replace('/^(re|fw|fwd)\s*:\s*/i', '', trim($subject)) ?? trim($subject);
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        return mb_strtolower(trim($s));
    }

    private function subjectSimilarity(string $a, string $b): float
    {
        $na = $this->normalizeSubject($a);
        $nb = $this->normalizeSubject($b);
        if ($na === '' || $nb === '') {
            return 0.0;
        }
        if ($na === $nb) {
            return 1.0;
        }
        if (str_contains($nb, $na) || str_contains($na, $nb)) {
            return 0.8;
        }
        similar_text($na, $nb, $percent);

        return $percent / 100;
    }
}
