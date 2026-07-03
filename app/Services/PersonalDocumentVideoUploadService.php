<?php

namespace App\Services;

use App\Jobs\ProcessPersonalDocumentVideoUploadJob;
use App\Models\Admin;
use App\Models\Document;
use App\Traits\ClientHelpers;
use App\Traits\LogsClientActivity;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PersonalDocumentVideoUploadService
{
    use ClientHelpers, LogsClientActivity;

    public const VIDEO_EXTENSIONS = ['mp4', 'webm', 'mov', 'm4v', 'avi', 'mkv'];

    private const CACHE_PREFIX = 'personal_video_upload:';

    private const CACHE_TTL_MINUTES = 120;

    public static function isVideoExtension(?string $extension): bool
    {
        return in_array(strtolower((string) $extension), self::VIDEO_EXTENSIONS, true);
    }

    public function usesDirectUpload(): bool
    {
        return (bool) config('crm.personal_video_upload.direct_upload', true);
    }

    public static function extendPhpLimits(): void
    {
        $seconds = max(300, (int) config('crm.personal_video_upload.execution_time_seconds', 1800));
        $inputSeconds = max(300, (int) config('crm.personal_video_upload.max_input_time_seconds', 1800));
        $socketTimeout = max(120, (int) config('crm.personal_video_upload.socket_timeout_seconds', 600));

        if (function_exists('set_time_limit')) {
            @set_time_limit($seconds);
        }

        @ini_set('max_execution_time', (string) $seconds);
        @ini_set('max_input_time', (string) $inputSeconds);
        @ini_set('default_socket_timeout', (string) $socketTimeout);
        @ignore_user_abort(true);
    }

    public static function maxVideoBytes(): int
    {
        return max(1, (int) config('crm.personal_video_upload.max_size_mb', 200)) * 1024 * 1024;
    }

    public function cacheKey(string $token): string
    {
        return self::CACHE_PREFIX . $token;
    }

    /**
     * @return \Illuminate\Contracts\Cache\Repository
     */
    private function cacheStore()
    {
        $store = config('crm.personal_video_upload.cache_store');

        return $store ? Cache::store($store) : Cache::store();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function initStatus(string $token, int $userId, int $clientId, array $extra = []): void
    {
        $this->cacheStore()->put($this->cacheKey($token), array_merge([
            'status' => 'queued',
            'message' => 'Video upload queued for processing.',
            'document_id' => null,
            'user_id' => $userId,
            'client_id' => $clientId,
        ], $extra), now()->addMinutes(self::CACHE_TTL_MINUTES));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getStatus(string $token): ?array
    {
        $data = $this->cacheStore()->get($this->cacheKey($token));

        return is_array($data) ? $data : null;
    }

    public function updateStatus(string $token, string $status, string $message, ?int $documentId = null): void
    {
        $existing = $this->getStatus($token) ?? [];
        $this->cacheStore()->put($this->cacheKey($token), array_merge($existing, [
            'status' => $status,
            'message' => $message,
            'document_id' => $documentId ?? ($existing['document_id'] ?? null),
        ]), now()->addMinutes(self::CACHE_TTL_MINUTES));
    }

    public function storeTempFile(UploadedFile $file, string $token): string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        return $file->storeAs('video-uploads', $token . '.' . $extension, 'local');
    }

    public function storeTempFileFromPath(string $absolutePath, string $token, string $extension): string
    {
        $relativePath = 'video-uploads/' . $token . '.' . strtolower($extension);
        $this->streamPathToDisk(Storage::disk('local'), $absolutePath, $relativePath);

        return $relativePath;
    }

    public function deleteTempFile(?string $relativePath): void
    {
        if ($relativePath && Storage::disk('local')->exists($relativePath)) {
            Storage::disk('local')->delete($relativePath);
        }
    }

    /**
     * Stream an uploaded video directly to S3 and finalize the document record.
     *
     * @return array{success: bool, message: string, document_id: int|null, filename: string|null, filetype: string|null, preview_url: string|null}
     */
    public function uploadVideoDirect(
        UploadedFile $file,
        Document $document,
        int $clientId,
        int $userId,
        string $doctype,
        string $type,
        string $doccategory
    ): array {
        $realPath = $file->getRealPath();
        if ($realPath === false || ! is_readable($realPath)) {
            return [
                'success' => false,
                'message' => 'Unable to read uploaded video file.',
                'document_id' => null,
                'filename' => null,
                'filetype' => null,
                'preview_url' => null,
            ];
        }

        return $this->processVideoFromLocalPath(
            $realPath,
            (int) $document->id,
            $clientId,
            $userId,
            $doctype,
            $type,
            $doccategory,
            $file->getClientOriginalName(),
            (int) $file->getSize(),
            strtolower($file->getClientOriginalExtension())
        );
    }

    /**
     * @return array{token: string, temp_path: string}
     */
    public function queueUpload(
        UploadedFile $file,
        Document $document,
        int $clientId,
        int $userId,
        string $doctype,
        string $type,
        string $doccategory
    ): array {
        $token = (string) Str::uuid();
        $tempPath = $this->storeTempFile($file, $token);

        $this->initStatus($token, $userId, $clientId, [
            'filename' => $file->getClientOriginalName(),
        ]);

        $this->dispatchProcessJob(
            $token,
            $tempPath,
            (int) $document->id,
            $clientId,
            $userId,
            $doctype,
            $type,
            $doccategory,
            $file->getClientOriginalName(),
            (int) $file->getSize(),
            strtolower($file->getClientOriginalExtension())
        );

        return [
            'token' => $token,
            'temp_path' => $tempPath,
        ];
    }

    /**
     * @return array{success: bool, message: string, document_id: int|null, filename: string|null, filetype: string|null, preview_url: string|null}
     */
    public function processStoredVideo(
        string $tempPath,
        int $documentId,
        int $clientId,
        int $userId,
        string $doctype,
        string $type,
        string $doccategory,
        string $originalFileName,
        int $fileSize,
        string $extension
    ): array {
        if (! Storage::disk('local')->exists($tempPath)) {
            return [
                'success' => false,
                'message' => 'Temporary video file is missing.',
                'document_id' => null,
                'filename' => null,
                'filetype' => null,
                'preview_url' => null,
            ];
        }

        return $this->processVideoFromLocalPath(
            Storage::disk('local')->path($tempPath),
            $documentId,
            $clientId,
            $userId,
            $doctype,
            $type,
            $doccategory,
            $originalFileName,
            $fileSize,
            strtolower($extension)
        );
    }

    /**
     * @return array{success: bool, message: string, document_id: int|null, filename: string|null, filetype: string|null, preview_url: string|null}
     */
    private function processVideoFromLocalPath(
        string $localPath,
        int $documentId,
        int $clientId,
        int $userId,
        string $doctype,
        string $type,
        string $doccategory,
        string $originalFileName,
        int $fileSize,
        string $extension
    ): array {
        self::extendPhpLimits();

        $obj = Document::find($documentId);
        if (! $obj) {
            return $this->videoFailure('Document record not found.');
        }

        if ((int) $obj->client_id !== $clientId) {
            return $this->videoFailure('Document does not belong to this client.');
        }

        if (empty($obj->checklist)) {
            return $this->videoFailure('Document checklist not found.');
        }

        $adminInfo = Admin::select('client_id', 'first_name')->where('id', $clientId)->first();
        $clientUniqueId = ! empty($adminInfo) ? $adminInfo->client_id : '';
        $clientFirstName = ! empty($adminInfo)
            ? preg_replace('/[^a-zA-Z0-9_\-]/', '_', $adminInfo->first_name)
            : 'client';

        $obj->refresh();
        $checklistName = $obj->checklist;
        $timestamp = time();
        $name = $clientFirstName . '_' . $checklistName . '_' . $timestamp . '.' . $extension;
        $filePath = $clientUniqueId . '/' . $doctype . '/' . $name;

        $disk = Storage::disk('s3');
        $this->streamPathToDisk($disk, $localPath, $filePath);

        $obj->refresh();
        $finalChecklistName = $obj->checklist;
        if (! empty($finalChecklistName) && $finalChecklistName !== $checklistName) {
            $checklistName = $finalChecklistName;
            $name = $clientFirstName . '_' . $checklistName . '_' . $timestamp . '.' . $extension;
            $newFilePath = $clientUniqueId . '/' . $doctype . '/' . $name;
            if ($newFilePath !== $filePath) {
                try {
                    $disk->copy($filePath, $newFilePath);
                    $disk->delete($filePath);
                    $filePath = $newFilePath;
                } catch (\Throwable $e) {
                    Log::error('Failed to move video after checklist change', [
                        'document_id' => $documentId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $obj->file_name = $clientFirstName . '_' . $checklistName . '_' . $timestamp;
        $obj->filetype = $extension;
        $obj->user_id = $userId;
        $obj->myfile = $disk->url($filePath);
        $obj->myfile_key = $name;
        $obj->type = $type;
        $obj->file_size = $fileSize;
        $obj->doc_type = $doctype;
        $saved = $obj->save();

        if (! $saved) {
            return $this->videoFailure('Failed to save video document record.');
        }

        if ($type === 'client') {
            $matterRef = $this->getMatterReference($clientId);
            $subject = ! empty($matterRef)
                ? "uploaded {$checklistName} - {$matterRef}"
                : "uploaded {$checklistName}";
            $description = "<p>Uploaded video in '{$doccategory}' folder</p>";

            $this->logClientActivity(
                $clientId,
                $subject,
                $description,
                'document'
            );
        }

        Log::info('Personal document video uploaded successfully', [
            'document_id' => $obj->id,
            'original_filename' => $originalFileName,
            'client_id' => $clientId,
            'user_id' => $userId,
            'direct_upload' => $this->usesDirectUpload(),
        ]);

        return [
            'success' => true,
            'message' => 'Video uploaded successfully.',
            'document_id' => (int) $obj->id,
            'filename' => $name,
            'filetype' => $extension,
            'preview_url' => url('/documents/preview/' . $obj->id),
        ];
    }

    /**
     * @return array{success: bool, message: string, document_id: null, filename: null, filetype: null, preview_url: null}
     */
    private function videoFailure(string $message): array
    {
        return [
            'success' => false,
            'message' => $message,
            'document_id' => null,
            'filename' => null,
            'filetype' => null,
            'preview_url' => null,
        ];
    }

    private function streamPathToDisk(Filesystem $disk, string $sourcePath, string $destPath): void
    {
        $stream = fopen($sourcePath, 'rb');
        if ($stream === false) {
            throw new \RuntimeException('Unable to read video file.');
        }

        try {
            $disk->writeStream($destPath, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * @return array{token: string, temp_path: string}
     */
    public function queueFromStoredTemp(
        string $tempPath,
        Document $document,
        int $clientId,
        int $userId,
        string $doctype,
        string $type,
        string $doccategory,
        string $originalFileName,
        int $fileSize,
        string $extension
    ): array {
        $token = (string) Str::uuid();

        $this->initStatus($token, $userId, $clientId, [
            'filename' => $originalFileName,
        ]);

        $this->dispatchProcessJob(
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
            strtolower($extension)
        );

        return [
            'token' => $token,
            'temp_path' => $tempPath,
        ];
    }

    private function dispatchProcessJob(
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
        string $extension
    ): void {
        $connection = (string) config('crm.personal_video_upload.queue_connection', 'sync');
        $afterResponse = (bool) config('crm.personal_video_upload.after_response', true);

        $pending = ProcessPersonalDocumentVideoUploadJob::dispatch(
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
            $extension
        );

        $pending->onConnection($connection);

        if ($afterResponse && $connection === 'sync') {
            $pending->afterResponse();
        }
    }
}
