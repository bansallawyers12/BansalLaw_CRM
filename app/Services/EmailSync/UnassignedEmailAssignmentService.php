<?php

namespace App\Services\EmailSync;

use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\Document;
use App\Models\EmailLog;
use App\Models\EmailLogAttachment;
use App\Services\Email\EmailCalendarMergeService;
use App\Support\EmailTimelineActivity;
use App\Support\StaffClientVisibility;
use App\Traits\LogsClientActivity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class UnassignedEmailAssignmentService
{
    use LogsClientActivity;

    /**
     * @return array{success: bool, message?: string, email_log_id?: int}
     */
    public function assignToClient(
        int $emailLogId,
        int $clientId,
        int $clientMatterId,
        ?int $staffUserId = null,
        bool $allowReassign = false,
        string $assignmentStatus = 'manual_assigned',
        bool $enforceStaffAccess = true
    ): array
    {
        $emailLog = EmailLog::find($emailLogId);
        if (! $emailLog) {
            return ['success' => false, 'message' => 'Email not found.'];
        }

        $previousClientId = (int) ($emailLog->client_id ?? 0);
        $isReassigning = $previousClientId > 0 && $previousClientId !== $clientId;

        if ($emailLog->client_id) {
            if ((int) $emailLog->client_id === $clientId && (int) $emailLog->client_matter_id === $clientMatterId) {
                return ['success' => true, 'message' => 'Email is already assigned to this client matter.', 'email_log_id' => $emailLogId];
            }
            if ($isReassigning && ! $allowReassign) {
                return ['success' => false, 'message' => 'Email is already assigned to another client.'];
            }
        }

        $user = Auth::guard('admin')->user();
        if ($enforceStaffAccess && $user && ! StaffClientVisibility::canAccessClientOrLead($clientId, $user)) {
            return ['success' => false, 'message' => 'Unauthorized access to target client.'];
        }

        if ($enforceStaffAccess && $user && $isReassigning && $previousClientId > 0 && ! StaffClientVisibility::canAccessClientOrLead($previousClientId, $user)) {
            return ['success' => false, 'message' => 'Unauthorized access to current assigned client.'];
        }

        $client = Admin::query()->where('id', $clientId)->whereIn('type', ['client', 'lead'])->first();
        if (! $client) {
            return ['success' => false, 'message' => 'Client not found.'];
        }

        $matter = ClientMatter::query()
            ->where('id', $clientMatterId)
            ->where('client_id', $clientId)
            ->first();
        if (! $matter) {
            return ['success' => false, 'message' => 'Client matter not found for this client.'];
        }

        $destPrefix = $this->clientStoragePrefix($client);
        $mailType = (string) ($emailLog->mail_body_type ?: 'inbox');
        $docType = (string) ($emailLog->conversion_type ?: 'conversion_email_fetch');
        $staffUserId = $staffUserId ?: (int) Auth::id();

        $sourcePrefix = $this->resolveSourcePrefix($emailLog);

        try {
            // Persist assignment before S3 moves so a hung HeadObject/copy cannot
            // leave the modal on "Assigning..." with the email still unassigned.
            $emailLog->client_id = $clientId;
            $emailLog->client_matter_id = $clientMatterId;
            $emailLog->type = in_array($client->type, ['client', 'lead'], true) ? $client->type : 'client';
            $emailLog->user_id = $staffUserId;
            $emailLog->sync_assignment_status = in_array($assignmentStatus, ['auto_assigned', 'manual_assigned'], true)
                ? $assignmentStatus
                : 'manual_assigned';
            $emailLog->save();

            $matter->updated_at = now();
            $matter->save();

            try {
                if ($emailLog->uploaded_doc_id) {
                    $this->relocateDocument((int) $emailLog->uploaded_doc_id, $sourcePrefix, $destPrefix, $docType, $mailType, $clientId, $clientMatterId, $staffUserId);
                }

                if ($emailLog->pdf_doc_id) {
                    $this->relocateDocument((int) $emailLog->pdf_doc_id, $sourcePrefix, $destPrefix, $docType, $mailType, $clientId, $clientMatterId, $staffUserId);
                }

                foreach ($this->attachmentsFor($emailLog) as $attachment) {
                    $this->relocateAttachment($attachment, $sourcePrefix, $destPrefix);
                }
            } catch (Throwable $storageException) {
                Log::warning('Assigned email but could not relocate storage objects', [
                    'email_log_id' => $emailLogId,
                    'client_id' => $clientId,
                    'error' => $storageException->getMessage(),
                ]);
            }

            if ($client->type === 'client') {
                try {
                    $matterRef = $matter->client_unique_matter_no ?: '';
                    $subjectLine = $emailLog->subject ?: 'Email';
                    $from = $emailLog->from_mail ?: 'Unknown';
                    $activitySubject = $mailType === 'inbox'
                        ? EmailTimelineActivity::subjectReceived($subjectLine, $matterRef !== '' ? $matterRef : null)
                        : EmailTimelineActivity::subjectAssigned($subjectLine, $matterRef !== '' ? $matterRef : null);
                    $this->logClientActivity(
                        $clientId,
                        $activitySubject,
                        EmailTimelineActivity::descriptionFrom((string) $from),
                        'email'
                    );
                } catch (Throwable $activityException) {
                    Log::warning('Email was assigned but client activity logging failed', [
                        'email_log_id' => $emailLogId,
                        'client_id' => $clientId,
                        'error' => $activityException->getMessage(),
                    ]);
                }
            }

            try {
                app(EmailCalendarMergeService::class)->mergePendingForEmail(
                    $emailLog->fresh(['attachments', 'calendarLinks']),
                    $staffUserId
                );
            } catch (Throwable $calendarException) {
                Log::warning('Email calendar merge failed after assignment', [
                    'email_log_id' => $emailLog->id,
                    'client_id' => $clientId,
                    'error' => $calendarException->getMessage(),
                ]);
            }

            return [
                'success' => true,
                'message' => $isReassigning
                    ? 'Email moved to the selected client successfully.'
                    : 'Email assigned to client successfully.',
                'email_log_id' => $emailLog->id,
            ];
        } catch (Throwable $e) {
            Log::error('Failed to assign synced email to client', [
                'email_log_id' => $emailLogId,
                'client_id' => $clientId,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Could not assign email: ' . $e->getMessage()];
        }
    }

    /**
     * Return a wrongly assigned synced email to the unassigned inbox.
     *
     * @return array{success: bool, message?: string, email_log_id?: int}
     */
    public function unlinkFromClient(int $emailLogId, ?int $staffUserId = null): array
    {
        $emailLog = EmailLog::find($emailLogId);
        if (! $emailLog) {
            return ['success' => false, 'message' => 'Email not found.'];
        }

        if (! $emailLog->synced_email_id) {
            return ['success' => false, 'message' => 'Only synced inbox emails can be unlinked.'];
        }

        if (! $emailLog->client_id) {
            return [
                'success' => true,
                'message' => 'Email is already unassigned.',
                'email_log_id' => $emailLogId,
            ];
        }

        $oldClientId = (int) $emailLog->client_id;
        $oldClient = Admin::query()->find($oldClientId);
        $sourcePrefix = $oldClient
            ? $this->clientStoragePrefix($oldClient)
            : $this->resolveSourcePrefix($emailLog);
        $destPrefix = $this->unassignedStoragePrefix($emailLog);
        $mailType = (string) ($emailLog->mail_body_type ?: 'inbox');
        $docType = (string) ($emailLog->conversion_type ?: 'conversion_email_fetch');

        try {
            $emailLog->client_id = null;
            $emailLog->client_matter_id = null;
            $emailLog->user_id = $staffUserId ?: (int) Auth::id();
            $emailLog->sync_assignment_status = 'unlinked';
            $emailLog->save();

            try {
                if ($emailLog->uploaded_doc_id) {
                    $this->unlinkDocument(
                        (int) $emailLog->uploaded_doc_id,
                        $sourcePrefix,
                        $destPrefix,
                        $docType,
                        $mailType
                    );
                }

                if ($emailLog->pdf_doc_id) {
                    $this->unlinkDocument(
                        (int) $emailLog->pdf_doc_id,
                        $sourcePrefix,
                        $destPrefix,
                        $docType,
                        $mailType
                    );
                }

                foreach ($this->attachmentsFor($emailLog) as $attachment) {
                    $this->relocateAttachment($attachment, $sourcePrefix, $destPrefix);
                }
            } catch (Throwable $storageException) {
                Log::warning('Unlinked email but could not relocate storage objects', [
                    'email_log_id' => $emailLogId,
                    'client_id' => $oldClientId,
                    'error' => $storageException->getMessage(),
                ]);
            }

            if ($oldClient && $oldClient->type === 'client') {
                try {
                    $subjectLine = $emailLog->subject ?: 'Email';
                    $activitySubject = "unlinked Email: {$subjectLine}";
                    if (strlen($activitySubject) > 100) {
                        $activitySubject = substr($activitySubject, 0, 97) . '...';
                    }
                    $this->logClientActivity(
                        $oldClientId,
                        $activitySubject,
                        '<p>Email returned to the unassigned inbox.</p>',
                        'email'
                    );
                } catch (Throwable $activityException) {
                    Log::warning('Email was unlinked but client activity logging failed', [
                        'email_log_id' => $emailLogId,
                        'client_id' => $oldClientId,
                        'error' => $activityException->getMessage(),
                    ]);
                }
            }

            return [
                'success' => true,
                'message' => 'Email unlinked and returned to the unassigned inbox.',
                'email_log_id' => $emailLog->id,
            ];
        } catch (Throwable $e) {
            Log::error('Failed to unlink synced email from client', [
                'email_log_id' => $emailLogId,
                'client_id' => $oldClientId,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Could not unlink email: ' . $e->getMessage()];
        }
    }

    /**
     * @return Collection<int, EmailLogAttachment>
     */
    protected function attachmentsFor(EmailLog $emailLog): Collection
    {
        return $emailLog->attachments()->get();
    }

    protected function clientStoragePrefix(Admin $client): string
    {
        if (! empty($client->client_id)) {
            return (string) $client->client_id;
        }

        return 'client_' . $client->id;
    }

    protected function resolveSourcePrefix(EmailLog $emailLog): string
    {
        if ($emailLog->client_id) {
            $client = Admin::query()->find($emailLog->client_id);
            if ($client) {
                return $this->clientStoragePrefix($client);
            }
        }

        if ($emailLog->mailbox_email) {
            $safeMailbox = preg_replace('/[^a-zA-Z0-9\-_.@]/', '_', strtolower($emailLog->mailbox_email));

            return config('imap_sync.unassigned_storage_prefix', 'sync-inbox') . '/' . $safeMailbox;
        }

        if ($emailLog->uploaded_doc_id) {
            $doc = Document::find($emailLog->uploaded_doc_id);
            if ($doc && $doc->myfile) {
                $parsed = $this->parseStoragePrefixFromUrl((string) $doc->myfile);
                if ($parsed) {
                    return $parsed;
                }
            }
        }

        return config('imap_sync.unassigned_storage_prefix', 'sync-inbox');
    }

    protected function unassignedStoragePrefix(EmailLog $emailLog): string
    {
        if ($emailLog->mailbox_email) {
            $safeMailbox = preg_replace('/[^a-zA-Z0-9\-_.@]/', '_', strtolower($emailLog->mailbox_email));

            return config('imap_sync.unassigned_storage_prefix', 'sync-inbox') . '/' . $safeMailbox;
        }

        return config('imap_sync.unassigned_storage_prefix', 'sync-inbox');
    }

    protected function parseStoragePrefixFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        $docTypeIndex = array_search('conversion_email_fetch', $segments, true);
        if ($docTypeIndex === false || $docTypeIndex === 0) {
            return null;
        }

        return implode('/', array_slice($segments, 0, $docTypeIndex));
    }

    protected function relocateDocument(
        int $documentId,
        string $sourcePrefix,
        string $destPrefix,
        string $docType,
        string $mailType,
        int $clientId,
        int $clientMatterId,
        int $staffUserId
    ): void {
        $document = Document::find($documentId);
        if (! $document || empty($document->myfile_key)) {
            return;
        }

        $filename = (string) $document->myfile_key;
        $sourcePath = "{$sourcePrefix}/{$docType}/{$mailType}/{$filename}";
        $destPath = "{$destPrefix}/{$docType}/{$mailType}/{$filename}";

        $moved = $this->moveS3Object($sourcePath, $destPath);

        $document->client_id = $clientId;
        $document->client_matter_id = $clientMatterId;
        $document->user_id = $staffUserId;
        if ($moved) {
            $document->myfile = $this->storagePublicUrl($destPath);
        }
        $document->save();
    }

    protected function unlinkDocument(
        int $documentId,
        string $sourcePrefix,
        string $destPrefix,
        string $docType,
        string $mailType
    ): void {
        $document = Document::find($documentId);
        if (! $document || empty($document->myfile_key)) {
            return;
        }

        $filename = (string) $document->myfile_key;
        $sourcePath = "{$sourcePrefix}/{$docType}/{$mailType}/{$filename}";
        $destPath = "{$destPrefix}/{$docType}/{$mailType}/{$filename}";

        $moved = $this->moveS3Object($sourcePath, $destPath);

        $document->client_id = null;
        $document->client_matter_id = null;
        if ($moved) {
            $document->myfile = $this->storagePublicUrl($destPath);
        }
        $document->save();
    }

    protected function relocateAttachment(EmailLogAttachment $attachment, string $sourcePrefix, string $destPrefix): void
    {
        if (empty($attachment->s3_key)) {
            return;
        }

        $s3Key = (string) $attachment->s3_key;
        if (! str_starts_with($s3Key, $sourcePrefix . '/')) {
            return;
        }

        $destKey = $destPrefix . substr($s3Key, strlen($sourcePrefix));
        if (! $this->moveS3Object($s3Key, $destKey)) {
            return;
        }

        $attachment->s3_key = $destKey;
        $attachment->file_path = $this->storagePublicUrl($destKey);
        $attachment->save();
    }

    protected function storagePublicUrl(string $path): string
    {
        try {
            return Storage::disk('s3')->url($path);
        } catch (Throwable $e) {
            return $path;
        }
    }

    protected function moveS3Object(string $sourcePath, string $destPath): bool
    {
        if ($sourcePath === $destPath) {
            return true;
        }

        $disk = Storage::disk('s3');

        try {
            $disk->move($sourcePath, $destPath);

            return true;
        } catch (Throwable $e) {
            try {
                if ($disk->exists($destPath)) {
                    try {
                        $disk->delete($sourcePath);
                    } catch (Throwable $ignored) {
                    }

                    return true;
                }
            } catch (Throwable $existsException) {
                Log::warning('Could not verify synced email storage object after move failure', [
                    'source' => $sourcePath,
                    'dest' => $destPath,
                    'error' => $existsException->getMessage(),
                ]);
            }

            Log::warning('Could not move synced email storage object', [
                'source' => $sourcePath,
                'dest' => $destPath,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
