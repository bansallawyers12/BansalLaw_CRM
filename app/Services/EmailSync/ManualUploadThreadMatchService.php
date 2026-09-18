<?php

namespace App\Services\EmailSync;

use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\EmailLog;
use App\Services\Email\ClientEmailListService;
use App\Services\EmailMatchingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Match unassigned Zoho-synced emails to manually uploaded matter emails
 * by conversation subject (and optional Message-ID / In-Reply-To).
 */
class ManualUploadThreadMatchService
{
    public function __construct(
        private readonly EmailMatchingService $matchingService,
    ) {
    }

    /**
     * @return array{
     *     client_id: int,
     *     client_ref: string,
     *     client_name: string,
     *     client_matter_id: int,
     *     matter_no: string,
     *     matter_title: string,
     *     matched_by: string,
     *     matched_manual_emails: list<array<string, mixed>>,
     *     ambiguous: bool
     * }|null
     */
    public function findUniqueMatterMatch(EmailLog $unassigned): ?array
    {
        $result = $this->findMatterMatches($unassigned);
        if ($result === null || ! empty($result['ambiguous'])) {
            return null;
        }

        return $result;
    }

    /**
     * @return array{
     *     client_id: int,
     *     client_ref: string,
     *     client_name: string,
     *     client_matter_id: int,
     *     matter_no: string,
     *     matter_title: string,
     *     matched_by: string,
     *     matched_manual_emails: list<array<string, mixed>>,
     *     ambiguous: bool,
     *     candidate_matters?: list<array<string, mixed>>
     * }|null
     */
    public function findMatterMatches(EmailLog $unassigned): ?array
    {
        $subject = trim((string) ($unassigned->subject ?? ''));
        if ($subject === '') {
            return null;
        }

        $manuals = $this->findManualUploadsForSubject($subject, $unassigned);
        if ($manuals->isEmpty()) {
            $byMessageId = $this->findManualUploadsByMessageLink($unassigned);
            if ($byMessageId->isNotEmpty()) {
                $manuals = $byMessageId;
            }
        }

        if ($manuals->isEmpty()) {
            return null;
        }

        $groups = [];
        foreach ($manuals as $manual) {
            $clientId = (int) ($manual->client_id ?? 0);
            $matterId = (int) ($manual->client_matter_id ?? 0);
            if ($clientId < 1 || $matterId < 1) {
                continue;
            }
            $key = $clientId . ':' . $matterId;
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'client_id' => $clientId,
                    'client_matter_id' => $matterId,
                    'emails' => [],
                ];
            }
            $groups[$key]['emails'][] = $this->mapManualEmail($manual);
        }

        if ($groups === []) {
            return null;
        }

        if (count($groups) === 1) {
            $group = array_values($groups)[0];

            return $this->buildMatchPayload(
                (int) $group['client_id'],
                (int) $group['client_matter_id'],
                $group['emails'],
                false
            );
        }

        $clientIds = array_unique(array_map(static fn ($g) => (int) $g['client_id'], $groups));
        if (count($clientIds) === 1) {
            $clientId = (int) array_values($clientIds)[0];
            $allEmails = [];
            $candidateMatters = [];
            foreach ($groups as $group) {
                $allEmails = array_merge($allEmails, $group['emails']);
                $candidateMatters[] = [
                    'client_matter_id' => (int) $group['client_matter_id'],
                    'matched_count' => count($group['emails']),
                ];
            }
            $payload = $this->buildMatchPayload($clientId, 0, $allEmails, true);
            if ($payload !== null) {
                $payload['candidate_matters'] = $candidateMatters;
            }

            return $payload;
        }

        // Multiple clients share the same thread subject — too ambiguous to auto-pick.
        return null;
    }

    /**
     * Attach match summaries onto unassigned synced email models for list/reading UI.
     *
     * @param  iterable<int, EmailLog>  $emails
     */
    public function attachMatchesToEmails(iterable $emails): void
    {
        foreach ($emails as $email) {
            if (! $email instanceof EmailLog) {
                continue;
            }
            if (! $this->isUnassignedSynced($email)) {
                $email->manual_upload_match = null;
                continue;
            }
            $email->manual_upload_match = $this->findMatterMatches($email);
        }
    }

    public function isUnassignedSynced(EmailLog $email): bool
    {
        $clientId = (int) ($email->client_id ?? 0);
        if ($clientId > 0) {
            return false;
        }

        $status = (string) ($email->sync_assignment_status ?? '');
        if (in_array($status, ['unassigned', 'unlinked'], true)) {
            return true;
        }

        return ! empty($email->synced_email_id) || ! empty($email->mailbox_email) || ! empty($email->imap_uid);
    }

    /**
     * @return Collection<int, EmailLog>
     */
    protected function findManualUploadsForSubject(string $subject, EmailLog $unassigned): Collection
    {
        $normalized = ClientEmailListService::normalizeThreadSubject($subject);
        if ($normalized === '') {
            return collect();
        }

        $needle = ClientEmailListService::threadSubjectSearchNeedle($normalized);
        if ($needle === '') {
            return collect();
        }

        $query = $this->manualUploadBaseQuery()
            ->where('id', '!=', (int) $unassigned->id)
            ->whereRaw('LOWER(subject) LIKE ?', ['%' . mb_strtolower(addcslashes($needle, '%_\\')) . '%'])
            ->orderByRaw('COALESCE(received_date, fetch_mail_sent_time, sent_at, created_at) desc')
            ->limit(80);

        return $query->get()->filter(function (EmailLog $manual) use ($subject) {
            return ClientEmailListService::subjectsBelongToSameThread(
                (string) ($manual->subject ?? ''),
                $subject
            );
        })->values();
    }

    /**
     * @return Collection<int, EmailLog>
     */
    protected function findManualUploadsByMessageLink(EmailLog $unassigned): Collection
    {
        $thread = is_array($unassigned->thread_info ?? null) ? $unassigned->thread_info : [];
        $candidates = array_values(array_filter([
            trim((string) ($thread['in_reply_to'] ?? '')),
            trim((string) ($unassigned->message_id ?? '')),
        ]));
        if ($candidates === []) {
            return collect();
        }

        $normalized = [];
        foreach ($candidates as $id) {
            $bare = trim($id, " <>");
            if ($bare !== '') {
                $normalized[] = $bare;
                $normalized[] = '<' . $bare . '>';
            }
        }
        $normalized = array_values(array_unique($normalized));
        if ($normalized === []) {
            return collect();
        }

        return $this->manualUploadBaseQuery()
            ->where('id', '!=', (int) $unassigned->id)
            ->whereIn('message_id', $normalized)
            ->orderByDesc('id')
            ->limit(40)
            ->get();
    }

    protected function manualUploadBaseQuery(): Builder
    {
        return EmailLog::query()
            ->select([
                'id', 'subject', 'from_mail', 'to_mail', 'client_id', 'client_matter_id',
                'received_date', 'fetch_mail_sent_time', 'sent_at', 'created_at',
                'message_id', 'conversion_type', 'synced_email_id', 'imap_uid', 'uploaded_doc_id',
            ])
            ->whereNotNull('client_id')
            ->where('client_id', '>', 0)
            ->whereNotNull('client_matter_id')
            ->where('client_matter_id', '>', 0)
            ->where(function ($q) {
                $q->whereNull('synced_email_id')->orWhere('synced_email_id', 0);
            })
            ->where(function ($q) {
                $q->whereNull('imap_uid')->orWhere('imap_uid', 0);
            })
            ->where(function ($q) {
                $q->where('conversion_type', 'conversion_email_fetch')
                    ->orWhereNotNull('uploaded_doc_id');
            });
    }

    /**
     * @param  list<array<string, mixed>>  $matchedEmails
     * @return array{
     *     client_id: int,
     *     client_ref: string,
     *     client_name: string,
     *     client_matter_id: int,
     *     matter_no: string,
     *     matter_title: string,
     *     matched_by: string,
     *     matched_manual_emails: list<array<string, mixed>>,
     *     ambiguous: bool
     * }|null
     */
    protected function buildMatchPayload(int $clientId, int $matterId, array $matchedEmails, bool $ambiguous): ?array
    {
        $client = Admin::query()
            ->select('id', 'client_id', 'first_name', 'last_name', 'email', 'type')
            ->find($clientId);
        if (! $client) {
            return null;
        }

        $summary = $this->matchingService->clientSummary($client);
        $matterNo = '';
        $matterTitle = '';
        if ($matterId > 0) {
            $matter = ClientMatter::query()
                ->leftJoin('matters', 'matters.id', '=', 'client_matters.sel_matter_id')
                ->where('client_matters.id', $matterId)
                ->select(
                    'client_matters.id',
                    'client_matters.client_unique_matter_no',
                    'matters.title as matter_title'
                )
                ->first();
            $matterNo = (string) ($matter->client_unique_matter_no ?? '');
            $matterTitle = (string) ($matter->matter_title ?? '');
        }

        return [
            'client_id' => $clientId,
            'client_ref' => (string) ($summary['client_ref'] ?? ''),
            'client_name' => (string) ($summary['client_name'] ?? ''),
            'client_matter_id' => $matterId,
            'matter_no' => $matterNo,
            'matter_title' => $matterTitle,
            'matched_by' => 'manual_upload_thread',
            'matched_manual_emails' => array_values($matchedEmails),
            'ambiguous' => $ambiguous,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapManualEmail(EmailLog $email): array
    {
        $when = $email->received_date
            ?? $email->fetch_mail_sent_time
            ?? $email->sent_at
            ?? $email->created_at;
        $tz = (string) config('app.timezone', 'Australia/Melbourne');

        return [
            'id' => (int) $email->id,
            'subject' => (string) ($email->subject ?? ''),
            'from_mail' => (string) ($email->from_mail ?? ''),
            'to_mail' => (string) ($email->to_mail ?? ''),
            'client_id' => (int) ($email->client_id ?? 0),
            'client_matter_id' => (int) ($email->client_matter_id ?? 0),
            'received_at' => $when?->toIso8601String(),
            'received_at_display' => $when
                ? $when->copy()->timezone($tz)->format('d/m/Y h:i a')
                : '',
        ];
    }
}
