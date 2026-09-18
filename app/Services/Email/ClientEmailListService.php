<?php

namespace App\Services\Email;

use App\Models\Document;
use App\Models\EmailLog;
use App\Models\EmailLogAttachment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Matter/lead email list queries with lean column selection (no full body / analysis blobs).
 */
class ClientEmailListService
{
    /**
     * Columns safe for list/JSON payloads. Full `message` is loaded on demand via body endpoint.
     *
     * @return list<string>
     */
    public static function listColumns(string $table = 'email_logs', bool $includeMessage = false): array
    {
        static $columnCache = [];

        $cacheKey = $table . ':' . ($includeMessage ? '1' : '0');
        if (isset($columnCache[$cacheKey])) {
            return $columnCache[$cacheKey];
        }

        $exclude = ['python_analysis', 'security_issues', 'thread_info'];
        if (! $includeMessage) {
            $exclude[] = 'message';
        }

        try {
            $all = Schema::getColumnListing($table);
        } catch (\Throwable) {
            $all = [];
        }

        if ($all === []) {
            // Fallback if schema introspection fails mid-request
            $cols = [
                'id', 'user_id', 'from_mail', 'mailbox_email', 'synced_email_id', 'sync_assignment_status',
                'imap_uid', 'sync_source', 'to_mail', 'cc', 'bcc', 'template_id', 'subject', 'type',
                'mail_type', 'mail_body_type', 'send_status', 'send_error', 'sent_at', 'failed_at',
                'retry_count', 'client_id', 'client_matter_id', 'conversion_type', 'fetch_mail_sent_time',
                'uploaded_doc_id', 'pdf_doc_id', 'mail_is_read', 'text_preview', 'processed_at',
                'message_id', 'received_date', 'file_hash', 'created_at', 'updated_at',
            ];
            if ($includeMessage) {
                $cols[] = 'message';
            }

            return $columnCache[$cacheKey] = array_map(static fn (string $c) => "{$table}.{$c}", $cols);
        }

        $cols = array_values(array_filter(
            $all,
            static fn (string $c) => ! in_array($c, $exclude, true)
        ));

        return $columnCache[$cacheKey] = array_map(static fn (string $c) => "{$table}.{$c}", $cols);
    }

    public function applyListSelect(Builder $query): Builder
    {
        return $query->select(self::listColumns());
    }

    /**
     * @param  array{
     *     client_matter_id?: mixed,
     *     status?: mixed,
     *     search?: mixed,
     *     label_id?: mixed,
     *     sender_filter?: mixed
     * }  $filters
     */
    public function paginateInbox(array $filters, int $perPage = 5): LengthAwarePaginator
    {
        $matterId = $filters['client_matter_id'] ?? null;
        if (! $matterId) {
            throw new \InvalidArgumentException('Matter ID is required');
        }

        if (! Schema::hasColumn('email_logs', 'client_matter_id')) {
            return EmailLog::query()->whereRaw('0 = 1')->paginate($perPage);
        }

        $query = EmailLog::query()
            ->where('client_matter_id', $matterId)
            ->where('type', 'client')
            ->where('mail_type', 1)
            ->where('conversion_type', 'conversion_email_fetch')
            ->where('mail_body_type', 'inbox')
            ->with(['labels', 'attachments', 'pdfDocument'])
            ->orderByDesc('created_at');

        $this->applyListSelect($query);
        $this->applyCommonFilters($query, $filters);
        EmailLog::applyExcludeCalendarInvitesFromMailLists($query);

        return $query->paginate($perPage);
    }

