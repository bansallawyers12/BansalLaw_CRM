<?php

namespace App\Services\EmailSync;

use App\Models\EmailLog;
use App\Services\Email\ClientEmailListService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * When staff manually upload .msg/.eml on a client matter, detect the same message
 * already present in the synced unassigned queue (avoid duplicate manual imports).
 */
class UnassignedEmailUploadMatchService
{
    public function __construct(
        private readonly ManualUploadThreadMatchService $threadMatchService,
    ) {
    }

    public function isUnassignedSynced(EmailLog $email): bool
    {
        return $this->threadMatchService->isUnassignedSynced($email);
    }

    /**
     * @param  array<string, mixed>  $parsedData
     */
    public function findMatch(array $parsedData, string $fileHash, string $mailType): ?EmailLog
    {
        $mailType = $mailType === 'sent' ? 'sent' : 'inbox';

        if ($fileHash !== '') {
            $byHash = $this->firstUnassignedFromQuery(
                $this->unassignedBaseQuery($mailType)->where('file_hash', $fileHash)
            );
            if ($byHash instanceof EmailLog) {
                return $byHash;
            }
        }

        $messageId = trim((string) ($parsedData['message_id'] ?? ''));
        if ($messageId !== '') {
            $normalized = trim($messageId, '<>');
            $byMessageId = $this->firstUnassignedFromQuery(
                $this->unassignedBaseQuery($mailType)->where(function ($q) use ($messageId, $normalized) {
                    $q->where('message_id', $messageId)
                        ->orWhere('message_id', $normalized)
                        ->orWhere('message_id', '<' . $normalized . '>');
                })
            );
            if ($byMessageId instanceof EmailLog) {
                return $byMessageId;
            }
        }

        $subject = trim((string) ($parsedData['subject'] ?? ''));
        $sender = strtolower(trim((string) ($parsedData['sender_email'] ?? $parsedData['from_mail'] ?? '')));
        if ($subject !== '') {
            $exact = $this->unassignedBaseQuery($mailType)
                ->whereRaw('LOWER(subject) = ?', [mb_strtolower($subject)]);
            if ($sender !== '') {
                $exact->whereRaw('LOWER(from_mail) LIKE ?', ['%' . $sender . '%']);
            }
            if (! empty($parsedData['sent_date'])) {
                try {
                    $sentAt = \Carbon\Carbon::parse((string) $parsedData['sent_date'], (string) config('app.timezone'));
                    $exact->whereBetween('fetch_mail_sent_time', [
                        $sentAt->copy()->subMinutes(5),
                        $sentAt->copy()->addMinutes(5),
                    ]);
                } catch (\Throwable) {
                    // Ignore unparseable dates; fall through to thread match.
                }
            }
            $existing = $this->firstUnassignedFromQuery($exact);
            if ($existing instanceof EmailLog) {
                return $existing;
            }

            $threadMatch = $this->findByThreadSubject($subject, $mailType);
            if ($threadMatch instanceof EmailLog) {
                return $threadMatch;
            }
        }

        return null;
    }

    protected function firstUnassignedFromQuery(Builder $query): ?EmailLog
    {
        $rows = (clone $query)->orderByDesc('id')->limit(15)->get();
        foreach ($rows as $row) {
            if ($row instanceof EmailLog && $this->isUnassignedSynced($row)) {
                return $row;
            }
        }

        return null;
    }

    protected function findByThreadSubject(string $subject, string $mailType): ?EmailLog
    {
        $normalized = ClientEmailListService::normalizeThreadSubject($subject);
        if ($normalized === '') {
            return null;
        }

        $needle = ClientEmailListService::threadSubjectSearchNeedle($normalized);
        if ($needle === '') {
            return null;
        }

        /** @var Collection<int, EmailLog> $candidates */
        $candidates = $this->unassignedBaseQuery($mailType)
            ->whereRaw('LOWER(subject) LIKE ?', ['%' . mb_strtolower(addcslashes($needle, '%_\\')) . '%'])
            ->orderByRaw('COALESCE(received_date, fetch_mail_sent_time, sent_at, created_at) desc')
            ->limit(40)
            ->get()
            ->filter(function (EmailLog $row) use ($subject) {
                if (! $this->isUnassignedSynced($row)) {
                    return false;
                }

                return ClientEmailListService::subjectsBelongToSameThread(
                    (string) ($row->subject ?? ''),
                    $subject
                );
            })
            ->values();

        return $candidates->first();
    }

    protected function unassignedBaseQuery(string $mailType): Builder
    {
        return EmailLog::query()
            ->where('mail_body_type', $mailType)
            ->where(function ($q) {
                $q->whereNull('client_id')->orWhere('client_id', '<=', 0);
            });
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * Reverse lookup: manual upload on a client matter → matching row still in unassigned queue.
     */
    public function findUnassignedMatchForClientEmail(EmailLog $email): ?EmailLog
    {
        if (! $this->threadMatchService->isManualClientUpload($email)) {
            return null;
        }

        $when = $email->fetch_mail_sent_time
            ?? $email->received_date
            ?? $email->sent_at
            ?? $email->created_at;
        $sentDate = $when ? $when->toIso8601String() : '';

        $parsed = [
            'subject' => (string) ($email->subject ?? ''),
            'sender_email' => (string) ($email->from_mail ?? ''),
            'from_mail' => (string) ($email->from_mail ?? ''),
            'message_id' => (string) ($email->message_id ?? ''),
            'sent_date' => $sentDate,
        ];
        $mailType = (string) ($email->mail_body_type ?: 'inbox');

        return $this->findMatch($parsed, (string) ($email->file_hash ?? ''), $mailType);
    }

    public function summarizeMatch(EmailLog $email): array
    {
        $when = $email->received_date
            ?? $email->fetch_mail_sent_time
            ?? $email->sent_at
            ?? $email->created_at;
        $tz = (string) config('app.timezone', 'Australia/Melbourne');

        return [
            'email_log_id' => (int) $email->id,
            'subject' => (string) ($email->subject ?? ''),
            'from_mail' => (string) ($email->from_mail ?? ''),
            'to_mail' => (string) ($email->to_mail ?? ''),
            'received_at_display' => $when
                ? $when->copy()->timezone($tz)->format('d/m/Y h:i a')
                : '',
        ];
    }
}
