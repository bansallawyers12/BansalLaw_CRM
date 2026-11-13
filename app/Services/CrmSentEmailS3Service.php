<?php

namespace App\Services;

use App\Models\Document;
use App\Models\EmailLog;
use App\Models\EmailLogAttachment;
use App\Models\Admin;
use App\Support\DocumentLabel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

/**
 * Stores CRM-sent emails to AWS S3 (full HTML snapshot + attachments).
 * Enables consistent archival with uploaded .msg emails and attachment download in the Email tab.
 */
class CrmSentEmailS3Service
{
    /**
     * Store a CRM-sent email to S3 and create Document + EmailLogAttachment records.
     *
     * @param EmailLog $emailLog
     * @param string $subject
     * @param string $messageHtml
     * @param array $attachmentPaths Array of local file paths (or [path => displayFilename])
     * @return bool Success
     */
    public function storeToS3(EmailLog $emailLog, string $subject, string $messageHtml, array $attachmentPaths = []): bool
    {
        try {
            $clientId = $emailLog->client_id;
            if (!$clientId) {
                Log::warning('CrmSentEmailS3Service: No client_id, skipping S3 storage');
                return false;
            }

            if (!config('filesystems.disks.s3.key') || !config('filesystems.disks.s3.bucket')) {
                Log::info('CrmSentEmailS3Service: S3 not configured, skipping storage');
                return false;
            }

            $admin = Admin::find($clientId);
            $clientUniqueId = ($admin && !empty($admin->client_id)) ? $admin->client_id : 'client_' . $clientId;
            $sanitizedClientId = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $clientUniqueId);

            $docType = 'crm_sent';
            $uniqueFileName = 'email-' . $emailLog->id . '.html';
            $s3Path = $sanitizedClientId . '/' . $docType . '/sent/' . $uniqueFileName;

            $htmlContent = $this->buildEmailHtml($emailLog, $subject, $messageHtml);

            try {
                $uploaded = Storage::disk('s3')->put($s3Path, $htmlContent);
                if (!$uploaded) {
                    throw new \Exception('S3 put returned false');
                }
                $fileUrl = Storage::disk('s3')->url($s3Path);
            } catch (\Exception $e) {
                Log::error('CrmSentEmailS3Service: S3 upload failed', [
                    'email_log_id' => $emailLog->id,
                    'error' => $e->getMessage(),
                ]);
                return false;
            }

            $document = new Document();
            $document->file_name = 'email-' . $emailLog->id;
            $document->filetype = 'html';
            $document->user_id = $emailLog->user_id;
            $document->myfile = $fileUrl;
            $document->myfile_key = $uniqueFileName;
            $document->client_id = $clientId;
            $document->type = $emailLog->type ?? 'client';
            $document->mail_type = 'sent';
            $document->file_size = strlen($htmlContent);
            $document->doc_type = $docType;
            $document->client_matter_id = $emailLog->client_matter_id;
            $document->save();

            $emailLog->uploaded_doc_id = $document->id;
            $emailLog->save();

            foreach ($attachmentPaths as $pathOrTuple) {
                $filePath = is_array($pathOrTuple) ? ($pathOrTuple['path'] ?? null) : $pathOrTuple;
                $displayName = is_array($pathOrTuple) ? ($pathOrTuple['name'] ?? basename($filePath)) : basename($filePath);
                if ($filePath && file_exists($filePath)) {
                    $this->uploadAttachmentToS3($emailLog->id, $filePath, $displayName, $clientUniqueId);
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::error('CrmSentEmailS3Service: storeToS3 failed', [
                'email_log_id' => $emailLog->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Build full HTML document for the email.
     */
    protected function buildEmailHtml(EmailLog $emailLog, string $subject, string $messageHtml): string
    {
        $from = htmlspecialchars($emailLog->from_mail ?? '');
        $to = htmlspecialchars($emailLog->to_mail ?? '');
        $date = $emailLog->created_at ? $emailLog->created_at->format('d/m/Y h:i a') : date('d/m/Y h:i a');
        $subjectEscaped = htmlspecialchars($subject);
        $body = $messageHtml;

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . $subjectEscaped . '</title></head><body>' .
            '<div style="font-family:Arial,sans-serif;max-width:800px;">' .
            '<p><strong>From:</strong> ' . $from . '</p>' .
            '<p><strong>To:</strong> ' . $to . '</p>' .
            '<p><strong>Date:</strong> ' . $date . '</p>' .
            '<p><strong>Subject:</strong> ' . $subjectEscaped . '</p>' .
            '<hr>' .
            '<div>' . $body . '</div>' .
            '</div></body></html>';
    }

    /**
     * Upload a single attachment to S3 and create EmailLogAttachment record.
     */
    protected function uploadAttachmentToS3(int $emailLogId, string $localPath, string $displayName, string $clientUniqueId): void
    {
        try {
            $content = file_get_contents($localPath);
            if ($content === false) {
                Log::warning('CrmSentEmailS3Service: Could not read attachment', ['path' => $localPath]);
                return;
            }

            $stem = pathinfo($displayName, PATHINFO_FILENAME) ?: 'attachment';
            $extension = pathinfo($displayName, PATHINFO_EXTENSION);
            $stored = DocumentLabel::plainStoredName($stem, $extension);
            $suffix = 2;
            $folder = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $clientUniqueId) . '/attachments/' . $emailLogId . '/';
            $keyTaken = function (string $key): bool {
                try {
                    return Storage::disk('s3')->exists($key);
                } catch (\Throwable) {
                    return false;
                }
            };
            while ($keyTaken($folder . $stored['storage_key']) && $suffix <= 30) {
                $stored = DocumentLabel::plainStoredName($stem . '_' . $suffix, $extension);
                $suffix++;
            }
            $s3Key = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $clientUniqueId) . '/attachments/' . $emailLogId . '/' . $stored['storage_key'];

            $uploaded = Storage::disk('s3')->put($s3Key, $content);
            $s3Path = $uploaded ? Storage::disk('s3')->url($s3Key) : null;

            $contentType = $this->guessContentType($displayName);

            EmailLogAttachment::create([
                'email_log_id' => $emailLogId,
                'filename' => $displayName,
                'display_name' => $displayName,
                'content_type' => $contentType,
                'file_path' => $s3Path,
                's3_key' => $s3Key,
                'file_size' => strlen($content),
                'is_inline' => false,
                'extension' => pathinfo($displayName, PATHINFO_EXTENSION),
            ]);
        } catch (\Exception $e) {
            Log::warning('CrmSentEmailS3Service: Attachment upload failed', [
                'path' => $localPath,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function guessContentType(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $map = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
        return $map[$ext] ?? 'application/octet-stream';
    }
}