    /**
     * @param  array{
     *     client_matter_id?: mixed,
     *     type?: mixed,
     *     status?: mixed,
     *     search?: mixed,
     *     sender_filter?: mixed
     * }  $filters
     */
    public function paginateSent(array $filters, int $perPage = 5): LengthAwarePaginator
    {
        $matterId = $filters['client_matter_id'] ?? null;
        if (! $matterId) {
            throw new \InvalidArgumentException('Matter ID is required');
        }

        $hasSendStatus = Schema::hasColumn('email_logs', 'send_status');
        $query = EmailLog::query()
            ->where('client_matter_id', $matterId)
            ->where('type', 'client')
            ->where(function ($q) use ($hasSendStatus) {
                $q->where(function ($crm) use ($hasSendStatus) {
                    $crm->where('mail_type', 2);
                    if ($hasSendStatus) {
                        $crm->where(function ($status) {
                            $status->where('send_status', EmailLog::SEND_STATUS_SENT)
                                ->orWhereNull('send_status')
                                ->orWhere('send_status', EmailLog::SEND_STATUS_PENDING);
                        });
                    }
                })->orWhere(function ($uploaded) {
                    $uploaded->where('mail_type', 1)
                        ->where(function ($inner) {
                            $inner->whereNull('conversion_type')
                                ->orWhere(function ($sub) {
                                    $sub->where('conversion_type', 'conversion_email_fetch')
                                        ->where('mail_body_type', 'sent');
                                });
                        });
                });
            })
            ->with(['labels', 'attachments', 'pdfDocument'])
            ->orderByRaw('COALESCE(sent_at, fetch_mail_sent_time, created_at) DESC');

        $this->applyListSelect($query);

        $type = $filters['type'] ?? '';
        if ($type !== '' && $type !== null) {
            if ((int) $type === 1) {
                $query->whereNotNull('conversion_type');
            } elseif ((int) $type === 2) {
                $query->whereNull('conversion_type');
            }
        }

        $this->applyCommonFilters($query, $filters);

        EmailLog::applyExcludeCalendarInvitesFromMailLists($query);

        return $query->paginate($perPage);
    }

