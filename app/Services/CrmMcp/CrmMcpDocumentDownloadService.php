<?php

namespace App\Services\CrmMcp;

use App\Models\Admin;
use App\Models\Document;
use App\Models\Staff;
use App\Support\StaffClientVisibility;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToCheckFileExistence;

/**
 * Read-only document download for MCP: short-lived S3 URL when available.
 */
class CrmMcpDocumentDownloadService
{
    /**
     * @return array{document_id: int, filename: string, url: string, expires_in_minutes: int, mime_type: string}
     */
    public function temporaryDownload(Document $document, Staff $staff): array
    {
        $this->assertStaffMayAccessDocument($document, $staff);

        $s3Key = $this->resolveS3KeyForDocument($document);
        if ($s3Key === null) {
            throw new CrmMcpAccessException('File not found in storage.', 404);
        }

        $filename = $document->myfile_key
            ? basename((string) $document->myfile_key)
            : basename($s3Key);

        $minutes = max(1, min(15, (int) config('crm_mcp.download_url_minutes', 5)));
        $mime = $this->mimeTypeForS3Key($s3Key);
        $disk = Storage::disk('s3');

        if ((string) config('filesystems.disks.s3.driver', '') !== 's3') {
            throw new CrmMcpAccessException(
                'Document downloads require the S3 disk. Temporary URLs are not available on local storage.',
                503
            );
        }

        $url = $disk->temporaryUrl(
            $s3Key,
            now()->addMinutes($minutes),
            [
                'ResponseContentDisposition' => 'attachment; filename="'.str_replace('"', '\\"', $filename).'"',
                'ResponseContentType' => $mime,
            ]
        );

        Log::info('crm_mcp.document_download', [
            'staff_id' => $staff->id,
            'document_id' => $document->id,
            'client_id' => $document->client_id,
            'lead_id' => $document->lead_id,
        ]);

        return [
            'document_id' => (int) $document->id,
            'filename' => $filename,
            'url' => $url,
            'expires_in_minutes' => $minutes,
            'mime_type' => $mime,
        ];
    }

    private function assertStaffMayAccessDocument(Document $document, Staff $staff): void
    {
        $ownerId = (int) ($document->client_id ?: $document->lead_id);
        if ($ownerId <= 0 || ! StaffClientVisibility::canAccessClientOrLead($ownerId, $staff)) {
            throw new CrmMcpAccessException('Unauthorized access to this document.', 403);
        }
    }

    private function resolveS3KeyForDocument(Document $document): ?string
    {
        $candidates = [];

        $myfile = (string) ($document->myfile ?? '');
        if ($myfile !== '' && str_starts_with($myfile, 'http')) {
            $fromUrl = $this->normalizeS3KeyFromMyfileUrl($myfile);
            if ($fromUrl) {
                $candidates[] = $fromUrl;
            }
        } elseif ($myfile !== '' && str_contains($myfile, '/')) {
            // Stored as an object key rather than a full URL.
            $candidates[] = ltrim($myfile, '/');
        }

        $myfileKey = (string) ($document->myfile_key ?? '');
        if ($myfileKey !== '' && str_contains($myfileKey, '/')) {
            $candidates[] = ltrim($myfileKey, '/');
        }

        $legacy = $this->buildLegacyS3KeyForDocument($document);
        if ($legacy) {
            $candidates[] = $legacy;
        }

        foreach (array_values(array_unique(array_filter($candidates))) as $s3Key) {
            if ($this->s3ObjectExistsLenient($s3Key)) {
                return $s3Key;
            }

            if (str_contains($s3Key, '/matter/')) {
                $altKey = str_replace('/matter/', '/visa/', $s3Key);
                if ($this->s3ObjectExistsLenient($altKey)) {
                    return $altKey;
                }
            }
        }

        return null;
    }

    private function buildLegacyS3KeyForDocument(Document $document): ?string
    {
        $ownerAdminId = (int) ($document->client_id ?: $document->lead_id);
        if ($ownerAdminId <= 0) {
            return null;
        }

        $admin = Admin::query()->select('client_id')->where('id', $ownerAdminId)->first();
        if (! $admin || $admin->client_id === null || $admin->client_id === '') {
            return null;
        }

        $uniqueId = (string) $admin->client_id;
        $rawName = $document->myfile_key ?? $document->myfile;
        if ($rawName === null || $rawName === '') {
            return null;
        }

        // Legacy keys store only the leaf filename; full keys are tried separately.
        $fileName = basename((string) $rawName);
        if ($fileName === '') {
            return null;
        }

        $docType = (string) ($document->doc_type ?? '');
        if ($docType === 'migration') {
            return $uniqueId.'/'.$document->folder_name.'/'.$fileName;
        }
        if ($docType === 'conversion_email_fetch' && ! empty($document->mail_type)) {
            return $uniqueId.'/'.$docType.'/'.$document->mail_type.'/'.$fileName;
        }

        return $uniqueId.'/'.$docType.'/'.$fileName;
    }

    private function normalizeS3KeyFromMyfileUrl(string $myfile): ?string
    {
        $parsed = parse_url($myfile);
        if (! isset($parsed['path'])) {
            return null;
        }

        $path = ltrim(urldecode((string) $parsed['path']), '/');
        if ($path === '') {
            return null;
        }

        $bucket = (string) config('filesystems.disks.s3.bucket', '');
        if ($bucket !== '' && str_starts_with($path, $bucket.'/')) {
            $path = substr($path, strlen($bucket) + 1);
        }

        foreach (['storage/app/', 'storage/', 'app/'] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $path = substr($path, strlen($prefix));
                break;
            }
        }

        return $path !== '' ? $path : null;
    }

    private function s3ObjectExistsLenient(string $key): bool
    {
        try {
            return Storage::disk('s3')->exists($key);
        } catch (UnableToCheckFileExistence) {
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function mimeTypeForS3Key(string $key): string
    {
        $ext = strtolower((string) pathinfo($key, PATHINFO_EXTENSION));

        return match ($ext) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            default => 'application/octet-stream',
        };
    }
}
