<?php

namespace App\Services\Email;

use App\Models\EmailLog;
use App\Services\EmailSync\IncomingEmailSyncService;
use App\Services\EmailSync\ManualUploadThreadMatchService;
use App\Services\EmailSync\UnassignedEmailUploadMatchService;
use Carbon\Carbon;

/**
 * Block manual .msg/.eml uploads when the same message already exists on the client
 * (Inbox or Sent). Unassigned Mail is only checked when there is no target client.
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
        string $fileHash,
        string $mailType = 'inbox'
    ): ?array {
        $mailType = $mailType === 'sent' ? 'sent' : 'inbox';

        if ($clientId <= 0) {
            $unassignedBlock = $this->buildUnassignedMailDuplicateBlock($parsedData, $fileHash);
            if ($unassignedBlock !== null) {
                return $unassignedBlock;
            }

            return null;
        }

        $existing = $this->findAssignedClientDuplicate($clientId, $clientMatterId, $parsedData, $fileHash, $mailType);
        if (! $existing instanceof EmailLog) {
            return null;
        }

        if ($this->shouldAllowDuplicateManualUploadInSyncQueue($existing)) {
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
     * @return array<string, mixed>|null
     */
    protected function buildUnassignedMailDuplicateBlock(array $parsedData, string $fileHash): ?array
    {
        $unassigned = $this->unassignedMatchService->findMatchAcrossMailFolders($parsedData, $fileHash);
        if (! $unassigned instanceof EmailLog) {
            return null;
        }

        if ($this->shouldAllowDuplicateManualUploadInSyncQueue($unassigned)) {
            return null;
        }

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

    /**
     * Local only: allow manual .msg/.eml upload when the same message already sits in the
     * synced Unassigned or Assigned queue so the existing row can show "Manual upload match".
     */
    protected function shouldAllowDuplicateManualUploadInSyncQueue(EmailLog $existing): bool
    {
        if (! app()->environment('local')) {
            return false;
        }

        if ($this->unassignedMatchService->isUnassignedSynced($existing)) {
            return true;
        }

        if (! $this->threadMatchService->isManualClientUpload($existing)) {
            $status = (string) ($existing->sync_assignment_status ?? '');
            if (in_array($status, ['auto_assigned', 'manual_assigned'], true)
                && (int) ($existing->client_id ?? 0) > 0
                && ! empty($existing->synced_email_id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $parsedData
     */
    protected function findAssignedClientDuplicate(
        int $clientId,
        ?int $clientMatterId,
        array $parsedData,
        string $fileHash,
        string $mailType = 'inbox'
    ): ?EmailLog {
        $mailType = $mailType === 'sent' ? 'sent' : 'inbox';

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
            if ($byMessageId instanceof EmailLog
                && ($mailType === 'sent' || ! $this->incomingPrimaryRecipientsDiffer($parsedData, $byMessageId))) {
                return $byMessageId;
            }
        }

        $subject = trim((string) ($parsedData['subject'] ?? ''));
        $sender = strtolower(trim((string) ($parsedData['sender_email'] ?? $parsedData['from_mail'] ?? '')));
        $sentAt = $this->resolveParsedSentTime($parsedData);

        if ($subject !== '' && $sender !== '' && $sentAt instanceof Carbon) {
            $exact = (clone $base)
                ->where('mail_body_type', $mailType)
                ->whereRaw('LOWER(subject) = ?', [mb_strtolower($subject)])
                ->whereRaw('LOWER(from_mail) LIKE ?', ['%'.$sender.'%'])
                ->whereBetween('fetch_mail_sent_time', [
                    $sentAt->copy()->subMinutes(10),
                    $sentAt->copy()->addMinutes(10),
                ]);

            foreach ($exact->orderByDesc('id')->limit(20)->get() as $row) {
                if (! $row instanceof EmailLog) {
                    continue;
                }
                if ($mailType === 'sent' && ! $this->parsedPrimaryRecipientMatchesStoredTo($parsedData, $row)) {
                    continue;
                }
                if ($mailType !== 'sent' && $this->incomingPrimaryRecipientsDiffer($parsedData, $row)) {
                    continue;
                }

                return $row;
            }
        }

        if ($mailType === 'sent' || $subject === '') {
            return null;
        }

        $normalized = ClientEmailListService::normalizeThreadSubject($subject);
        $needle = ClientEmailListService::threadSubjectSearchNeedle($normalized);
        if ($needle === '') {
            return null;
        }

        $candidates = (clone $base)
            ->where('mail_body_type', 'inbox')
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
            if ($this->incomingPrimaryRecipientsDiffer($parsedData, $candidate)) {
                continue;
            }

            return $candidate;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $parsedData
     */
    protected function resolveParsedSentTime(array $parsedData): ?Carbon
    {
        foreach (['sent_date', 'received_date'] as $key) {
            if (empty($parsedData[$key])) {
                continue;
            }

            try {
                return Carbon::parse((string) $parsedData[$key], (string) config('app.timezone'));
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * Incoming uploads stay duplicates when either To address is missing.
     * They are different emails only when both primary To addresses exist and differ.
     *
     * @param  array<string, mixed>  $parsedData
     */
    protected function incomingPrimaryRecipientsDiffer(array $parsedData, EmailLog $existing): bool
    {
        $parsedPrimary = $this->primaryRecipientEmailFromParsed($parsedData);
        $storedPrimary = $this->primaryRecipientEmailFromStored((string) ($existing->to_mail ?? ''));
        if ($parsedPrimary === null || $storedPrimary === null) {
            return false;
        }

        return $parsedPrimary !== $storedPrimary;
    }

    /**
     * @param  array<string, mixed>  $parsedData
     */
    protected function parsedPrimaryRecipientMatchesStoredTo(array $parsedData, EmailLog $existing): bool
    {
        $parsedPrimary = $this->primaryRecipientEmailFromParsed($parsedData);
        $storedPrimary = $this->primaryRecipientEmailFromStored((string) ($existing->to_mail ?? ''));
        if ($parsedPrimary === null || $storedPrimary === null) {
            return false;
        }

        return $parsedPrimary === $storedPrimary;
    }

    /**
     * @param  array<string, mixed>  $parsedData
     */
    protected function primaryRecipientEmailFromParsed(array $parsedData): ?string
    {
        foreach (['to_recipients', 'recipients'] as $key) {
            $list = $parsedData[$key] ?? null;
            if (! is_array($list) || $list === []) {
                continue;
            }

            foreach ($list as $entry) {
                if ($entry === null) {
                    continue;
                }
                $normalized = IncomingEmailSyncService::normalizeRecipientEntry((string) $entry);
                if ($normalized !== '' && filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
                    return strtolower($normalized);
                }
            }
        }

        return null;
    }

    protected function primaryRecipientEmailFromStored(string $toMail): ?string
    {
        $toMail = trim($toMail);
        if ($toMail === '') {
            return null;
        }

        foreach (preg_split('/\s*,\s*/', $toMail) ?: [] as $part) {
            $normalized = IncomingEmailSyncService::normalizeRecipientEntry((string) $part);
            if ($normalized !== '' && filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
                return strtolower($normalized);
            }
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
            'open_folder' => ($email->mail_body_type === 'sent') ? 'sent' : 'inbox',
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
            return $email->mail_body_type === 'sent' ? 'unassigned_sent' : 'unassigned_inbox';
        }

        return $email->mail_body_type === 'sent' ? 'client_sent' : 'client_inbox';
    }

    public function locationLabel(EmailLog $email): string
    {
        if ($this->unassignedMatchService->isUnassignedSynced($email)) {
            return $email->mail_body_type === 'sent'
                ? 'Unassigned Mail → Sent'
                : 'Unassigned Mail → Incoming';
        }

        $hasMatter = (int) ($email->client_matter_id ?? 0) > 0;
        if ($email->mail_body_type === 'sent') {
            return $hasMatter ? 'Client matter → Sent' : 'Client → Emails (Sent)';
        }

        return $hasMatter ? 'Client matter → Incoming' : 'Client → Emails (Incoming)';
    }

    public function assignmentLabel(EmailLog $email): string
    {
        $status = (string) ($email->sync_assignment_status ?? '');
        if ((string) ($email->sync_source ?? '') === EmailLog::SYNC_SOURCE_OUTLOOK_ADDIN) {
            return 'Outlook add-in';
        }
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

        $message = 'Already exists — this email is already on the file at: '.$match['location_label']
            .' ('.$match['assignment_label'].') and was not uploaded again.';
        $message .= ' Subject: "'.$subject.'" from '.$from;
        if ($when !== '') {
            $message .= ' ('.$when.')';
        }

        return $message;
    }
}
