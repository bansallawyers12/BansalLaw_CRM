<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Document;
use App\Support\DocumentLabel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Matter-scoped client documents for compose-email attachment picker and sendmail.
 */
class ComposeMatterDocumentService
{
    /**
     * Documents uploaded for a client or lead that can be attached in compose email.
     *
     * @return array<int, array{id: int, checklist: string, file_name: string, preview_url: string}>
     */
    public function listForMatter(int $clientId, int $clientMatterId = 0): array
    {
        if ($clientId <= 0 || ! Schema::hasTable('documents')) {
            return [];
        }

        foreach (['client_id', 'doc_type'] as $column) {
            if (! Schema::hasColumn('documents', $column)) {
                return [];
            }
        }

        try {
            $query = Document::query()
                ->where(function ($q) use ($clientId) {
                    $q->where('client_id', $clientId);
                    if (Schema::hasColumn('documents', 'lead_id')) {
                        $q->orWhere('lead_id', $clientId);
                    }
                })
                ->whereIn('doc_type', ['matter', 'visa'])
                ->where(function ($q) {
                    $q->where(function ($inner) {
                        $inner->whereNotNull('myfile_key')->where('myfile_key', '!=', '');
                    })->orWhere(function ($inner) {
                        $inner->whereNotNull('myfile')->where('myfile', '!=', '');
                    });
                });

            if (Schema::hasColumn('documents', 'type')) {
                $query->where(function ($q) {
                    $q->whereIn('type', ['client', 'lead'])->orWhereNull('type');
                });
            }

            if ($clientMatterId > 0 && Schema::hasColumn('documents', 'client_matter_id')) {
                $query->where('client_matter_id', $clientMatterId);
            }

            if (Schema::hasColumn('documents', 'not_used_doc')) {
                $query->whereNull('not_used_doc');
            }

            $rows = $query->orderBy('checklist')->orderBy('created_at')->get();
        } catch (\Throwable $e) {
            Log::warning('ComposeMatterDocumentService listForMatter failed', [
                'client_id' => $clientId,
                'client_matter_id' => $clientMatterId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        return $rows->map(function (Document $doc) {
            return [
                'id' => (int) $doc->id,
                'checklist' => DocumentLabel::forDisplay($doc->checklist),
                'file_name' => $this->displayFileName($doc),
                'preview_url' => url('/documents/preview/' . $doc->id),
            ];
        })->values()->all();
    }

    /**
     * Resolve a document to a local temp file path for email attachment.
     *
     * @return array{path: string, name: string}|null
     */
    public function attachmentForEmail(Document $document): ?array
    {
        $displayName = $this->displayFileName($document);
        $safeName = preg_replace('/[^a-zA-Z0-9._\-\s]/', '_', $displayName) ?: 'document';

        $myfile = (string) ($document->myfile ?? '');
        if ($myfile !== '' && is_file($myfile)) {
            return ['path' => $myfile, 'name' => $safeName];
        }

        $content = $this->readFileContent($document);
        if ($content === null || $content === '') {
            return null;
        }

        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'compose_doc_' . $document->id . '_' . uniqid('', true);
        if (! @mkdir($dir, 0700, true) && ! is_dir($dir)) {
            return null;
        }

        $tempPath = $dir . DIRECTORY_SEPARATOR . basename($safeName);
        if (@file_put_contents($tempPath, $content) === false) {
            return null;
        }

        return ['path' => $tempPath, 'name' => $safeName];
    }

    public function displayFileName(Document $document): string
    {
        if (! empty($document->myfile_key)) {
            return basename((string) $document->myfile_key);
        }

        $name = trim((string) ($document->file_name ?? '') . '.' . (string) ($document->filetype ?? ''), '.');

        if ($name !== '') {
            return $name;
        }

        $checklist = DocumentLabel::forDisplay($document->checklist);

        return $checklist !== '' ? $checklist : 'document';
    }

    protected function readFileContent(Document $document): ?string
    {
        $myfile = (string) ($document->myfile ?? '');

        if ($myfile !== '' && preg_match('#^https?://#i', $myfile)) {
            $content = $this->fetchAllowedUrl($myfile, (int) $document->id);
            if ($content !== null) {
                return $content;
            }
        }

        foreach ($this->candidateS3Keys($document) as $key) {
            try {
                $content = Storage::disk('s3')->get($key);
                if (is_string($content) && $content !== '') {
                    return $content;
                }
            } catch (\Throwable $e) {
                Log::debug('ComposeMatterDocumentService S3 read failed', [
                    'document_id' => $document->id,
                    'key' => $key,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($myfile !== '') {
            $baseName = basename($myfile);
            $candidatePublic = realpath(public_path('img/documents/' . $baseName));
            $allowedPublic = realpath(public_path('img/documents'));
            if ($candidatePublic && $allowedPublic && str_starts_with($candidatePublic, $allowedPublic)) {
                $content = @file_get_contents($candidatePublic);

                return ($content !== false && $content !== '') ? $content : null;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    protected function candidateS3Keys(Document $document): array
    {
        $keys = [];
        $legacy = $this->buildLegacyS3Key($document);
        if ($legacy !== null) {
            $keys[] = $legacy;
        }

        $myfileKey = (string) ($document->myfile_key ?? '');
        if ($myfileKey !== '' && str_contains($myfileKey, '/')) {
            $keys[] = ltrim($myfileKey, '/');
        }

        $urlKey = $this->normalizeS3KeyFromUrl((string) ($document->myfile ?? ''));
        if ($urlKey !== null) {
            $keys[] = $urlKey;
        }

        $expanded = [];
        foreach (array_unique(array_filter($keys)) as $key) {
            $expanded[] = $key;
            if (str_contains($key, '/matter/')) {
                $expanded[] = str_replace('/matter/', '/visa/', $key);
            } elseif (str_contains($key, '/visa/')) {
                $expanded[] = str_replace('/visa/', '/matter/', $key);
            }
        }

        return array_values(array_unique(array_filter($expanded)));
    }

    protected function buildLegacyS3Key(Document $document): ?string
    {
        $admin = Admin::query()->select('client_id')->where('id', $document->client_id)->first();
        if (! $admin || $admin->client_id === null || $admin->client_id === '') {
            return null;
        }

        $uniqueId = (string) $admin->client_id;
        $fileName = $document->myfile_key ?? $document->myfile;
        if ($fileName === null || $fileName === '') {
            return null;
        }

        if (str_contains((string) $fileName, '/')) {
            return ltrim((string) $fileName, '/');
        }

        $docType = (string) ($document->doc_type ?? '');
        if ($docType === 'migration') {
            return $uniqueId . '/' . $document->folder_name . '/' . $fileName;
        }

        return $uniqueId . '/' . $docType . '/' . $fileName;
    }

    protected function normalizeS3KeyFromUrl(string $myfile): ?string
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
        if ($bucket !== '' && str_starts_with($path, $bucket . '/')) {
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

    protected function fetchAllowedUrl(string $fileUrl, int $documentId): ?string
    {
        $parsedScheme = strtolower((string) parse_url($fileUrl, PHP_URL_SCHEME));
        $parsedHost = strtolower((string) parse_url($fileUrl, PHP_URL_HOST));

        if (! in_array($parsedScheme, ['http', 'https'], true) || $parsedHost === '') {
            return null;
        }

        $ip = gethostbyname($parsedHost);
        $isPublicIp = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        $isAllowedDomain = (
            str_ends_with($parsedHost, 'amazonaws.com') ||
            str_ends_with($parsedHost, 'bansallawyers.com.au')
        );

        if (! $isAllowedDomain || ! $isPublicIp) {
            Log::warning('Compose email SSRF attempt blocked', [
                'doc_id' => $documentId,
                'url' => $fileUrl,
                'host' => $parsedHost,
                'ip' => $ip,
            ]);

            return null;
        }

        $content = @file_get_contents($fileUrl);

        return ($content !== false && $content !== '') ? $content : null;
    }
}
