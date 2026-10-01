<?php

namespace App\Services\Email;

use App\Models\EmailLog;
use App\Services\EmailSync\ManualUploadThreadMatchService;
use App\Services\EmailSync\UnassignedEmailUploadMatchService;
/**
 * Block manual .msg/.eml uploads when the same message already exists
 * in Unassigned Mail or on the client matter (Inbox or Sent).
 */
class ClientEmailUploadDuplicateService
{
    public function __construct(
        private readonly UnassignedEmailUploadMatchService $unassignedMatchService,
        private readonly ManualUploadThreadMatchService $threadMatchService,
    ) {}

    /**
     * @param  array<string, mixed>  $parsedData
     * @return array<string, mixed>|null Upload error payload for {@see EmailUploadController::processEmailFile}
     */
    public function buildManualUploadBlockResponse(
        int $clientId,
        ?int $clientMatterId,
        array $parsedData,
        string $fileHash
    ): ?array {
        $unassigned = $this->unassignedMatchService->findMatchAcrossMailFolders($parsedData, $fileHash);
        if ($unassigned instanceof EmailLog) {
            $summary = $this->unassignedMatchService->summarizeMatch($unassigned);
            $summary['location'] = $this->locationKey($unassigned);
            $summary['location_label'] = $this->locationLabel($unassigned);

            return [
                'success' => false,
                'error_code' => 'unassigned_match',
                'duplicate' => true,
                'block_force_upload' => true,
                'error' => 'This email is already in '.$summary['location_label'].'. Open it there instead of uploading again.',
                'unassigned_match' => $summary,
                'existing_match' => $summary,
            ];
        }

        if ($clientId <= 0) {
            return null;
        }

        $existing = $this->findAssignedClientDuplicate($clientId, $clientMatterId, $parsedData, $fileHash);
        if (! $existing instanceof EmailLog) {
            return null;
        }

        $match = $this->summarizeExisting($existing);

        return [
            'success' => false,
            'error_code' => 'existing_email',
            'duplicate' => true,
            'block_force_upload' => true,
            'error' => $this->buildUserMessage($existing, $match),
            'existing' => $match,
            'existing_match' => $match,
        ];
    }

    /**
     * @param  array<string, mixed>  $parsedData
     */
    protected function findAssignedClientDuplicate(
        int $clientId,
        ?int $clientMatterId,
        array $parsedData,
        string $fileHash
    ): ?EmailLog {
        $base = EmailLog::query()
            ->where('client_id', $clientId)
            ->where('client_id', '>', 0);

        if ($clientMatterId) {
            $base->where('client_matter_id', $clientMatterId);
        }

        if ($fileHash !== '') {
            $byHash = (clone $base)->where('file_hash', $fileHash)->first();
            if ($byHash) {
                return $byHash;
            }
        }

        $messageId = trim((string) ($parsedData['message_id'] ?? ''));
        if ($messageId !== '') {
            $normalized = trim($messageId, '<>');
            $byMessageId = (clone $base)->where(function ($q) use ($messageId, $normalized) {
                $q->where('message_id', $messageId)
                    ->orWhere('message_id', $normalized)
                    ->orWhere('message_id', '<'.$normalized.'>');
            })->first();
            if ($byMessageId) {
                return $byMessageId;
            }
        }

        $subject = trim((string) ($parsedData['subject'] ?? ''));
        $sender = strtolower(trim((string) ($parsedData['sender_email'] ?? $parsedData['from_mail'] ?? '')));
        if ($subject !== '' && $sender !== '') {
            $exact = (clone $base)
                ->whereRaw('LOWER(subject) = ?', [mb_strtolower($subject)])
                ->whereRaw('LOWER(from_mail) LIKE ?', ['%'.$sender.'%']);

            if (! empty($parsedData['sent_date'])) {
                try {
                    $sentAt = \Carbon\Carbon::parse((string) $parsedData['sent_date'], (string) config('app.timezone'));
                    $exact->whereBetween('fetch_mail_sent_time', [
                        $sentAt->copy()->subMinutes(10),
                        $sentAt->copy()->addMinutes(10),
                    ]);
                } catch (\Throwable) {
                    // Continue to thread match.
                }
            }

            $row = $exact->orderByDesc('id')->first();
            if ($row) {
                return $row;
            }
        }

        if ($subject === '') {
            return null;
        }

        $normalized = ClientEmailListService::normalizeThreadSubject($subject);
        $needle = ClientEmailListService::threadSubjectSearchNeedle($normalized);
        if ($needle === '') {
            return null;
        }

        $candidates = (clone $base)
            ->whereRaw('LOWER(subject) LIKE ?', ['%'.mb_strtolower(addcslashes($needle, '%_\\')).'%'])
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        foreach ($candidates as $candidate) {
            if (! $candidate instanceof EmailLog) {
                continue;
            }
            if (! ClientEmailListService::subjectsBelongToSameThread((string) $candidate->subject, $subject)) {
                continue;
            }
            if ($sender !== '' && ! str_contains(mb_strtolower((string) $candidate->from_mail), $sender)) {
                continue;
            }

            return $candidate;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function summarizeExisting(EmailLog $email): array
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
            'mail_body_type' => (string) ($email->mail_body_type ?? 'inbox'),
            'location' => $this->locationKey($email),
            'location_label' => $this->locationLabel($email),
            'assignment_label' => $this->assignmentLabel($email),
            'received_at_display' => $when
                ? $when->copy()->timezone($tz)->format('d/m/Y h:i a')
                : '',
        ];
    }

    public function locationKey(EmailLog $email): string
    {
        if ($this->unassignedMatchService->isUnassignedSynced($email)) {
            return ($email->mail_body_type === 'sent' ? 'unassigned_sent' : 'unassigned_inbox');
        }

        return ($email->mail_body_type === 'sent' ? 'client_sent' : 'client_inbox');
    }

    public function locationLabel(EmailLog $email): string
    {
        if ($this->unassignedMatchService->isUnassignedSynced($email)) {
            return $email->mail_body_type === 'sent'
                ? 'Unassigned Mail → Sent'
                : 'Unassigned Mail → Incoming';
        }

        return $email->mail_body_type === 'sent'
            ? 'Client matter → Sent'
            : 'Client matter → Incoming';
    }

    public function assignmentLabel(EmailLog $email): string
    {
        $status = (string) ($email->sync_assignment_status ?? '');
        if ($status === 'auto_assigned' || ($email->synced_email_id && $email->sync_source === EmailLog::SYNC_SOURCE_CRON)) {
            return 'Auto-assigned (inbox sync)';
        }
        if ($this->threadMatchService->isManualClientUpload($email)) {
            return 'Manual upload';
        }
        if ($email->sync_source === EmailLog::SYNC_SOURCE_COMPOSE) {
            return 'Sent from CRM';
        }

        return 'On file';
    }

    /**
     * @param  array<string, mixed>  $match
     */
    protected function buildUserMessage(EmailLog $existing, array $match): string
    {
        $subject = $existing->subject ?: '(No subject)';
        $from = $existing->from_mail ?: 'Unknown sender';
        $when = $match['received_at_display'] ?? '';

        $message = 'This email is already on the file at: '.$match['location_label']
            .' ('.$match['assignment_label'].').';
        $message .= ' Subject: "'.$subject.'" from '.$from;
        if ($when !== '') {
            $message .= ' ('.$when.')';
        }

        return $message;
    }
}