    /**
     * @param  array{
     *     client_id?: mixed,
     *     status?: mixed,
     *     search?: mixed,
     *     label_id?: mixed,
     *     sender_filter?: mixed
     * }  $filters
     */
    public function paginateLeadEmails(array $filters, int $perPage = 5): LengthAwarePaginator
    {
        $clientId = $filters['client_id'] ?? null;
        if (! $clientId) {
            throw new \InvalidArgumentException('Lead ID is required');
        }

        $hasSendStatus = Schema::hasColumn('email_logs', 'send_status');
        $query = EmailLog::query()
            ->where('client_id', $clientId)
            ->where('type', 'lead')
            ->where(function ($q) use ($hasSendStatus) {
                $q->where(function ($crm) use ($hasSendStatus) {
                    $crm->where('mail_type', 2);
                    if ($hasSendStatus) {
                        $crm->where(function ($status) {
                            $status->where('send_status', EmailLog::SEND_STATUS_SENT)
                                ->orWhereNull('send_status')
                                ->orWhere('send_status', EmailLog::SEND_STATUS_PENDING);
                        });
                    }
                })->orWhere(function ($uploaded) {
                    $uploaded->where('mail_type', 1)
                        ->where(function ($inner) {
                            $inner->whereNull('conversion_type')
                                ->orWhere(function ($sub) {
                                    $sub->where('conversion_type', 'conversion_email_fetch')
                                        ->where('mail_body_type', 'sent');
                                });
                        });
                });
            })
            ->with(['labels', 'attachments', 'pdfDocument'])
            ->orderByRaw('COALESCE(sent_at, fetch_mail_sent_time, created_at) DESC');

        $this->applyListSelect($query);
        $this->applyCommonFilters($query, $filters);
        EmailLog::applyExcludeCalendarInvitesFromMailLists($query);

        return $query->paginate($perPage);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function mapPaginatorItems(LengthAwarePaginator $paginator): array
    {
        return collect($paginator->items())
            ->map(fn (EmailLog $email) => $this->mapEmailForList($email))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function mapEmailForList(EmailLog $email): array
    {
        $email->loadMissing(['attachments', 'labels', 'pdfDocument']);

        $emailArray = $email->toArray();
        unset(
            $emailArray['message'],
            $emailArray['python_analysis'],
            $emailArray['security_issues'],
            $emailArray['thread_info'],
        );

        $attachments = $email->attachments;
        if (! $attachments || $attachments->count() === 0) {
            $attachments = EmailLogAttachment::where('email_log_id', $email->id)->get();
        }

        $emailArray['attachments'] = $attachments->count() > 0
            ? $attachments->map(fn ($a) => $this->formatAttachment($a))->values()->all()
            : [];

        $emailArray['preview_url'] = $this->resolveMsgDownloadUrl($email);
        $emailArray['pdf_preview_url'] = $this->resolvePdfPreviewUrl($email);
        $emailArray['body_deferred'] = true;
        $emailArray['from_mail'] = $emailArray['from_mail'] ?? '';
        $emailArray['to_mail'] = EmailLog::resolveRecipientDisplay($emailArray['to_mail'] ?? '', $email->type ?? null);
        $emailArray['cc'] = EmailLog::resolveRecipientDisplay($emailArray['cc'] ?? '', $email->type ?? null);
        $emailArray['bcc'] = EmailLog::resolveRecipientDisplay($emailArray['bcc'] ?? '', $email->type ?? null);
        $emailArray['subject'] = $emailArray['subject'] ?? '';
        $emailArray['message'] = '';
        $emailArray['text_preview'] = $emailArray['text_preview'] ?? '';
        $emailArray['is_hearing'] = EmailLog::isHearingMailListItem($email);

        return $emailArray;
    }

    /**
     * @return array{success: bool, message: string, text_preview: string}
     */
    public function bodyPayload(EmailLog $email): array
    {
        $message = (string) ($email->message ?? '');
        $preview = (string) ($email->text_preview ?? '');

        if ($message === '' && $preview !== '') {
            $message = $preview;
        }

        return [
            'success' => true,
            'message' => $message,
            'text_preview' => $preview !== ''
                ? $preview
                : EmailLog::plainTextPreview($message, 100),
        ];
    }

    /**
     * Exclude analysis/blob columns from an existing query (e.g. Outlook list).
     * Keeps `message` optional via $includeMessage for reading-pane compatibility.
     */
    public function applyLeanSelect(Builder $query, bool $includeMessage = false): Builder
    {
        return $query->select(self::listColumns('email_logs', $includeMessage));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyCommonFilters(Builder $query, array $filters): void
    {
        $status = $filters['status'] ?? null;
        if ($status !== null && $status !== '') {
            if ((int) $status === 1) {
                $query->where('mail_is_read', 1);
            } elseif ((int) $status === 2) {
                $query->where(function ($q) {
                    $q->where('mail_is_read', 0)->orWhereNull('mail_is_read');
                });
            }
        }

        $search = $filters['search'] ?? null;
        if ($search !== null && $search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'LIKE', "%{$search}%")
                    ->orWhere('text_preview', 'LIKE', "%{$search}%")
                    ->orWhere('from_mail', 'LIKE', "%{$search}%")
                    ->orWhere('to_mail', 'LIKE', "%{$search}%")
                    ->orWhere('cc', 'LIKE', "%{$search}%");
            });
        }

        $labelId = $filters['label_id'] ?? null;
        if (! empty($labelId)) {
            $query->whereHas('labels', function ($q) use ($labelId) {
                $q->where('email_labels.id', $labelId);
            });
        }

        $sender = $filters['sender_filter'] ?? null;
        if (! empty($sender)) {
            $query->where('from_mail', $sender);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function formatAttachment(EmailLogAttachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'mail_report_id' => $attachment->email_log_id,
            'filename' => $attachment->filename,
            'display_name' => $attachment->display_name ?? $attachment->filename,
            'content_type' => $attachment->content_type,
            'file_size' => (int) $attachment->file_size,
            'content_id' => $attachment->content_id,
            'is_inline' => (bool) ($attachment->is_inline ?? false),
            'description' => $attachment->description,
            'extension' => $attachment->extension,
            'preview_url' => url('/mail-attachments/' . $attachment->id . '/preview'),
            'download_url' => url('/mail-attachments/' . $attachment->id . '/download'),
        ];
    }

    public function resolveMsgDownloadUrl(EmailLog $email): string
    {
        $docId = (int) ($email->uploaded_doc_id ?? 0);
        if ($docId <= 0) {
            return '';
        }

        return url('/documents/preview/' . $docId) . '?download=1';
    }

    public function resolvePdfPreviewUrl(EmailLog $email): string
    {
        if (empty($email->pdf_doc_id)) {
            return '';
        }

        $pdfDoc = $email->relationLoaded('pdfDocument')
            ? $email->pdfDocument
            : Document::find((int) $email->pdf_doc_id);

        if (! $pdfDoc) {
            return '';
        }

        $myfile = (string) ($pdfDoc->myfile ?? '');
        if ($myfile === '' || ! str_starts_with($myfile, 'http')) {
            return '';
        }

        $path = ltrim(urldecode((string) parse_url($myfile, PHP_URL_PATH)), '/');
        if ($path === '') {
            return '';
        }

        $bucket = (string) config('filesystems.disks.s3.bucket', '');
        if ($bucket !== '' && str_starts_with($path, $bucket . '/')) {
            $path = substr($path, strlen($bucket) + 1);
        }

        try {
            if (! Storage::disk('s3')->exists($path)) {
                return '';
            }
        } catch (\Throwable) {
            return '';
        }

        return url('/documents/preview/' . (int) $email->pdf_doc_id) . '?embed=1';
    }

    /**
     * Strip reply/forward prefixes so chain messages share one subject key.
     */
    public static function normalizeThreadSubject(string $subject): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', $subject) ?? $subject);
        $previous = null;
        while ($s !== '' && $s !== $previous) {
            $previous = $s;
            $stripped = preg_replace('/^(re|fw|fwd)\s*:\s*/iu', '', $s);
            $s = trim((string) ($stripped ?? $s));
        }

        return mb_strtolower($s);
    }

