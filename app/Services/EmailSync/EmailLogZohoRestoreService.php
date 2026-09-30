<?php

namespace App\Services\EmailSync;

use App\Http\Controllers\CRM\EmailUploadController;
use App\Models\Admin;
use App\Models\Document;
use App\Models\Email;
use App\Models\EmailLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Re-fetch one synced email from Zoho IMAP and refresh HTML body + S3 (.eml / Parsed PDF).
 */
class EmailLogZohoRestoreService
{
    public function __construct(
        private ZohoImapFetcher $fetcher,
        private EmailUploadController $uploadController,
    ) {}

    /**
     * @return array{success: bool, message: string, pdf_doc_id?: int}
     */
    public function restore(EmailLog $email): array
    {
        $uid = (int) ($email->imap_uid ?? 0);
        if ($uid <= 0) {
            return ['success' => false, 'message' => 'No imap_uid on this email log.'];
        }

        $mailbox = $this->resolveMailbox($email);
        if (! $mailbox) {
            return ['success' => false, 'message' => 'Could not resolve Zoho mailbox for this email.'];
        }

        $sentFolders = (array) config('imap_sync.sent_folders', ['Sent']);
        $inboxFolders = (array) config('imap_sync.folders', ['INBOX']);
        $preferredFolder = strtolower((string) ($email->mail_body_type ?? 'inbox')) === 'sent'
            ? ($sentFolders[0] ?? 'Sent')
            : ($inboxFolders[0] ?? 'INBOX');

        try {
            $fetched = $this->fetcher->fetchRawMessageByUid($mailbox, $uid, $preferredFolder);
        } catch (\Throwable $e) {
            Log::warning('EmailLog Zoho restore: IMAP fetch failed', [
                'email_log_id' => $email->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Zoho IMAP fetch failed: ' . $e->getMessage()];
        }

        if (! $fetched || empty($fetched['raw_eml'])) {
            return ['success' => false, 'message' => 'Message not found in Zoho for this UID.'];
        }

        $tempDir = storage_path('app/temp/zoho-restore');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        $tempPath = $tempDir . '/' . Str::uuid() . '.eml';

        try {
            file_put_contents($tempPath, $fetched['raw_eml']);

            $uploadedFile = new UploadedFile($tempPath, 'restore-' . $uid . '.eml', 'message/rfc822', null, true);
            $parsed = $this->uploadController->parseEmailFileForSync($uploadedFile, true);
            if (! $parsed || ! empty($parsed['error']) || (isset($parsed['success']) && ! $parsed['success'])) {
                return [
                    'success' => false,
                    'message' => 'Python parse failed: ' . ($parsed['error'] ?? 'unknown error'),
                ];
            }

            $html = (string) ($parsed['html_content'] ?? '');
            $text = (string) ($parsed['text_content'] ?? '');
            $body = $html !== '' ? $html : $text;
            $body = str_replace("\0", '', $body);

            if ($body !== '') {
                $email->message = $body;
            }
            if (! empty($parsed['text_preview'])) {
                $email->text_preview = $parsed['text_preview'];
            } elseif ($text !== '') {
                $email->text_preview = EmailLog::plainTextPreview($text, 200);
            }

            $pdfDocId = $this->replaceOrCreatePdf($email, $parsed);
            if ($pdfDocId) {
                $email->pdf_doc_id = $pdfDocId;
            }

            if (! empty($email->uploaded_doc_id)) {
                $this->refreshSourceEmlDocument($email, $fetched['raw_eml']);
            }

            $email->save();

            return [
                'success' => true,
                'message' => 'Restored from Zoho.',
                'pdf_doc_id' => $pdfDocId ?: (int) ($email->pdf_doc_id ?? 0),
            ];
        } finally {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    private function resolveMailbox(EmailLog $email): ?Email
    {
        if (! empty($email->synced_email_id)) {
            $mailbox = Email::query()->find((int) $email->synced_email_id);
            if ($mailbox) {
                return $mailbox;
            }
        }

        if (! empty($email->mailbox_email)) {
            return Email::query()
                ->whereRaw('LOWER(email) = ?', [strtolower(trim((string) $email->mailbox_email))])
                ->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function replaceOrCreatePdf(EmailLog $email, array $parsed): ?int
    {
        $pdfBytes = \App\Services\PythonService::resolvePdfBytesFromParsed($parsed);
        if ($pdfBytes === null || $pdfBytes === '') {
            return null;
        }

        $clientRef = 'client_' . (int) $email->client_id;
        if (! empty($email->client_id)) {
            $admin = Admin::query()->select('client_id')->find((int) $email->client_id);
            if ($admin && ! empty($admin->client_id)) {
                $clientRef = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', (string) $admin->client_id) ?: $clientRef;
            }
        } elseif (! empty($email->mailbox_email)) {
            $clientRef = 'sync-inbox/' . preg_replace('/[^a-zA-Z0-9\-_.@]/', '_', strtolower((string) $email->mailbox_email));
        }

        $docType = $email->conversion_type ?: 'conversion_email_fetch';
        $mailType = $email->mail_body_type ?: 'inbox';
        $pdfKey = 'zoho-restore-' . $email->id . '-' . time() . '.pdf';

        $existing = ! empty($email->pdf_doc_id) ? Document::query()->find((int) $email->pdf_doc_id) : null;
        if ($existing && ! empty($existing->myfile_key)) {
            $pdfKey = (string) $existing->myfile_key;
            if (! str_ends_with(strtolower($pdfKey), '.pdf')) {
                $pdfKey = pathinfo($pdfKey, PATHINFO_FILENAME) . '.pdf';
            }
        }

        $pdfPath = $clientRef . '/' . $docType . '/' . $mailType . '/' . $pdfKey;
        if ($existing) {
            $existingPath = $this->resolveDocumentS3Path($existing, $email);
            if ($existingPath) {
                $pdfPath = $existingPath;
            }
        }

        if (! Storage::disk('s3')->put($pdfPath, $pdfBytes)) {
            return null;
        }

        if ($existing) {
            $existing->file_size = strlen($pdfBytes);
            $existing->myfile = Storage::disk('s3')->url($pdfPath);
            $existing->myfile_key = basename($pdfPath);
            $existing->filetype = 'pdf';
            $existing->save();

            return (int) $existing->id;
        }

        $pdfDocument = new Document();
        $pdfDocument->file_name = pathinfo((string) ($email->subject ?: 'email'), PATHINFO_FILENAME) ?: 'email';
        $pdfDocument->filetype = 'pdf';
        $pdfDocument->user_id = $email->user_id;
        $pdfDocument->myfile = Storage::disk('s3')->url($pdfPath);
        $pdfDocument->myfile_key = basename($pdfPath);
        $pdfDocument->client_id = $email->client_id;
        $pdfDocument->type = $email->type;
        $pdfDocument->mail_type = $mailType;
        $pdfDocument->file_size = strlen($pdfBytes);
        $pdfDocument->doc_type = $docType;
        $pdfDocument->client_matter_id = $email->client_matter_id;
        $pdfDocument->save();

        return (int) $pdfDocument->id;
    }

    private function refreshSourceEmlDocument(EmailLog $email, string $rawEml): void
    {
        $doc = Document::query()->find((int) $email->uploaded_doc_id);
        if (! $doc) {
            return;
        }

        $path = $this->resolveDocumentS3Path($doc, $email);
        if (! $path) {
            return;
        }

        if (Storage::disk('s3')->put($path, $rawEml)) {
            $doc->file_size = strlen($rawEml);
            $doc->save();
        }
    }

    private function resolveDocumentS3Path(Document $document, EmailLog $email): ?string
    {
        if (! empty($document->myfile) && str_starts_with((string) $document->myfile, 'http')) {
            $path = parse_url((string) $document->myfile, PHP_URL_PATH);
            if (is_string($path) && $path !== '') {
                $path = ltrim(urldecode($path), '/');
                $bucket = (string) config('filesystems.disks.s3.bucket', '');
                if ($bucket !== '' && str_starts_with($path, $bucket . '/')) {
                    $path = substr($path, strlen($bucket) + 1);
                }

                return $path;
            }
        }

        if (! empty($document->myfile_key)) {
            $client = Admin::query()->select('client_id')->find($email->client_id);
            $clientRef = preg_replace(
                '/[^a-zA-Z0-9\-_\.]/',
                '_',
                (string) ($client->client_id ?? ('client_' . $email->client_id))
            );
            $docType = $document->doc_type ?? $email->conversion_type ?? 'conversion_email_fetch';
            $mailType = $document->mail_type ?? $email->mail_body_type ?? 'inbox';

            return $clientRef . '/' . $docType . '/' . $mailType . '/' . $document->myfile_key;
        }

        return null;
    }
}
