<?php

namespace App\Services\Email;

use App\Http\Controllers\CRM\EmailUploadController;
use App\Models\Admin;
use App\Models\Document;
use App\Models\EmailLog;
use App\Services\EmailSync\IncomingEmailSyncService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Hydrate missing To/Cc/Bcc on email_logs from the stored original .msg/.eml when available.
 */
class EmailLogRecipientBackfillService
{
    /**
     * @return array{from: string, to: string, cc: string, bcc: string}
     */
    public function ensureRecipients(EmailLog $email, bool $persist = true): array
    {
        $type = $email->type ?? null;
        $from = (string) ($email->from_mail ?? '');
        $to = EmailLog::resolveRecipientDisplay((string) ($email->to_mail ?? ''), $type);
        $cc = EmailLog::resolveRecipientDisplay((string) ($email->cc ?? ''), $type);
        $bcc = EmailLog::resolveRecipientDisplay((string) ($email->bcc ?? ''), $type);

        if (($cc === '' || $bcc === '') && (int) ($email->uploaded_doc_id ?? 0) > 0) {
            $parsed = $this->parseOriginalUpload($email);
            if (is_array($parsed) && empty($parsed['error'])) {
                if ($cc === '') {
                    $cc = $this->formatParsedRecipientList($parsed, 'cc_recipients');
                }
                if ($bcc === '') {
                    $bcc = $this->formatParsedRecipientList($parsed, 'bcc_recipients');
                }

                if ($persist) {
                    $dirty = false;
                    if ($cc !== '' && trim((string) ($email->cc ?? '')) === '') {
                        $email->cc = $cc;
                        $dirty = true;
                    }
                    if ($bcc !== '' && trim((string) ($email->bcc ?? '')) === '') {
                        $email->bcc = $bcc;
                        $dirty = true;
                    }
                    if ($dirty) {
                        $email->save();
                    }
                }
            }
        }

        return [
            'from' => $from,
            'to' => $to,
            'cc' => $cc,
            'bcc' => $bcc,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseOriginalUpload(EmailLog $email): ?array
    {
        $doc = Document::query()->find((int) $email->uploaded_doc_id);
        if (! $doc) {
            return null;
        }

        $path = $this->resolveDocumentS3Path($doc, $email);
        if ($path === null || $path === '' || ! Storage::disk('s3')->exists($path)) {
            return null;
        }

        $bytes = Storage::disk('s3')->get($path);
        if ($bytes === '' || $bytes === null) {
            return null;
        }

        $fileName = (string) ($doc->file_name ?? 'email.msg');
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (! in_array($extension, ['msg', 'eml'], true)) {
            $extension = strtolower((string) ($doc->filetype ?? 'msg'));
        }
        if (! in_array($extension, ['msg', 'eml'], true)) {
            $extension = 'msg';
        }

        $tempPath = storage_path('app/temp/recipient-hydrate-' . Str::uuid() . '.' . $extension);
        $dir = dirname($tempPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        try {
            file_put_contents($tempPath, $bytes);
            $uploadedFile = new UploadedFile($tempPath, $fileName, null, null, true);

            /** @var EmailUploadController $upload */
            $upload = app(EmailUploadController::class);

            return $upload->parseEmailMetadataForSync($uploadedFile);
        } finally {
            if (is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    private function resolveDocumentS3Path(Document $document, EmailLog $email): ?string
    {
        if (! empty($document->myfile) && str_starts_with((string) $document->myfile, 'http')) {
            $parsedPath = parse_url((string) $document->myfile, PHP_URL_PATH);
            if (is_string($parsedPath) && $parsedPath !== '') {
                $path = ltrim(urldecode($parsedPath), '/');
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

    /**
     * @param  array<string, mixed>  $parsedData
     */
    private function formatParsedRecipientList(array $parsedData, string $primaryKey, ?string $fallbackKey = null): string
    {
        $list = $parsedData[$primaryKey] ?? null;
        if ((! is_array($list) || $list === []) && $fallbackKey !== null) {
            $list = $parsedData[$fallbackKey] ?? [];
        }
        if (! is_array($list) || $list === []) {
            return '';
        }

        $normalized = [];
        foreach ($list as $entry) {
            if ($entry === null) {
                continue;
            }
            $entry = trim((string) $entry);
            if ($entry === '' || str_contains($entry, 'object at 0x')) {
                continue;
            }
            $normalizedEntry = IncomingEmailSyncService::normalizeRecipientEntry($entry);
            if ($normalizedEntry !== '') {
                $normalized[] = $normalizedEntry;
            }
        }

        return $normalized !== [] ? implode(',', array_values(array_unique($normalized))) : '';
    }
}