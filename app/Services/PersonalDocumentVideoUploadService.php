<?php

namespace App\Services;

use App\Jobs\ProcessPersonalDocumentVideoUploadJob;
use App\Models\Admin;
use App\Models\Document;
use App\Traits\ClientHelpers;
use App\Traits\LogsClientActivity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
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

    public function cacheKey(string $token): string
    {
        return self::CACHE_PREFIX . $token;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function initStatus(string $token, int $userId, int $clientId, array $extra = []): void
    {
        Cache::put($this->cacheKey($token), array_merge([
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
        $data = Cache::get($this->cacheKey($token));

        return is_array($data) ? $data : null;
    }

    public function updateStatus(string $token, string $status, string $message, ?int $documentId = null): void
    {
        $existing = $this->getStatus($token) ?? [];
        Cache::put($this->cacheKey($token), array_merge($existing, [
            'status' => $status,
            'message' => $message,
            'document_id' => $documentId ?? ($existing['document_id'] ?? null),
        ]), now()->addMinutes(self::CACHE_TTL_MINUTES));
    }

    public function storeTempFile(UploadedFile $file, string $token): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $relativePath = 'video-uploads/' . $token . '.' . $extension;
        Storage::disk('local')->put($relativePath, file_get_contents($file->getRealPath()));

        return $relativePath;
    }

    public function storeTempFileFromPath(string $absolutePath, string $token, string $extension): string
    {
        $relativePath = 'video-uploads/' . $token . '.' . strtolower($extension);
        Storage::disk('local')->put($relativePath, file_get_contents($absolutePath));

        return $relativePath;
    }

    public function deleteTempFile(?string $relativePath): void
    {
        if ($relativePath && Storage::disk('local')->exists($relativePath)) {
            Storage::disk('local')->delete($relativePath);
        }
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

        ProcessPersonalDocumentVideoUploadJob::dispatch(
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
        $obj = Document::find($documentId);
        if (! $obj) {
            return [
                'success' => false,
                'message' => 'Document record not found.',
                'document_id' => null,
                'filename' => null,
                'filetype' => null,
                'preview_url' => null,
            ];
        }

        if ((int) $obj->client_id !== $clientId) {
            return [
                'success' => false,
                'message' => 'Document does not belong to this client.',
                'document_id' => null,
                'filename' => null,
                'filetype' => null,
                'preview_url' => null,
            ];
        }

        if (empty($obj->checklist)) {
            return [
                'success' => false,
                'message' => 'Document checklist not found.',
                'document_id' => null,
                'filename' => null,
                'filetype' => null,
                'preview_url' => null,
            ];
        }

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
        $disk->put($filePath, Storage::disk('local')->get($tempPath));

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
                    Log::error('Failed to move queued video after checklist change', [
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
            return [
                'success' => false,
                'message' => 'Failed to save video document record.',
                'document_id' => null,
                'filename' => null,
                'filetype' => null,
                'preview_url' => null,
            ];
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

        Log::info('Queued personal document video uploaded successfully', [
            'document_id' => $obj->id,
            'original_filename' => $originalFileName,
            'client_id' => $clientId,
            'user_id' => $userId,
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

        ProcessPersonalDocumentVideoUploadJob::dispatch(
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
}
