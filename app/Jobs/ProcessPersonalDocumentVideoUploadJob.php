<?php

namespace App\Jobs;

use App\Services\PersonalDocumentVideoUploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPersonalDocumentVideoUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 2;

    public function __construct(
        public string $token,
        public string $tempPath,
        public int $documentId,
        public int $clientId,
        public int $userId,
        public string $doctype,
        public string $type,
        public string $doccategory,
        public string $originalFileName,
        public int $fileSize,
        public string $extension,
    ) {}

    public function handle(PersonalDocumentVideoUploadService $service): void
    {
        $service->updateStatus($this->token, 'processing', 'Processing video upload…');

        try {
            $result = $service->processStoredVideo(
                $this->tempPath,
                $this->documentId,
                $this->clientId,
                $this->userId,
                $this->doctype,
                $this->type,
                $this->doccategory,
                $this->originalFileName,
                $this->fileSize,
                $this->extension
            );

            if ($result['success']) {
                $service->updateStatus(
                    $this->token,
                    'completed',
                    $result['message'],
                    $result['document_id']
                );

                return;
            }

            $service->updateStatus($this->token, 'failed', $result['message']);
        } catch (\Throwable $e) {
            Log::error('Queued personal document video upload failed', [
                'token' => $this->token,
                'document_id' => $this->documentId,
                'client_id' => $this->clientId,
                'error' => $e->getMessage(),
            ]);

            $service->updateStatus(
                $this->token,
                'failed',
                'Video upload failed. Please try again.'
            );

            throw $e;
        } finally {
            $service->deleteTempFile($this->tempPath);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $service = app(PersonalDocumentVideoUploadService::class);
        $service->updateStatus(
            $this->token,
            'failed',
            'Video upload failed. Please try again.'
        );
        $service->deleteTempFile($this->tempPath);
    }
}
