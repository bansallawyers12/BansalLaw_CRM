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

            return array_map(static fn (string $c) => "{$table}.{$c}", $cols);
        }

        $cols = array_values(array_filter(
            $all,
            static fn (string $c) => ! in_array($c, $exclude, true)
        ));

        return array_map(static fn (string $c) => "{$table}.{$c}", $cols);
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
}