    /**
     * Distinctive LIKE needle for SQL prefilter (prefer last | segment).
     */
    public static function threadSubjectSearchNeedle(string $normalizedSubject): string
    {
        $normalizedSubject = trim($normalizedSubject);
        if ($normalizedSubject === '') {
            return '';
        }

        $parts = array_values(array_filter(array_map('trim', explode('|', $normalizedSubject)), static fn ($p) => $p !== ''));
        $needle = $parts !== [] ? (string) end($parts) : $normalizedSubject;
        if (mb_strlen($needle) < 12) {
            $needle = $normalizedSubject;
        }

        return mb_substr($needle, 0, 120);
    }

    /**
     * Whether two normalized subjects belong to the same conversation chain.
     */
    public static function subjectsBelongToSameThread(string $a, string $b): bool
    {
        $na = self::normalizeThreadSubject($a);
        $nb = self::normalizeThreadSubject($b);
        if ($na === '' || $nb === '') {
            return $na === $nb;
        }
        if ($na === $nb) {
            return true;
        }

        // Allow longer subject variants that still carry the same core thread text.
        if (mb_strlen($na) >= 12 && mb_strlen($nb) >= 12 && (str_contains($na, $nb) || str_contains($nb, $na))) {
            return true;
        }

        return false;
    }

