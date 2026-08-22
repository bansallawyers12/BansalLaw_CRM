<?php

namespace App\Services\Email;

use App\Models\ClientCourtHearing;
use App\Models\EmailCalendarLink;
use App\Models\EmailLog;
use App\Models\StaffCalendarEvent;
use App\Support\CalendarEventText;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class EmailCalendarMergeService
{
    /** @var list<string> */
    protected array $scheduleKeywords = [
        'directions hearing',
        'case management',
        'court hearing',
        'court date',
        'hearing',
        'interview',
        'walk in',
        'walk-in',
        'walkin',
        'appointment',
        'consultation',
        'mention',
        'mediation',
        'conference',
        'tribunal',
        'listing',
    ];

    /**
     * @param  array<int, array{filename: string, content: string}>  $icsAttachments
     * @return array{merged: int, pending: int, links: list<EmailCalendarLink>}
     */
    public function mergeFromEmail(EmailLog $emailLog, ?int $staffUserId = null, array $icsAttachments = []): array
    {
        if (! Schema::hasTable('email_calendar_links')) {
            return ['merged' => 0, 'pending' => 0, 'links' => []];
        }

        if ((int) ($emailLog->mail_type ?? 0) !== 1) {
            return ['merged' => 0, 'pending' => 0, 'links' => []];
        }

        $mailBodyType = strtolower((string) ($emailLog->mail_body_type ?? 'inbox'));
        if ($mailBodyType === 'sent') {
            return ['merged' => 0, 'pending' => 0, 'links' => []];
        }

        $detectedEvents = $this->extractEvents($emailLog, $icsAttachments);
        if ($detectedEvents === []) {
            return ['merged' => 0, 'pending' => 0, 'links' => []];
        }

        $hasClient = ! empty($emailLog->client_id);
        $merged = 0;
        $pending = 0;
        $links = [];

        foreach ($detectedEvents as $event) {
            if ($this->linkExists($emailLog->id, $event)) {
                continue;
            }

            try {
                if ($hasClient) {
                    $link = $this->createMergedLink($emailLog, $event, $staffUserId);
                    $merged++;
                } else {
                    $link = EmailCalendarLink::create([
                        'email_log_id' => $emailLog->id,
                        'calendar_type' => $this->calendarTypeForEvent($event['event_type']),
                        'calendar_id' => null,
                        'event_type' => $event['event_type'],
                        'event_title' => $event['title'],
                        'starts_at' => $event['starts_at'],
                        'ends_at' => $event['ends_at'] ?? null,
                        'location' => $event['location'] ?? null,
                        'source' => $event['source'],
                        'status' => EmailCalendarLink::STATUS_PENDING,
                    ]);
                    $pending++;
                }

                $links[] = $link;
            } catch (Throwable $e) {
                Log::warning('Email calendar merge failed for detected event', [
                    'email_log_id' => $emailLog->id,
                    'event_title' => $event['title'] ?? '',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['merged' => $merged, 'pending' => $pending, 'links' => $links];
    }

    /**
     * Promote pending calendar links after an email is assigned to a client.
     *
     * @return array{merged: int, links: list<EmailCalendarLink>}
     */
    public function mergePendingForEmail(EmailLog $emailLog, ?int $staffUserId = null): array
    {
        if (! Schema::hasTable('email_calendar_links') || empty($emailLog->client_id)) {
            return ['merged' => 0, 'links' => []];
        }

        $pendingLinks = EmailCalendarLink::query()
            ->where('email_log_id', $emailLog->id)
            ->where('status', EmailCalendarLink::STATUS_PENDING)
            ->get();

        $merged = 0;
        $links = [];

        foreach ($pendingLinks as $pendingLink) {
            try {
                $startsAt = $pendingLink->starts_at;
                $endsAt = $pendingLink->ends_at;
                if (! $startsAt instanceof Carbon) {
                    continue;
                }

                $isAllDay = $startsAt->format('H:i:s') === '00:00:00'
                    && ($endsAt === null || ($endsAt instanceof Carbon && $endsAt->format('H:i:s') === '00:00:00'));

                $event = [
                    'title' => $pendingLink->event_title,
                    'event_type' => $pendingLink->event_type,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'location' => $pendingLink->location,
                    'source' => $pendingLink->source,
                    'is_all_day' => $isAllDay,
                ];

                $calendarId = $this->createCalendarRecord($emailLog, $event, $staffUserId);
                $pendingLink->calendar_id = $calendarId;
                $pendingLink->calendar_type = $this->calendarTypeForEvent($event['event_type']);
                $pendingLink->status = EmailCalendarLink::STATUS_MERGED;
                $pendingLink->save();

                $merged++;
                $links[] = $pendingLink;
            } catch (Throwable $e) {
                Log::warning('Failed to promote pending email calendar link', [
                    'email_calendar_link_id' => $pendingLink->id,
                    'email_log_id' => $emailLog->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($merged === 0) {
            $result = $this->mergeFromEmail($emailLog->fresh(['attachments']), $staffUserId, $this->loadIcsAttachmentsFromEmail($emailLog));
            return ['merged' => $result['merged'], 'links' => $result['links']];
        }

        return ['merged' => $merged, 'links' => $links];
    }

    /**
     * @param  array<int, array{filename: string, content: string}>  $icsAttachments
     * @return list<array{title: string, event_type: string, starts_at: Carbon, ends_at: ?Carbon, location: ?string, source: string, is_all_day: bool}>
     */
    public function extractEvents(EmailLog $emailLog, array $icsAttachments = []): array
    {
        $events = [];

        foreach ($icsAttachments as $attachment) {
            $events = array_merge($events, $this->parseIcsContent($attachment['content'] ?? '', 'ics_attachment'));
        }

        $inlineIcs = $this->extractInlineIcsBlocks(strip_tags((string) ($emailLog->message ?? '')));
        foreach ($inlineIcs as $icsBlock) {
            $events = array_merge($events, $this->parseIcsContent($icsBlock, 'ics_inline'));
        }

        $plainText = $this->plainTextFromEmail($emailLog);
        $textEvents = $this->parseTextForScheduledEvents(
            (string) ($emailLog->subject ?? ''),
            $plainText
        );
        $events = array_merge($events, $textEvents);

        return $this->dedupeEvents($events);
    }

    /**
     * @return list<array{filename: string, content: string}>
     */
    public function loadIcsAttachmentsFromEmail(EmailLog $emailLog): array
    {
        $icsAttachments = [];

        foreach ($emailLog->attachments()->get() as $attachment) {
            if (! $this->isCalendarAttachment($attachment->filename ?? '', $attachment->content_type ?? '', $attachment->extension ?? '')) {
                continue;
            }

            $content = $this->readAttachmentContent($attachment->s3_key ?? '', $attachment->file_path ?? '');
            if ($content !== '') {
                $icsAttachments[] = [
                    'filename' => (string) ($attachment->filename ?? 'invite.ics'),
                    'content' => $content,
                ];
            }
        }

        return $icsAttachments;
    }

    public function isCalendarAttachment(?string $filename, ?string $contentType, ?string $extension = null): bool
    {
        $name = strtolower((string) $filename);
        $ext = strtolower((string) ($extension ?: pathinfo($name, PATHINFO_EXTENSION)));
        $type = strtolower((string) $contentType);

        return $ext === 'ics'
            || str_ends_with($name, '.ics')
            || str_contains($type, 'text/calendar')
            || str_contains($type, 'application/ics');
    }

    /**
     * @return list<array{title: string, event_type: string, starts_at: Carbon, ends_at: ?Carbon, location: ?string, source: string, is_all_day: bool}>
     */
    public function parseIcsContent(string $icsContent, string $source = 'ics_attachment'): array
    {
        if (trim($icsContent) === '') {
            return [];
        }

        $normalized = str_replace(["\r\n", "\r"], "\n", $icsContent);
        $events = [];
        $blocks = preg_split('/BEGIN:VEVENT\s*/i', $normalized) ?: [];

        foreach ($blocks as $block) {
            if (! str_contains(strtoupper($block), 'END:VEVENT')) {
                continue;
            }

            $summary = $this->icsFieldValue($block, 'SUMMARY') ?: 'Calendar event';
            $location = CalendarEventText::sanitizeLocation($this->icsFieldValue($block, 'LOCATION'));
            $description = $this->icsFieldValue($block, 'DESCRIPTION') ?? '';
            $startsAt = $this->parseIcsDateTime($this->icsFieldValue($block, 'DTSTART'));
            $endsAt = $this->parseIcsDateTime($this->icsFieldValue($block, 'DTEND'));

            if (! $startsAt) {
                continue;
            }

            $classificationText = strtolower($summary . ' ' . $description);
            $eventType = $this->classifyEventType($classificationText) ?? 'meeting';
            $isAllDay = $this->icsValueIsAllDay($this->icsFieldValue($block, 'DTSTART'));

            $events[] = [
                'title' => $this->cleanStaffEventTitle($summary, $eventType),
                'event_type' => $eventType,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'location' => $location,
                'source' => $source,
                'is_all_day' => $isAllDay,
            ];
        }

        return $events;
    }

    /**
     * @return list<array{title: string, event_type: string, starts_at: Carbon, ends_at: ?Carbon, location: ?string, source: string, is_all_day: bool}>
     */
    public function parseTextForScheduledEvents(string $subject, string $body): array
    {
        $combined = trim($subject . "\n" . $body);
        if ($combined === '') {
            return [];
        }

        if (! $this->containsScheduleKeyword($combined)) {
            return [];
        }

        $events = [];
        $lines = preg_split('/\R+/', $combined) ?: [];
        $timezone = config('app.timezone', 'Australia/Melbourne');

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || CalendarEventText::isNoiseScheduleLine($line) || ! $this->containsScheduleKeyword($line)) {
                continue;
            }

            foreach ($this->extractDatesFromLine($line, $timezone) as $dateInfo) {
                $events[] = [
                    'title' => $this->buildEventTitle($subject, $line, $dateInfo['event_type']),
                    'event_type' => $dateInfo['event_type'],
                    'starts_at' => $dateInfo['starts_at'],
                    'ends_at' => $dateInfo['ends_at'] ?? null,
                    'location' => $dateInfo['location'] ?? null,
                    'source' => 'text_detection',
                    'is_all_day' => $dateInfo['is_all_day'] ?? false,
                ];
            }
        }

        if ($events === [] && $this->containsScheduleKeyword($combined) && ! CalendarEventText::isNoiseScheduleLine($combined)) {
            foreach ($this->extractDatesFromLine($combined, $timezone) as $dateInfo) {
                $events[] = [
                    'title' => $this->buildEventTitle($subject, $combined, $dateInfo['event_type']),
                    'event_type' => $dateInfo['event_type'],
                    'starts_at' => $dateInfo['starts_at'],
                    'ends_at' => $dateInfo['ends_at'] ?? null,
                    'location' => $dateInfo['location'] ?? null,
                    'source' => 'text_detection',
                    'is_all_day' => $dateInfo['is_all_day'] ?? false,
                ];
            }
        }

        return $events;
    }

    /**
     * @param  array{title: string, event_type: string, starts_at: Carbon, ends_at: ?Carbon, location: ?string, source: string, is_all_day: bool}  $event
     */
    protected function createMergedLink(EmailLog $emailLog, array $event, ?int $staffUserId): EmailCalendarLink
    {
        $calendarId = $this->createCalendarRecord($emailLog, $event, $staffUserId);

        return EmailCalendarLink::create([
            'email_log_id' => $emailLog->id,
            'calendar_type' => $this->calendarTypeForEvent($event['event_type']),
            'calendar_id' => $calendarId,
            'event_type' => $event['event_type'],
            'event_title' => $event['title'],
            'starts_at' => $event['starts_at'],
            'ends_at' => $event['ends_at'] ?? null,
            'location' => $event['location'] ?? null,
            'source' => $event['source'],
            'status' => EmailCalendarLink::STATUS_MERGED,
        ]);
    }

    /**
     * @param  array{title: string, event_type: string, starts_at: Carbon, ends_at: ?Carbon, location: ?string, source: string, is_all_day: bool}  $event
     */
    protected function createCalendarRecord(EmailLog $emailLog, array $event, ?int $staffUserId): int
    {
        $notes = trim('Auto-created from email: ' . ($emailLog->subject ?: '(No subject)'));
        if (! empty($emailLog->id)) {
            $notes .= ' (Email #' . $emailLog->id . ')';
        }

        $location = CalendarEventText::sanitizeLocation($event['location'] ?? null);
        $isAllDay = (bool) ($event['is_all_day'] ?? false);
        $startsAt = $event['starts_at'] ?? null;
        if (! $startsAt instanceof Carbon) {
            throw new \InvalidArgumentException('Calendar event is missing a start time.');
        }

        if ($this->calendarTypeForEvent($event['event_type']) === EmailCalendarLink::TYPE_COURT_HEARING) {
            $hearingType = CalendarEventText::sanitizeHearingType(
                ucfirst(str_replace('_', ' ', $event['event_type']))
            );
            $hearingTime = $isAllDay ? null : $startsAt->format('H:i');

            $existingId = $this->findExistingHearingId(
                (int) $emailLog->client_id,
                $startsAt->toDateString(),
                $hearingTime
            );
            if ($existingId !== null) {
                return $existingId;
            }

            $hearing = ClientCourtHearing::create([
                'client_id' => (int) $emailLog->client_id,
                'client_matter_id' => $emailLog->client_matter_id ?: null,
                'court_name' => $location,
                'hearing_date' => $startsAt->toDateString(),
                'hearing_time' => $hearingTime,
                'hearing_type' => $hearingType,
                'notes' => $notes,
                'status' => 'Scheduled',
            ]);

            return (int) $hearing->id;
        }

        $endsAt = $event['ends_at'] ?: $startsAt->copy()->addHour();
        $title = $this->cleanStaffEventTitle((string) ($event['title'] ?? ''), $event['event_type']);

        $existingStaffId = $this->findExistingStaffEventId(
            $emailLog->client_id ? (int) $emailLog->client_id : null,
            $startsAt,
            $isAllDay,
            $this->staffEventType($event['event_type'])
        );
        if ($existingStaffId !== null) {
            return $existingStaffId;
        }

        $staffEvent = StaffCalendarEvent::create([
            'title' => mb_substr($title, 0, 255),
            'event_type' => $this->staffEventType($event['event_type']),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'is_all_day' => $isAllDay,
            'client_id' => $emailLog->client_id ?: null,
            'client_matter_id' => $emailLog->client_matter_id ?: null,
            'location' => $location,
            'notes' => $notes,
            'created_by_staff_id' => $staffUserId ?: null,
        ]);

        return (int) $staffEvent->id;
    }

    protected function findExistingHearingId(int $clientId, string $hearingDate, ?string $hearingTime): ?int
    {
        if (! Schema::hasTable('client_court_hearings') || $clientId <= 0) {
            return null;
        }

        $query = ClientCourtHearing::query()
            ->where('client_id', $clientId)
            ->whereDate('hearing_date', $hearingDate);

        if ($hearingTime !== null) {
            $query->where(function ($q) use ($hearingTime) {
                $q->whereNull('hearing_time')
                    ->orWhereTime('hearing_time', $hearingTime)
                    ->orWhere('hearing_time', $hearingTime)
                    ->orWhere('hearing_time', $hearingTime . ':00');
            });
        }

        $id = $query->orderByRaw('CASE WHEN hearing_time IS NULL THEN 1 ELSE 0 END')
            ->orderBy('hearing_time')
            ->value('id');

        return $id ? (int) $id : null;
    }

    protected function findExistingStaffEventId(
        ?int $clientId,
        Carbon $startsAt,
        bool $isAllDay,
        string $eventType
    ): ?int {
        if (! Schema::hasTable('staff_calendar_events')) {
            return null;
        }

        $query = StaffCalendarEvent::query()
            ->where('event_type', $eventType)
            ->where('is_all_day', $isAllDay);

        if ($clientId) {
            $query->where('client_id', $clientId);
        } else {
            $query->whereNull('client_id');
        }

        $query->whereDate('starts_at', $startsAt->toDateString());
        if (! $isAllDay) {
            $query->whereTime('starts_at', $startsAt->copy()->startOfMinute()->format('H:i:s'));
        }

        $id = $query->value('id');

        return $id ? (int) $id : null;
    }

    protected function cleanStaffEventTitle(string $title, string $eventType): string
    {
        $title = trim($title);
        $fallback = ucfirst(str_replace('_', ' ', $this->staffEventType($eventType)));

        if ($title === '' || CalendarEventText::looksLikeJunkEventTitle($title)) {
            return $fallback;
        }

        // Strip common reply prefixes for calendar readability.
        $title = preg_replace('/^(?:re|fw|fwd)\s*:\s*/i', '', $title) ?? $title;
        $title = trim($title);

        if ($title === '' || CalendarEventText::looksLikeJunkEventTitle($title) || ! $this->subjectLooksLikeEventTitle($title)) {
            return $fallback;
        }

        return mb_substr($title, 0, 255);
    }

    protected function subjectLooksLikeEventTitle(string $subject): bool
    {
        $normalized = strtolower($subject);

        if (preg_match('/\b(appointment|consultation|interview|meeting|mediation|conference|hearing|mention|walk[\s-]?in)\b/i', $normalized)) {
            return true;
        }

        // Reject court-file / ref-only email subjects.
        if (preg_match('/\b(our ref|your ref|court file|invoice|requires more documents)\b/i', $normalized)) {
            return false;
        }

        return mb_strlen($subject) <= 80;
    }

    protected function calendarTypeForEvent(string $eventType): string
    {
        return in_array($eventType, ['hearing', 'court', 'mention', 'tribunal'], true)
            ? EmailCalendarLink::TYPE_COURT_HEARING
            : EmailCalendarLink::TYPE_STAFF_EVENT;
    }

    protected function staffEventType(string $eventType): string
    {
        return match ($eventType) {
            'hearing', 'court', 'mention', 'tribunal' => 'court',
            'interview', 'walkin', 'walk_in', 'appointment', 'consultation' => 'meeting',
            default => 'meeting',
        };
    }

    protected function classifyEventType(string $text): ?string
    {
        $normalized = strtolower($text);

        if (preg_match('/\b(directions hearing|case management hearing|court hearing|mention|tribunal|court listing|listed (?:for|at))\b/i', $normalized)) {
            return 'hearing';
        }
        // Require hearing to look like a scheduled court event, not "hearing attached invoice".
        if (preg_match('/\bhearing\b/i', $normalized)
            && preg_match('/\b(court|directions|listed|before|judge|registry|federal|magistrates|family court|ncat|aat)\b/i', $normalized)
        ) {
            return 'hearing';
        }
        if (preg_match('/\bhearing\b/i', $normalized)
            && preg_match('/\b(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4}|\d{1,2}\s+(?:jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\s+\d{4}|(?:jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\s+\d{1,2},?\s+\d{4})\b/i', $normalized)
            && ! CalendarEventText::looksLikeBodySnippet($text)
        ) {
            return 'hearing';
        }
        if (preg_match('/\bwalk[\s-]?in\b/i', $normalized)) {
            return 'walkin';
        }
        if (preg_match('/\binterview\b/i', $normalized)) {
            return 'interview';
        }
        if (preg_match('/\b(appointment|consultation)\b/i', $normalized)) {
            return 'appointment';
        }
        if (preg_match('/\b(mediation|conference)\b/i', $normalized)) {
            return 'meeting';
        }

        return null;
    }

    protected function containsScheduleKeyword(string $text): bool
    {
        $normalized = strtolower($text);
        foreach ($this->scheduleKeywords as $keyword) {
            if (str_contains($normalized, strtolower($keyword))) {
                return true;
            }
        }

        return (bool) preg_match('/\bcourt\b/i', $normalized);
    }

    /**
     * @return list<array{starts_at: Carbon, ends_at: ?Carbon, event_type: string, location: ?string, is_all_day: bool}>
     */
    protected function extractDatesFromLine(string $line, string $timezone): array
    {
        $results = [];
        $eventType = $this->classifyEventType($line);
        if ($eventType === null) {
            return [];
        }

        $location = $this->extractLocationFromLine($line);

        $patterns = [
            '/\b(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})(?:\s+(?:at\s+)?(\d{1,2}(?::\d{2})?\s*(?:am|pm|a\.m\.|p\.m\.)))?\b/i',
            '/\b(\d{1,2}\s+(?:Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:tember)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)\s+\d{4})(?:\s+(?:at\s+)?(\d{1,2}(?::\d{2})?\s*(?:am|pm|a\.m\.|p\.m\.)))?\b/i',
            '/\b((?:Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:tember)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)\s+\d{1,2},?\s+\d{4})(?:\s+(?:at\s+)?(\d{1,2}(?::\d{2})?\s*(?:am|pm|a\.m\.|p\.m\.)))?\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (! preg_match_all($pattern, $line, $matches, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($matches as $match) {
                $startsAt = $this->parseFlexibleDateTime($match[1], $match[2] ?? null, $timezone);
                if (! $startsAt || ! $this->isReasonableEventDate($startsAt)) {
                    continue;
                }

                $hasExplicitTime = ! empty($match[2]);
                $results[] = [
                    'starts_at' => $startsAt,
                    'ends_at' => $startsAt->copy()->addHour(),
                    'event_type' => $eventType,
                    'location' => $location,
                    'is_all_day' => ! $hasExplicitTime,
                ];
            }
        }

        $unique = [];
        foreach ($results as $row) {
            $key = $row['starts_at']->format('Y-m-d H:i') . '|' . ($row['is_all_day'] ? '1' : '0');
            $unique[$key] = $row;
        }

        return array_values($unique);
    }

    protected function parseFlexibleDateTime(string $datePart, ?string $timePart, string $timezone): ?Carbon
    {
        $datePart = trim($datePart);
        $timePart = trim((string) $timePart);

        try {
            if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2,4})$/', $datePart, $parts)) {
                $year = (int) $parts[3];
                if ($year < 100) {
                    $year += 2000;
                }
                $dateString = sprintf('%04d-%02d-%02d', $year, (int) $parts[2], (int) $parts[1]);
            } else {
                $dateString = $datePart;
            }

            $combined = $timePart !== '' ? trim($dateString . ' ' . $timePart) : $dateString;
            $parsed = Carbon::parse($combined, $timezone);

            return $parsed->timezone($timezone);
        } catch (Throwable) {
            return null;
        }
    }

    protected function isReasonableEventDate(Carbon $date): bool
    {
        $now = now($date->timezone);
        $min = $now->copy()->subDays(1);
        $max = $now->copy()->addYears(3);

        return $date->betweenIncluded($min, $max);
    }

    protected function buildEventTitle(string $subject, string $contextLine, string $eventType): string
    {
        if (in_array($eventType, ['hearing', 'court', 'mention', 'tribunal'], true)) {
            return CalendarEventText::sanitizeHearingType(ucfirst(str_replace('_', ' ', $eventType)));
        }

        return $this->cleanStaffEventTitle($subject !== '' ? $subject : $contextLine, $eventType);
    }

    protected function extractLocationFromLine(string $line): ?string
    {
        return CalendarEventText::extractLocationCandidate($line);
    }

    protected function plainTextFromEmail(EmailLog $emailLog): string
    {
        $message = (string) ($emailLog->message ?? '');
        if ($message === '') {
            return (string) ($emailLog->text_preview ?? '');
        }

        $text = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>'], "\n", $message)));

        return trim(preg_replace('/\n{3,}/', "\n\n", $text) ?? $text);
    }

    /**
     * @return list<string>
     */
    protected function extractInlineIcsBlocks(string $text): array
    {
        if (! str_contains(strtoupper($text), 'BEGIN:VCALENDAR')) {
            return [];
        }

        preg_match_all('/BEGIN:VCALENDAR.*?END:VCALENDAR/si', $text, $matches);

        return array_values(array_filter($matches[0] ?? []));
    }

    protected function icsFieldValue(string $block, string $field): ?string
    {
        if (! preg_match('/^' . preg_quote($field, '/') . '(?:;[^:]*)?:(.*)$/mi', $block, $match)) {
            return null;
        }

        return trim(str_replace('\\n', "\n", str_replace('\\,', ',', $match[1])));
    }

    protected function parseIcsDateTime(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $timezone = config('app.timezone', 'Australia/Melbourne');
        $raw = trim($value);

        try {
            if (preg_match('/^(\d{8})T(\d{6})Z?$/', $raw, $match)) {
                return Carbon::createFromFormat('Ymd\THis', $match[1] . 'T' . $match[2], str_ends_with($raw, 'Z') ? 'UTC' : $timezone)
                    ->timezone($timezone);
            }

            if (preg_match('/^\d{8}$/', $raw)) {
                return Carbon::createFromFormat('Ymd', $raw, $timezone)->startOfDay();
            }

            return Carbon::parse($raw, $timezone)->timezone($timezone);
        } catch (Throwable) {
            return null;
        }
    }

    protected function icsValueIsAllDay(?string $value): bool
    {
        return is_string($value) && preg_match('/^\d{8}$/', trim($value)) === 1;
    }

    /**
     * @param  list<array{title: string, event_type: string, starts_at: Carbon, ends_at: ?Carbon, location: ?string, source: string, is_all_day: bool}>  $events
     * @return list<array{title: string, event_type: string, starts_at: Carbon, ends_at: ?Carbon, location: ?string, source: string, is_all_day: bool}>
     */
    protected function dedupeEvents(array $events): array
    {
        $unique = [];

        foreach ($events as $event) {
            $bucket = $this->calendarTypeForEvent($event['event_type']);
            $timeKey = ($event['is_all_day'] ?? false)
                ? $event['starts_at']->format('Y-m-d') . '|allday'
                : $event['starts_at']->format('Y-m-d H:i');
            $key = $bucket . '|' . $timeKey;
            // Prefer ICS / cleaner titles over text detection when colliding.
            if (! isset($unique[$key]) || ($event['source'] ?? '') !== 'text_detection') {
                $unique[$key] = $event;
            }
        }

        return array_values($unique);
    }

    /**
     * @param  array{title: string, event_type: string, starts_at: Carbon, ends_at: ?Carbon, location: ?string, source: string, is_all_day: bool}  $event
     */
    protected function linkExists(int $emailLogId, array $event): bool
    {
        $query = EmailCalendarLink::query()
            ->where('email_log_id', $emailLogId)
            ->whereDate('starts_at', $event['starts_at']->toDateString())
            ->where(function ($q) use ($event) {
                $q->where('event_type', $event['event_type'])
                    ->orWhere('calendar_type', $this->calendarTypeForEvent($event['event_type']));
            });

        if (! ($event['is_all_day'] ?? false)) {
            $query->whereTime('starts_at', $event['starts_at']->format('H:i:s'));
        }

        return $query->exists();
    }

    protected function readAttachmentContent(?string $s3Key, ?string $filePath): string
    {
        if (! empty($s3Key)) {
            try {
                $disk = Storage::disk('s3');
                if ($disk->exists($s3Key)) {
                    return (string) $disk->get($s3Key);
                }
            } catch (Throwable) {
                // Fall through to HTTP fetch.
            }
        }

        if (! empty($filePath) && filter_var($filePath, FILTER_VALIDATE_URL)) {
            try {
                $content = @file_get_contents($filePath);
                if ($content !== false) {
                    return (string) $content;
                }
            } catch (Throwable) {
                return '';
            }
        }

        return '';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function calendarSummaryForEmail(EmailLog $emailLog): array
    {
        if (! Schema::hasTable('email_calendar_links')) {
            return [
                'has_calendar' => false,
                'count' => 0,
                'merged_count' => 0,
                'pending_count' => 0,
                'events' => [],
            ];
        }

        $emailLog->loadMissing('calendarLinks');
        $links = $emailLog->calendarLinks;

        if ($links->isEmpty()) {
            return [
                'has_calendar' => false,
                'count' => 0,
                'merged_count' => 0,
                'pending_count' => 0,
                'events' => [],
            ];
        }

        $timezone = config('app.timezone', 'Australia/Melbourne');
        $events = $links->map(function (EmailCalendarLink $link) use ($timezone) {
            return [
                'id' => $link->id,
                'event_type' => $link->event_type,
                'event_title' => $link->event_title,
                'starts_at' => $link->starts_at?->timezone($timezone)->format('d/m/Y h:i a'),
                'location' => $link->location,
                'source' => $link->source,
                'status' => $link->status,
                'calendar_type' => $link->calendar_type,
                'calendar_id' => $link->calendar_id,
            ];
        })->values()->all();

        $mergedCount = $links->where('status', EmailCalendarLink::STATUS_MERGED)->count();

        return [
            'has_calendar' => true,
            'count' => $links->count(),
            'merged_count' => $mergedCount,
            'pending_count' => $links->count() - $mergedCount,
            'events' => $events,
        ];
    }
}
