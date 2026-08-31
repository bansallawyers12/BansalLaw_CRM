<?php

namespace App\Console\Commands;

use App\Http\Controllers\CRM\EmailUploadController;
use App\Models\Admin;
use App\Models\Document;
use App\Models\Email;
use App\Models\EmailLog;
use App\Services\EmailSync\ZohoImapFetcher;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Local-only repair: re-fetch one email_logs row from Zoho IMAP and refresh
 * HTML body + Parsed email.pdf. Safe for APP_ENV=local (and testing).
 */
class RestoreEmailFromZoho extends Command
{
    protected $signature = 'emails:restore-from-zoho
                            {email-log-id : email_logs.id to restore}
                            {--force : Required outside local/testing (e.g. production)}';

    protected $description = 'Re-fetch one synced email from Zoho and refresh body/PDF (local by default; production needs --force)';

    public function handle(ZohoImapFetcher $fetcher, EmailUploadController $uploadController): int
    {
        $isLocalLike = app()->environment(['local', 'testing']);
        if (! $isLocalLike && ! $this->option('force')) {
            $this->error('Refusing to run in ' . app()->environment() . ' without --force.');
            $this->line('Example: php83 artisan emails:restore-from-zoho {id} --force');
            $this->warn('Use the production email_logs.id (local ids may differ).');

            return self::FAILURE;
        }

        if (! $isLocalLike) {
            $this->warn('Running Zoho restore in ' . app()->environment() . ' with --force.');
            if (! $this->confirm('Re-fetch this email from Zoho and overwrite body/PDF on this server?', false)) {
                $this->info('Cancelled.');

                return self::SUCCESS;
            }
        }

        $emailLogId = (int) $this->argument('email-log-id');
        $email = EmailLog::query()->find($emailLogId);
        if (! $email) {
            $this->error("email_logs row {$emailLogId} not found.");

            return self::FAILURE;
        }

        $uid = (int) ($email->imap_uid ?? 0);
        if ($uid <= 0) {
            $this->error('This email has no imap_uid — cannot fetch from Zoho.');

            return self::FAILURE;
        }

        $mailbox = null;
        if (! empty($email->synced_email_id)) {
            $mailbox = Email::query()->find((int) $email->synced_email_id);
        }
        if (! $mailbox && ! empty($email->mailbox_email)) {
            $mailbox = Email::query()
                ->whereRaw('LOWER(email) = ?', [strtolower(trim((string) $email->mailbox_email))])
                ->first();
        }
        if (! $mailbox) {
            $this->error('Could not resolve Zoho mailbox for this email.');

            return self::FAILURE;
        }

        $preferredFolder = strtolower((string) ($email->mail_body_type ?? 'inbox')) === 'sent'
            ? ((array) config('imap_sync.sent_folders', ['Sent']))[0] ?? 'Sent'
            : ((array) config('imap_sync.folders', ['INBOX']))[0] ?? 'INBOX';

        $this->info(sprintf(
            'Restoring email_logs#%d from %s UID %d (folder prefer: %s)…',
            $email->id,
            $mailbox->email,
            $uid,
            $preferredFolder
        ));

        try {
            $fetched = $fetcher->fetchRawMessageByUid($mailbox, $uid, $preferredFolder);
        } catch (\Throwable $e) {
            $this->error('Zoho IMAP fetch failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        if (! $fetched || empty($fetched['raw_eml'])) {
            $this->error('Message not found in Zoho for this UID (checked inbox/sent folders).');

            return self::FAILURE;
        }

        $this->line('Fetched from folder: ' . ($fetched['folder'] ?? '?'));
        if (! empty($fetched['subject'])) {
            $this->line('Zoho subject: ' . $fetched['subject']);
        }

        $tempDir = storage_path('app/temp/zoho-restore');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        $tempPath = $tempDir . '/' . Str::uuid() . '.eml';
        file_put_contents($tempPath, $fetched['raw_eml']);

        try {
            $uploadedFile = new UploadedFile($tempPath, 'restore-' . $uid . '.eml', 'message/rfc822', null, true);
            $parsed = $uploadController->parseEmailFileForSync($uploadedFile, true);
            if (! $parsed || ! empty($parsed['error']) || (isset($parsed['success']) && ! $parsed['success'])) {
                $this->error('Python parse failed: ' . ($parsed['error'] ?? 'unknown error'));

                return self::FAILURE;
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

            $pdfDocId = $this->replaceOrCreatePdf($email, $parsed, $fetched['raw_eml']);
            if ($pdfDocId) {
                $email->pdf_doc_id = $pdfDocId;
                $this->info('PDF preview refreshed (documents.id=' . $pdfDocId . ').');
            } else {
                $this->warn('Body updated, but PDF could not be regenerated'
                    . (! empty($parsed['pdf_error']) ? ': ' . $parsed['pdf_error'] : '.'));
            }

            // Refresh stored .eml bytes when source document exists.
            if (! empty($email->uploaded_doc_id)) {
                $this->refreshSourceEmlDocument($email, $fetched['raw_eml']);
            }

            $email->save();
            $this->info('Restored email_logs#' . $email->id . ' from Zoho.');

            return self::SUCCESS;
        } finally {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function replaceOrCreatePdf(EmailLog $email, array $parsed, string $rawEml): ?int
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
            $this->line('Refreshed source .eml on storage.');
        }
    }

    private function resolveDocumentS3Path(Document $document, EmailLog $email): ?string
    {
        if (! empty($document->myfile) && str_starts_with((string) $document->myfile, 'http')) {
            $path = parse_url((string) $document->myfile, PHP_URL_PATH);
            if (is_string($path) && $path !== '') {
                return ltrim(urldecode($path), '/');
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