    /**
     * Chronological conversation chain for one email (inbox + sent / firm replies).
     *
     * @param  'all'|'incoming'|'outgoing'  $direction
     * @return array{
     *     thread_subject: string,
     *     seed_email_id: int,
     *     direction: string,
     *     total: int,
     *     incoming_count: int,
     *     outgoing_count: int,
     *     emails: list<array<string, mixed>>
     * }
     */
    public function listChainForEmail(EmailLog $seed, string $direction = 'all'): array
    {
        $direction = in_array($direction, ['all', 'incoming', 'outgoing'], true) ? $direction : 'all';
        $threadSubject = self::normalizeThreadSubject((string) ($seed->subject ?? ''));
        $appTimezone = (string) config('app.timezone', 'Australia/Melbourne');
        $firmDomains = $this->firmEmailDomains();

        $mapItem = function (EmailLog $email) use ($seed, $appTimezone, $firmDomains): array {
            $sortAt = $email->received_date
                ?? $email->fetch_mail_sent_time
                ?? $email->sent_at
                ?? $email->created_at;
            $dir = $this->resolveChainDirection($email, $firmDomains);

            return [
                'id' => (int) $email->id,
                'subject' => (string) ($email->subject ?? ''),
                'from_mail' => (string) ($email->from_mail ?? ''),
                'to_mail' => EmailLog::resolveRecipientDisplay($email->to_mail ?? '', $email->type ?? null),
                'cc' => EmailLog::resolveRecipientDisplay($email->cc ?? '', $email->type ?? null),
                'mail_body_type' => (string) ($email->mail_body_type ?? ''),
                'mail_type' => $email->mail_type,
                'direction' => $dir,
                'is_current' => (int) $email->id === (int) $seed->id,
                'text_preview' => mb_substr((string) ($email->text_preview ?? ''), 0, 160),
                'received_at' => $sortAt?->toIso8601String(),
                'received_at_display' => $sortAt
                    ? $sortAt->copy()->timezone($appTimezone)->format('d/m/Y h:i a')
                    : '',
            ];
        };

        if ($threadSubject === '') {
            $only = $mapItem($seed);

            return [
                'thread_subject' => '',
                'seed_email_id' => (int) $seed->id,
                'direction' => $direction,
                'total' => 1,
                'incoming_count' => $only['direction'] === 'incoming' ? 1 : 0,
                'outgoing_count' => $only['direction'] === 'outgoing' ? 1 : 0,
                'emails' => [$only],
            ];
        }

        $needle = self::threadSubjectSearchNeedle($threadSubject);
        $query = EmailLog::query()
            ->select([
                'id', 'subject', 'from_mail', 'to_mail', 'cc', 'mail_body_type', 'mail_type',
                'type', 'text_preview', 'received_date', 'fetch_mail_sent_time', 'sent_at', 'created_at',
                'client_id', 'client_matter_id', 'mailbox_email',
            ]);

        if (! empty($seed->client_matter_id)) {
            $query->where('client_matter_id', $seed->client_matter_id);
        } elseif (! empty($seed->client_id)) {
            $query->where('client_id', $seed->client_id);
        } else {
            $query->where('id', $seed->id);
        }

        if ($needle !== '') {
            $query->whereRaw('LOWER(subject) LIKE ?', ['%' . mb_strtolower(addcslashes($needle, '%_\\')) . '%']);
        }

        EmailLog::applyExcludeCalendarInvitesFromMailLists($query);

        $candidates = $query
            ->orderByRaw('COALESCE(received_date, fetch_mail_sent_time, sent_at, created_at) asc')
            ->orderBy('id')
            ->limit(250)
            ->get();

        $seedIncluded = false;
        $items = [];
        foreach ($candidates as $email) {
            if (! self::subjectsBelongToSameThread((string) $email->subject, (string) $seed->subject)) {
                continue;
            }
            $item = $mapItem($email);
            if ($item['id'] === (int) $seed->id) {
                $seedIncluded = true;
            }
            $items[] = $item;
        }

        if (! $seedIncluded) {
            $items[] = $mapItem($seed);
            usort($items, static function (array $a, array $b): int {
                return strcmp((string) ($a['received_at'] ?? ''), (string) ($b['received_at'] ?? ''))
                    ?: ($a['id'] <=> $b['id']);
            });
        }

        $incomingCount = count(array_filter($items, static fn ($i) => ($i['direction'] ?? '') === 'incoming'));
        $outgoingCount = count(array_filter($items, static fn ($i) => ($i['direction'] ?? '') === 'outgoing'));

        if ($direction !== 'all') {
            $items = array_values(array_filter(
                $items,
                static fn (array $i) => ($i['direction'] ?? '') === $direction
            ));
        }

        return [
            'thread_subject' => $threadSubject,
            'seed_email_id' => (int) $seed->id,
            'direction' => $direction,
            'total' => count($items),
            'incoming_count' => $incomingCount,
            'outgoing_count' => $outgoingCount,
            'emails' => array_values($items),
        ];
    }

    /**
     * @return list<string>
     */
    private function firmEmailDomains(): array
    {
        $domains = ['bansallawyers.com.au'];
        try {
            $mailboxes = \App\Models\Email::query()
                ->where('status', true)
                ->pluck('email')
                ->filter()
                ->all();
            foreach ($mailboxes as $addr) {
                $host = strtolower((string) (parse_url('mailto:' . $addr, PHP_URL_HOST) ?: ''));
                if ($host === '' && str_contains((string) $addr, '@')) {
                    $host = strtolower((string) substr((string) $addr, strrpos((string) $addr, '@') + 1));
                }
                if ($host !== '' && ! in_array($host, $domains, true)) {
                    $domains[] = $host;
                }
            }
        } catch (\Throwable) {
            // Schema / DB unavailable — keep default firm domain.
        }

        return $domains;
    }

    /**
     * @param  list<string>  $firmDomains
     */
    private function resolveChainDirection(EmailLog $email, array $firmDomains): string
    {
        $folder = strtolower((string) ($email->mail_body_type ?? ''));
        if ($folder === 'sent' || (int) ($email->mail_type ?? 0) === 2) {
            return 'outgoing';
        }

        $from = strtolower(trim((string) ($email->from_mail ?? '')));
        foreach ($firmDomains as $domain) {
            if ($domain !== '' && str_contains($from, '@' . strtolower($domain))) {
                return 'outgoing';
            }
        }

        return 'incoming';
    }
}
