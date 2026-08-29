<?php

namespace App\Services\CommunicationCheck;

use App\Models\Admin;
use App\Models\ClientContact;
use App\Models\SmsLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Match extracted SMS fields to sms_logs (phone + time first, body as tie-break).
 */
class SmsLogMatcher
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
     *     inbound_warning: ?string
     * }
     */
    public function match(array $extracted, int $lookbackDays): array
    {
        $phone = $this->resolvePhone($extracted);
        $snippet = trim((string) ($extracted['snippet'] ?? ''));
        $direction = strtolower((string) ($extracted['direction'] ?? 'unknown'));
        $anchor = $this->resolveAnchorDate($extracted);
        $windowHours = (int) config('crm.communication_check.datetime_window_hours', 48);

        $empty = [
            'candidates' => [],
            'best' => null,
            'match_confidence' => 0,
            'matched_by' => [],
            'client_suggestions' => $this->suggestClientsByPhone($phone),
            'search' => [
                'phone' => $phone,
                'phone_last9' => PhoneNormalizer::lastDigits($phone),
                'anchor' => $anchor?->toIso8601String(),
                'lookback_days' => $lookbackDays,
                'rows_considered' => 0,
            ],
            'inbound_warning' => null,
        ];

        if ($phone === null || PhoneNormalizer::lastDigits($phone) === '') {
            $empty['inbound_warning'] = 'No phone number visible in the screenshot — SMS matching needs a number.';

            return $empty;
        }

        $last9 = PhoneNormalizer::lastDigits($phone);
        $query = SmsLog::query()->with([
            'sender:id,first_name,last_name,email',
            'client:id,first_name,last_name,email,phone',
        ]);

        $fromDate = now()->subDays(max(1, $lookbackDays))->startOfDay();
        if ($anchor) {
            $fromDate = $anchor->copy()->subHours($windowHours);
            $toDate = $anchor->copy()->addHours($windowHours);
            $query->where(function ($q) use ($fromDate, $toDate) {
                $q->whereBetween('sent_at', [$fromDate, $toDate])
                    ->orWhereBetween('created_at', [$fromDate, $toDate]);
            });
        } else {
            $query->where(function ($q) use ($fromDate) {
                $q->where('sent_at', '>=', $fromDate)
                    ->orWhere('created_at', '>=', $fromDate);
            });
        }

        // Phone filter: last-9 digit match on recipient / formatted fields
        $query->where(function ($q) use ($last9) {
            $q->where('recipient_phone', 'like', '%' . $last9)
                ->orWhere('formatted_phone', 'like', '%' . $last9);
            // Also match when leading digits vary (+61 vs 0)
            if (strlen($last9) >= 8) {
                $short = substr($last9, -8);
                $q->orWhere('recipient_phone', 'like', '%' . $short)
                    ->orWhere('formatted_phone', 'like', '%' . $short);
            }
        });

        /** @var Collection<int, SmsLog> $rows */
        $rows = $query->orderByDesc('id')->limit(40)->get();

        $scored = [];
        foreach ($rows as $row) {
            if (! PhoneNormalizer::matches($phone, $row->recipient_phone)
                && ! PhoneNormalizer::matches($phone, $row->formatted_phone)) {
                continue;
            }
            $score = $this->scoreRow($row, $phone, $snippet, $anchor, $direction);
            if ($score['confidence'] < 30) {
                continue;
            }
            $scored[] = $this->formatCandidate($row, $score);
        }

        usort($scored, static fn (array $a, array $b) => $b['confidence'] <=> $a['confidence']);
        $candidates = array_slice($scored, 0, 5);
        $best = $candidates[0] ?? null;

        $inboundWarning = null;
        if ($direction === 'incoming') {
            $hasInboundRow = collect($candidates)->contains(
                static fn (array $c) => ($c['direction_inferred'] ?? '') === 'inbound'
            );
            if (! $hasInboundRow && $candidates !== []) {
                $inboundWarning = 'Screenshot looks inbound, but matched CRM rows look outbound-only. Inbound SMS storage may be thin.';
            } elseif ($candidates === []) {
                $inboundWarning = 'Incoming SMS screenshots only match if Cellcast inbound webhook wrote a sms_logs row.';
            }
        }

        return [
            'candidates' => $candidates,
            'best' => $best,
            'match_confidence' => (int) ($best['confidence'] ?? 0),
            'matched_by' => $best['matched_by'] ?? [],
            'client_suggestions' => $this->suggestClientsByPhone($phone),
            'search' => [
                'phone' => $phone,
                'phone_last9' => $last9,
                'anchor' => $anchor?->toIso8601String(),
                'lookback_days' => $lookbackDays,
                'rows_considered' => $rows->count(),
            ],
            'inbound_warning' => $inboundWarning,
        ];
    }

    /**
     * @return array{confidence: int, matched_by: list<string>}
     */
    private function scoreRow(
        SmsLog $row,
        string $phone,
        string $snippet,
        ?Carbon $anchor,
        string $direction
    ): array {
        $confidence = 0;
        $matchedBy = [];

        if (PhoneNormalizer::matches($phone, $row->recipient_phone)
            || PhoneNormalizer::matches($phone, $row->formatted_phone)) {
            $confidence += 50;
            $matchedBy[] = 'phone';
        }

        if ($anchor) {
            $at = $row->sent_at ?? $row->created_at;
            if ($at) {
                try {
                    $rowAt = Carbon::parse($at);
                    $hours = abs($rowAt->diffInMinutes($anchor)) / 60;
                    if ($hours <= 1) {
                        $confidence += 30;
                        $matchedBy[] = 'datetime_close';
                    } elseif ($hours <= 6) {
                        $confidence += 18;
                        $matchedBy[] = 'datetime_near';
                    } elseif ($hours <= 24) {
                        $confidence += 10;
                        $matchedBy[] = 'datetime_day';
                    }
                } catch (\Throwable) {
                    // ignore
                }
            }
        }

        if ($snippet !== '') {
            $body = (string) ($row->message_content ?? '');
            $needle = mb_substr(preg_replace('/\s+/u', ' ', $snippet) ?? $snippet, 0, 40);
            if ($body !== '' && $needle !== '' && mb_stripos($body, $needle) !== false) {
                $confidence += 15;
                $matchedBy[] = 'snippet';
            } elseif ($body !== '' && $needle !== '') {
                similar_text(mb_strtolower($needle), mb_strtolower(mb_substr($body, 0, 80)), $pct);
                if ($pct >= 55) {
                    $confidence += 8;
                    $matchedBy[] = 'snippet_fuzzy';
                }
            }
        }

        $inferred = $this->inferDirection($row);
        if ($direction === 'incoming' && $inferred === 'inbound') {
            $confidence += 5;
            $matchedBy[] = 'direction_inbound';
        } elseif ($direction === 'outgoing' && $inferred === 'outbound') {
            $confidence += 5;
            $matchedBy[] = 'direction_outbound';
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
    private function formatCandidate(SmsLog $row, array $score): array
    {
        $client = $row->client;
        $sender = $row->sender;
        $at = $row->sent_at ?? $row->created_at;
        $direction = $this->inferDirection($row);

        return [
            'record_type' => 'sms_log',
            'sms_log_id' => (int) $row->id,
            'confidence' => $score['confidence'],
            'matched_by' => $score['matched_by'],
            'phone' => $row->formatted_phone ?: $row->recipient_phone,
            'snippet' => mb_substr((string) ($row->message_content ?? ''), 0, 120),
            'status' => $row->status,
            'message_type' => $row->message_type,
            'direction_inferred' => $direction,
            'sent_at' => $at?->toIso8601String(),
            'client_id' => $row->client_id ? (int) $row->client_id : null,
            'client_name' => $client ? trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? '')) : null,
            'sender_id' => $row->sender_id ? (int) $row->sender_id : null,
            'staff_name' => $sender?->full_name,
            'links' => [
                'client' => $row->client_id
                    ? route('clients.detail', ['client_id' => $row->client_id])
                    : null,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function suggestClientsByPhone(?string $phone): array
    {
        if ($phone === null || PhoneNormalizer::lastDigits($phone) === '') {
            return [];
        }

        $last9 = PhoneNormalizer::lastDigits($phone);
        $suggestions = [];

        if (Schema::hasTable('client_contacts')) {
            $contacts = ClientContact::query()
                ->where('phone', 'like', '%' . $last9)
                ->limit(10)
                ->get(['id', 'admin_id', 'client_id', 'phone']);

            foreach ($contacts as $contact) {
                $clientId = (int) ($contact->client_id ?: $contact->admin_id);
                if ($clientId <= 0) {
                    continue;
                }
                $client = Admin::query()->find($clientId, ['id', 'first_name', 'last_name', 'email']);
                if (! $client) {
                    continue;
                }
                $suggestions[$clientId] = [
                    'client_id' => $clientId,
                    'client_name' => trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? '')),
                    'email' => $client->email,
                    'phone' => $contact->phone,
                    'confidence' => 70,
                    'matched_by' => ['contact_phone'],
                    'record_type' => 'client',
                ];
            }
        }

        $admins = Admin::query()
            ->where('phone', 'like', '%' . $last9)
            ->limit(10)
            ->get(['id', 'first_name', 'last_name', 'email', 'phone']);

        foreach ($admins as $admin) {
            $id = (int) $admin->id;
            if (isset($suggestions[$id])) {
                continue;
            }
            $suggestions[$id] = [
                'client_id' => $id,
                'client_name' => trim(($admin->first_name ?? '') . ' ' . ($admin->last_name ?? '')),
                'email' => $admin->email,
                'phone' => $admin->phone,
                'confidence' => 65,
                'matched_by' => ['client_phone'],
                'record_type' => 'client',
            ];
        }

        $list = array_values($suggestions);
        usort($list, static fn (array $a, array $b) => $b['confidence'] <=> $a['confidence']);

        return array_slice($list, 0, 5);
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
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    private function inferDirection(SmsLog $row): string
    {
        // Cellcast inbound webhook stores sender_id null; outbound CRM sends set sender_id.
        if ($row->sender_id === null) {
            return 'inbound';
        }

        return 'outbound';
    }
}
