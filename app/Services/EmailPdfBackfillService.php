<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Document;
use App\Models\EmailLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EmailPdfBackfillService
{
    private string $pythonServiceUrl;

    public function __construct()
    {
        $this->pythonServiceUrl = config('services.python.url', env('PYTHON_SERVICE_URL', 'http://127.0.0.1:5002'));
    }

    private function getUrlWithTimezone(string $path): string
    {
        $timezone = config('app.timezone', 'Australia/Sydney');
        $separator = str_contains($path, '?') ? '&' : '?';
        return $this->pythonServiceUrl . $path . $separator . 'timezone=' . urlencode($timezone);
    }

    /**
     * @return array{status: string, message: string, pdf_doc_id?: int}
     */
    public function backfillEmailLog(EmailLog $email, bool $dryRun = false, bool $replace = false): array
    {
        if (! empty($email->pdf_doc_id) && ! $replace) {
            return ['status' => 'skipped', 'message' => 'PDF preview already linked'];
        }

        if (($email->conversion_type ?? '') !== 'conversion_email_fetch') {
            return ['status' => 'skipped', 'message' => 'Not an uploaded .msg email'];
        }

        if (empty($email->uploaded_doc_id)) {
            return ['status' => 'failed', 'message' => 'Missing uploaded_doc_id'];
        }

        $sourceDocument = Document::find($email->uploaded_doc_id);
        if (! $sourceDocument) {
            return ['status' => 'failed', 'message' => 'Source document record not found'];
        }

        $extension = strtolower((string) pathinfo((string) $sourceDocument->myfile_key, PATHINFO_EXTENSION));
        if ($extension === '') {
            $extension = strtolower((string) ($sourceDocument->filetype ?? ''));
        }
        if (! in_array($extension, config('crm.email_upload_allowed_extensions', ['msg', 'eml']), true)) {
            return ['status' => 'skipped', 'message' => 'Source file is not an uploaded Outlook email'];
        }

        $s3Key = $this->resolveDocumentS3Key($sourceDocument, $email);
        if ($s3Key === null) {
            return ['status' => 'failed', 'message' => 'Could not resolve S3 key for source .msg'];
        }

        if (! Storage::disk('s3')->exists($s3Key)) {
            return ['status' => 'failed', 'message' => 'Source .msg not found on S3: ' . $s3Key];
        }

        if ($dryRun) {
            $action = ($replace && ! empty($email->pdf_doc_id)) ? 'replace PDF for' : 'backfill';

            return ['status' => 'dry_run', 'message' => 'Would ' . $action . ' from S3 key: ' . $s3Key];
        }

        $tempPath = null;

        try {
            $msgBytes = Storage::disk('s3')->get($s3Key);
            if ($msgBytes === null || $msgBytes === '') {
                return ['status' => 'failed', 'message' => 'Downloaded .msg file is empty'];
            }

            $originalFileName = $this->resolveOriginalFileName($sourceDocument);
            $sanitizedFileName = $this->sanitizeFilename($originalFileName);

            $tempDir = storage_path('app/temp/email_backfill');
            if (! is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $tempPath = $tempDir . '/' . uniqid('backfill_', true) . '_' . $sanitizedFileName;
            file_put_contents($tempPath, $msgBytes);

            $parsedData = $this->parseMsgWithPython($tempPath, $sanitizedFileName);
            if (! $parsedData || isset($parsedData['error']) || (isset($parsedData['success']) && ! $parsedData['success'])) {
                return [
                    'status' => 'failed',
                    'message' => $parsedData['error'] ?? 'Python service failed to parse email',
                ];
            }

            $existingPdfDocument = null;
            if ($replace && ! empty($email->pdf_doc_id)) {
                $existingPdfDocument = Document::find($email->pdf_doc_id);
            }

            $pdfDocumentId = $this->saveEmailPdfDocument(
                $parsedData,
                $originalFileName,
                $sourceDocument,
                $email,
                $existingPdfDocument
            );

            if ($pdfDocumentId === null) {
                return [
                    'status' => 'failed',
                    'message' => $parsedData['pdf_error'] ?? 'PDF was not generated',
                ];
            }

            $email->pdf_doc_id = $pdfDocumentId;
            if (! empty($parsedData['text_preview']) && empty($email->text_preview)) {
                $email->text_preview = $parsedData['text_preview'];
            }
            $email->save();

            Log::info('Email PDF backfill succeeded', [
                'email_log_id' => $email->id,
                'pdf_doc_id' => $pdfDocumentId,
                'replaced' => $replace && $existingPdfDocument !== null,
            ]);

            return [
                'status' => 'success',
                'message' => ($replace && $existingPdfDocument !== null)
                    ? 'PDF preview replaced'
                    : 'PDF preview created',
                'pdf_doc_id' => $pdfDocumentId,
            ];
        } catch (\Throwable $e) {
            Log::error('Email PDF backfill failed', [
                'email_log_id' => $email->id,
                'error' => $e->getMessage(),
            ]);

            return ['status' => 'failed', 'message' => $e->getMessage()];
        } finally {
            if ($tempPath !== null && is_file($tempPath)) {
                @unlink($tempPath);
            }
        }
    }

    public function isPythonServiceHealthy(): bool
    {
        try {
            $response = Http::timeout(10)->get($this->pythonServiceUrl . '/health');

            return $response->successful() && ($response->json('status') === 'healthy');
        } catch (\Throwable) {
            return false;
        }
    }

    private function resolveDocumentS3Key(Document $document, EmailLog $email): ?string
    {
        if (! empty($document->myfile_key)) {
            $client = Admin::select('client_id')->find($email->client_id);
            if ($client && ! empty($client->client_id)) {
                $clientRef = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', (string) $client->client_id);
                $docType = $document->doc_type ?? $email->conversion_type ?? 'conversion_email_fetch';
                $mailType = $document->mail_type ?? $email->mail_body_type ?? 'inbox';

                return $clientRef . '/' . $docType . '/' . $mailType . '/' . $document->myfile_key;
            }
        }

        if (! empty($document->myfile)) {
            $path = parse_url((string) $document->myfile, PHP_URL_PATH);
            if (is_string($path) && $path !== '') {
                return ltrim($path, '/');
            }
        }

        return null;
    }

    private function resolveOriginalFileName(Document $document): string
    {
        $baseName = trim((string) ($document->file_name ?? 'email'));
        $extension = strtolower((string) ($document->filetype ?? 'msg'));

        if ($extension !== '' && ! str_ends_with(strtolower($baseName), '.' . $extension)) {
            return $baseName . '.' . $extension;
        }

        if (! str_ends_with(strtolower($baseName), '.msg')) {
            return $baseName . '.msg';
        }

        return $baseName;
    }

    private function parseMsgWithPython(string $filePath, string $sanitizedFileName): ?array
    {
        $response = Http::timeout((int) config('services.python.timeout', 120))
            ->attach('file', file_get_contents($filePath), $sanitizedFileName)
            ->post($this->getUrlWithTimezone('/email/parse-render-pdf'), [
                'timezone' => config('app.timezone', 'Australia/Melbourne'),
            ]);

        if (! $response->successful()) {
            Log::error('Email PDF backfill Python service error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['error' => 'Python service returned status: ' . $response->status()];
        }

        try {
            return $response->json();
        } catch (\Throwable $e) {
            return ['error' => 'Invalid JSON from Python service'];
        }
    }

    private function saveEmailPdfDocument(
        array $parsedData,
        string $fileName,
        Document $sourceDocument,
        EmailLog $email,
        ?Document $existingPdfDocument = null
    ): ?int {
        if (empty($parsedData['pdf_base64'])) {
            return null;
        }

        $pdfBytes = base64_decode($parsedData['pdf_base64'], true);
        if ($pdfBytes === false || strlen($pdfBytes) === 0) {
            return null;
        }

        $client = Admin::select('client_id')->find($email->client_id);
        $sanitizedClientId = preg_replace(
            '/[^a-zA-Z0-9\-_\.]/',
            '_',
            (string) ($client->client_id ?? ('client_' . $email->client_id))
        );

        $docType = $sourceDocument->doc_type ?? $email->conversion_type ?? 'conversion_email_fetch';
        $mailType = $sourceDocument->mail_type ?? $email->mail_body_type ?? 'inbox';
        $uniqueFileName = (string) $sourceDocument->myfile_key;

        $pdfUniqueFileName = preg_replace('/\.msg$/i', '.pdf', $uniqueFileName);
        if ($pdfUniqueFileName === $uniqueFileName) {
            $pdfUniqueFileName = pathinfo($uniqueFileName, PATHINFO_FILENAME) . '.pdf';
        }

        if ($existingPdfDocument !== null) {
            $pdfFilePath = $this->resolveDocumentS3Key($existingPdfDocument, $email);
            if ($pdfFilePath === null) {
                $pdfFilePath = $sanitizedClientId . '/' . $docType . '/' . $mailType . '/' . $pdfUniqueFileName;
            }

            if (! Storage::disk('s3')->put($pdfFilePath, $pdfBytes)) {
                return null;
            }

            $existingPdfDocument->file_size = strlen($pdfBytes);
            if (empty($existingPdfDocument->myfile)) {
                $existingPdfDocument->myfile = Storage::disk('s3')->url($pdfFilePath);
            }
            if (empty($existingPdfDocument->myfile_key)) {
                $existingPdfDocument->myfile_key = $pdfUniqueFileName;
            }
            $existingPdfDocument->save();

            return (int) $existingPdfDocument->id;
        }

        $pdfFilePath = $sanitizedClientId . '/' . $docType . '/' . $mailType . '/' . $pdfUniqueFileName;

        if (! Storage::disk('s3')->put($pdfFilePath, $pdfBytes)) {
            return null;
        }

        $pdfDocument = new Document();
        $pdfDocument->file_name = pathinfo($fileName, PATHINFO_FILENAME);
        $pdfDocument->filetype = 'pdf';
        $pdfDocument->user_id = $email->user_id;
        $pdfDocument->myfile = Storage::disk('s3')->url($pdfFilePath);
        $pdfDocument->myfile_key = $pdfUniqueFileName;
        $pdfDocument->client_id = $email->client_id;
        $pdfDocument->type = $email->type;
        $pdfDocument->mail_type = $mailType;
        $pdfDocument->file_size = strlen($pdfBytes);
        $pdfDocument->doc_type = $docType;
        $pdfDocument->client_matter_id = $email->client_matter_id;
        $pdfDocument->save();

        return (int) $pdfDocument->id;
    }

    private function sanitizeFilename(string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
        $sanitizedName = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $nameWithoutExt);
        $sanitizedName = preg_replace('/_+/', '_', (string) $sanitizedName);
        $sanitizedName = trim((string) $sanitizedName, '_');

        if ($sanitizedName === '') {
            $sanitizedName = 'email_' . time();
        }

        $sanitizedFilename = $extension !== '' ? $sanitizedName . '.' . $extension : $sanitizedName;

        if (strlen($sanitizedFilename) > 255) {
            $maxNameLength = 255 - strlen($extension) - 1;
            $sanitizedFilename = substr($sanitizedName, 0, max(1, $maxNameLength)) . '.' . $extension;
        }

        return $sanitizedFilename;
    }
}
