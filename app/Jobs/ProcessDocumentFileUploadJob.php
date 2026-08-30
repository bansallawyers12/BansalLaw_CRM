<?php

namespace App\Jobs;

use App\Services\ClientDocumentFileUploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessDocumentFileUploadJob implements ShouldQueue
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
        public string $clientUniqueId,
        public string $clientFirstName,
    ) {
        $this->timeout = max(120, (int) config('crm.documents.file_upload_timeout_seconds', 600));
    }

    public function handle(ClientDocumentFileUploadService $service): void
    {
        $service->updateStatus($this->token, 'processing', 'Uploading file to storage…', $this->documentId, $this->userId, $this->originalFileName);

        try {
            $result = $service->processStoredFile(
                $this->tempPath,
                $this->documentId,
                $this->clientId,
                $this->userId,
                $this->doctype,
                $this->type,
                $this->doccategory,
                $this->originalFileName,
                $this->fileSize,
                $this->extension,
                $this->clientUniqueId,
                $this->clientFirstName
            );

            if ($result['success']) {
                $service->updateStatus(
                    $this->token,
                    'completed',
                    $result['message'] ?? 'File uploaded successfully.',
                    $this->documentId,
                    $this->userId,
                    $this->originalFileName
                );
            } else {
                $service->updateStatus(
                    $this->token,
                    'failed',
                    $result['message'] ?? 'File upload failed.',
                    $this->documentId,
                    $this->userId,
                    $this->originalFileName
                );
            }
        } catch (\Throwable $e) {
            Log::error('ProcessDocumentFileUploadJob failed', [
                'token' => $this->token,
                'document_id' => $this->documentId,
                'error' => $e->getMessage(),
            ]);
            $service->updateStatus(
                $this->token,
                'failed',
                'File upload failed: '.$e->getMessage(),
                $this->documentId,
                $this->userId,
                $this->originalFileName
            );
            throw $e;
        }
    }
}
