<?php

namespace App\Services;

use App\Jobs\ProcessDocumentFileUploadJob;
use App\Models\Document;
use App\Support\DocumentLabel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Non-video document file uploads to S3 (stream put + optional queued bulk).
 */
class ClientDocumentFileUploadService
{
    private const CACHE_TTL_MINUTES = 60;

    /**
     * Stream an uploaded file to the documents disk (avoids loading whole file into memory).
     */
    public function putUploadedFile(string $path, UploadedFile $file): void
    {
        $realPath = $file->getRealPath();
        if (! is_string($realPath) || $realPath === '' || ! is_file($realPath)) {
            Storage::disk('s3')->put($path, $file->get());

            return;
        }

        $stream = fopen($realPath, 'r');
        if ($stream === false) {
            Storage::disk('s3')->put($path, $file->get());

            return;
        }

        try {
            Storage::disk('s3')->put($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function shouldQueueBulkNonVideo(int $fileCount): bool
    {
        if (! (bool) config('crm.documents.bulk_queue_non_video', true)) {
            return false;
        }

        $threshold = max(1, (int) config('crm.documents.bulk_queue_threshold', 1));

        return $fileCount >= $threshold;
    }

    /**
     * Stage a non-video file and queue S3 finalize (afterResponse by default).
     *
     * @return array{token: string, temp_path: string}
     */
    public function queueBulkFile(
        UploadedFile $file,
        Document $document,
        int $clientId,
        int $userId,
        string $doctype,
        string $type,
        string $doccategory,
        string $clientUniqueId,
        string $clientFirstName
    ): array {
        $token = (string) Str::uuid();
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $originalFileName = (string) $file->getClientOriginalName();
        $fileSize = (int) $file->getSize();

        $tempPath = $file->storeAs('document-uploads', $token.'.'.$extension, 'local');

        $this->updateStatus($token, 'queued', 'File queued for upload…', (int) $document->id, $userId, $originalFileName);

        $this->dispatchJob(
            $token,
            $tempPath,
            (int) $document->id,
            $clientId,
            $userId,
            $doctype,
            $type,
            $doccategory,
            $originalFileName,
            $fileSize,
            $extension,
            $clientUniqueId,
            $clientFirstName
        );

        return [
            'token' => $token,
            'temp_path' => $tempPath,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getStatus(string $token): ?array
    {
        $data = Cache::get($this->cacheKey($token));

        return is_array($data) ? $data : null;
    }

    public function updateStatus(
        string $token,
        string $status,
        string $message,
        ?int $documentId = null,
        ?int $userId = null,
        ?string $filename = null
    ): void {
        $existing = $this->getStatus($token) ?? [];
        Cache::put($this->cacheKey($token), array_merge($existing, array_filter([
            'status' => $status,
            'message' => $message,
            'document_id' => $documentId ?? ($existing['document_id'] ?? null),
            'user_id' => $userId ?? ($existing['user_id'] ?? null),
            'filename' => $filename ?? ($existing['filename'] ?? null),
            'updated_at' => now()->toIso8601String(),
        ], static fn ($v) => $v !== null)), now()->addMinutes(self::CACHE_TTL_MINUTES));
    }

    public function processStoredFile(
        string $tempPath,
        int $documentId,
        int $clientId,
        int $userId,
        string $doctype,
        string $type,
        string $doccategory,
        string $originalFileName,
        int $fileSize,
        string $extension,
        string $clientUniqueId,
        string $clientFirstName
    ): array {
        $document = Document::query()->find($documentId);
        if (! $document) {
            return ['success' => false, 'message' => 'Document checklist not found.'];
        }

        $checklistName = DocumentLabel::normalize((string) $document->checklist);
        $timestamp = time();
        $uniqueId = $timestamp.'_'.mt_rand(1000, 9999);
        $name = DocumentLabel::buildStoredFileName($clientFirstName, $checklistName, (string) $uniqueId, $extension);
        $filePath = $clientUniqueId.'/'.$doctype.'/'.$name;

        $absolute = Storage::disk('local')->path($tempPath);
        if (! is_file($absolute)) {
            return ['success' => false, 'message' => 'Staged upload file missing.'];
        }

        $stream = fopen($absolute, 'r');
        if ($stream === false) {
            return ['success' => false, 'message' => 'Could not open staged upload file.'];
        }

        try {
            Storage::disk('s3')->put($filePath, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $document->refresh();
        $finalChecklistName = DocumentLabel::normalize((string) $document->checklist);
        if ($finalChecklistName !== '' && $finalChecklistName !== $checklistName) {
            $checklistName = $finalChecklistName;
            $name = DocumentLabel::buildStoredFileName($clientFirstName, $checklistName, (string) $uniqueId, $extension);
            $newPath = $clientUniqueId.'/'.$doctype.'/'.$name;
            if ($newPath !== $filePath) {
                try {
                    Storage::disk('s3')->copy($filePath, $newPath);
                    Storage::disk('s3')->delete($filePath);
                    $filePath = $newPath;
                } catch (\Throwable $e) {
                    Log::warning('Document bulk upload rename after checklist change failed', [
                        'document_id' => $documentId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $fileUrl = Storage::disk('s3')->url($filePath);
        $document->file_name = DocumentLabel::buildStoredFileName($clientFirstName, $checklistName, (string) $uniqueId);
        $document->filetype = $extension;
        $document->user_id = $userId;
        $document->myfile = $fileUrl;
        $document->myfile_key = $name;
        $document->file_size = $fileSize;
        $document->save();

        Storage::disk('local')->delete($tempPath);

        return [
            'success' => true,
            'message' => 'File uploaded successfully.',
            'document_id' => $document->id,
            'filename' => $originalFileName,
        ];
    }

    private function dispatchJob(
        string $token,
        string $tempPath,
        int $documentId,
        int $clientId,
        int $userId,
        string $doctype,
        string $type,
        string $doccategory,
        string $originalFileName,
        int $fileSize,
        string $extension,
        string $clientUniqueId,
        string $clientFirstName
    ): void {
        $connection = (string) config('crm.documents.file_upload_queue_connection', 'sync');
        $afterResponse = (bool) config('crm.documents.file_upload_after_response', true);

        $pending = ProcessDocumentFileUploadJob::dispatch(
            $token,
            $tempPath,
            $documentId,
            $clientId,
            $userId,
            $doctype,
            $type,
            $doccategory,
            $originalFileName,
            $fileSize,
            $extension,
            $clientUniqueId,
            $clientFirstName
        );

        $pending->onConnection($connection);

        if ($afterResponse && $connection === 'sync') {
            $pending->afterResponse();
        }
    }

    private function cacheKey(string $token): string
    {
        return 'document_file_upload:'.$token;
    }
}
