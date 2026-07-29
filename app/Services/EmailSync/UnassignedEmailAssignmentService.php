<?php

namespace App\Services\EmailSync;

use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\Document;
use App\Models\EmailLog;
use App\Models\EmailLogAttachment;
use App\Services\Email\EmailCalendarMergeService;
use App\Traits\LogsClientActivity;
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
    public function assignToClient(int $emailLogId, int $clientId, int $clientMatterId, ?int $staffUserId = null): array
    {
        $emailLog = EmailLog::with('attachments')->find($emailLogId);
        if (! $emailLog) {
            return ['success' => false, 'message' => 'Email not found.'];
        }

        if ($emailLog->client_id) {
            if ((int) $emailLog->client_id === $clientId && (int) $emailLog->client_matter_id === $clientMatterId) {
                return ['success' => true, 'message' => 'Email is already assigned to this client matter.', 'email_log_id' => $emailLogId];
            }
            if ((int) $emailLog->client_id !== $clientId) {
                return ['success' => false, 'message' => 'Email is already assigned to another client.'];
            }
        }

        $user = Auth::guard('admin')->user();
        if ($user && ! \App\Services\StaffClientVisibility::canAccessClientOrLead($user, $clientId)) {
            return ['success' => false, 'message' => 'Unauthorized access to client.'];
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
            if ($emailLog->uploaded_doc_id) {
                $this->relocateDocument((int) $emailLog->uploaded_doc_id, $sourcePrefix, $destPrefix, $docType, $mailType, $clientId, $clientMatterId, $staffUserId);
            }

            if ($emailLog->pdf_doc_id) {
                $this->relocateDocument((int) $emailLog->pdf_doc_id, $sourcePrefix, $destPrefix, $docType, $mailType, $clientId, $clientMatterId, $staffUserId);
            }

            foreach ($emailLog->attachments as $attachment) {
                $this->relocateAttachment($attachment, $sourcePrefix, $destPrefix);
            }

            $emailLog->client_id = $clientId;
            $emailLog->client_matter_id = $clientMatterId;
            $emailLog->type = in_array($client->type, ['client', 'lead'], true) ? $client->type : 'client';
            $emailLog->user_id = $staffUserId;
            $emailLog->sync_assignment_status = 'manual_assigned';
            $emailLog->save();

            $matter->updated_at = now();
            $matter->save();

            if ($client->type === 'client') {
                $matterRef = $matter->client_unique_matter_no ?: '';
                $subjectLine = $emailLog->subject ?: 'Email';
                $activitySubject = $matterRef !== ''
                    ? "assigned Email: {$subjectLine} - {$matterRef}"
                    : "assigned Email: {$subjectLine}";
                if (strlen($activitySubject) > 100) {
                    $activitySubject = substr($activitySubject, 0, 97) . '...';
                }
                $from = $emailLog->from_mail ?: 'Unknown';
                $this->logClientActivity($clientId, $activitySubject, "<p>From: {$from}</p>", 'email');
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
                'message' => 'Email assigned to client successfully.',
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

        $this->moveS3Object($sourcePath, $destPath);

        $disk = Storage::disk('s3');
        $document->client_id = $clientId;
        $document->client_matter_id = $clientMatterId;
        $document->user_id = $staffUserId;
        if ($disk->exists($destPath)) {
            $document->myfile = $disk->url($destPath);
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
        $this->moveS3Object($s3Key, $destKey);

        $disk = Storage::disk('s3');
        $attachment->s3_key = $destKey;
        if ($disk->exists($destKey)) {
            $attachment->file_path = $disk->url($destKey);
        }
        $attachment->save();
    }

    protected function moveS3Object(string $sourcePath, string $destPath): void
    {
        if ($sourcePath === $destPath) {
            return;
        }

        $disk = Storage::disk('s3');
        if (! $disk->exists($sourcePath)) {
            return;
        }

        if ($disk->exists($destPath)) {
            $disk->delete($sourcePath);

            return;
        }

        $disk->copy($sourcePath, $destPath);
        $disk->delete($sourcePath);
    }
}
