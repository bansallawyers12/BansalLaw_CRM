<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToCheckFileExistence;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * S3-first uploads with a durable local mirror under storage/app/{relativePath}.
 * When S3 is down, files remain on the server; {@see promoteAllPendingToCloud()} pushes them to S3 later.
 */
class CrmDurableStorage
{
    public const MYFILE_LOCAL_PREFIX = 'local-storage:';

    /** @var list<string> */
    private const DEFAULT_PROMOTE_PREFIXES = [
        'legal_forms/',
        'note_attachments/',
        'office_receipt_uploads/',
        'conversion_email_fetch/',
        'email_uploads/',
        'checklists/',
    ];

    public function usesCloud(): bool
    {
        return (string) config('filesystems.disks.s3.driver', '') === 's3'
            && is_string(config('filesystems.disks.s3.bucket'))
            && config('filesystems.disks.s3.bucket') !== '';
    }

    public function disk(): Filesystem
    {
        return Storage::disk('s3');
    }

    public function normalize(?string $relativePath): string
    {
        $path = str_replace('\\', '/', trim((string) $relativePath));
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        $path = ltrim($path, '/');

        return $path;
    }

    public static function localMyfile(string $relativeKey): string
    {
        $path = str_replace('\\', '/', ltrim(trim($relativeKey), '/'));

        return self::MYFILE_LOCAL_PREFIX.$path;
    }

    public static function isLocalMyfile(?string $myfile): bool
    {
        return is_string($myfile) && str_starts_with($myfile, self::MYFILE_LOCAL_PREFIX);
    }

    public static function parseLocalMyfileKey(?string $myfile): ?string
    {
        if (! self::isLocalMyfile($myfile)) {
            return null;
        }

        $key = substr((string) $myfile, strlen(self::MYFILE_LOCAL_PREFIX));

        return $key !== '' ? $key : null;
    }

    /**
     * Value for {@see Document::$myfile}: S3 URL when the object is on S3, otherwise a local-storage marker.
     */
    public function myfileValue(string $relativePath): string
    {
        $path = $this->normalize($relativePath);
        if ($path === '') {
            throw new \InvalidArgumentException('Storage path is empty.');
        }

        if ($this->usesCloud() && $this->cloudExists($path)) {
            return $this->disk()->url($path);
        }

        return self::localMyfile($path);
    }

    public function localMirrorExists(?string $relativePath): bool
    {
        if ($relativePath === null || trim($relativePath) === '') {
            return false;
        }

        $path = $this->normalize($relativePath);
        if ($path === '') {
            return false;
        }

        return is_file($this->durableLocalPath($path)) || is_file($this->legacyPublicPath($path));
    }

    public function getLocalBytes(?string $relativePath): ?string
    {
        $path = $this->normalize($relativePath);
        if ($path === '') {
            return null;
        }

        foreach ([$this->durableLocalPath($path), $this->legacyPublicPath($path)] as $local) {
            if (! is_file($local)) {
                continue;
            }
            $bytes = file_get_contents($local);

            return is_string($bytes) && $bytes !== '' ? $bytes : null;
        }

        return null;
    }

    public function cloudExists(string $relativePath): bool
    {
        if (! $this->usesCloud()) {
            return false;
        }

        $path = $this->normalize($relativePath);
        if ($path === '') {
            return false;
        }

        try {
            if ($this->disk()->exists($path)) {
                return true;
            }
        } catch (UnableToCheckFileExistence $e) {
            try {
                $size = $this->disk()->size($path);

                return is_numeric($size) && (int) $size >= 0;
            } catch (\Throwable) {
                return false;
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }

    public function exists(?string $relativePath): bool
    {
        $path = $this->normalize($relativePath);
        if ($path === '') {
            return false;
        }

        if ($this->cloudExists($path)) {
            return true;
        }

        return is_file($this->durableLocalPath($path)) || is_file($this->legacyPublicPath($path));
    }

    public function get(?string $relativePath, ?string $myfileHint = null): ?string
    {
        $path = $this->normalize($relativePath);
        if ($path === '') {
            return null;
        }

        if (self::isLocalMyfile($myfileHint)) {
            return $this->getLocalBytes($path);
        }

        if (! $this->cloudExists($path)) {
            return $this->getLocalBytes($path);
        }

        if ($this->usesCloud()) {
            try {
                $bytes = $this->disk()->get($path);

                return is_string($bytes) && $bytes !== '' ? $bytes : $this->getLocalBytes($path);
            } catch (\Throwable $e) {
                Log::warning('CrmDurableStorage cloud get() failed', [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->getLocalBytes($path);
    }

    public function putUploadedFile(UploadedFile $file, string $relativePath): void
    {
        $path = $this->normalize($relativePath);
        if ($path === '') {
            throw new \InvalidArgumentException('Storage path is empty.');
        }

        $realPath = $file->getRealPath();
        $useStream = is_string($realPath) && $realPath !== '' && is_file($realPath);
        $bytes = null;

        if ($useStream) {
            $this->writeLocalFile($path, $realPath, true);
        } else {
            $bytes = $file->get();
            $this->writeLocalFile($path, $bytes, false);
        }

        $this->tryCloudPut($path, $useStream ? $realPath : null, $bytes);
    }

    public function putBytes(string $relativePath, string $bytes): void
    {
        $path = $this->normalize($relativePath);
        if ($path === '') {
            throw new \InvalidArgumentException('Storage path is empty.');
        }
        if ($bytes === '') {
            throw new \InvalidArgumentException('File content is empty.');
        }

        $this->writeLocalFile($path, $bytes, false);
        $this->tryCloudPut($path, null, $bytes);
    }

    /**
     * @param  resource|string|null  $streamOrPath
     */
    public function putStream(string $relativePath, $streamOrPath): void
    {
        $path = $this->normalize($relativePath);
        if ($path === '') {
            throw new \InvalidArgumentException('Storage path is empty.');
        }

        if (is_string($streamOrPath) && is_file($streamOrPath)) {
            $this->writeLocalFile($path, $streamOrPath, true);
            $this->tryCloudPut($path, $streamOrPath, null);

            return;
        }

        if (is_resource($streamOrPath)) {
            $bytes = stream_get_contents($streamOrPath);
            if (! is_string($bytes)) {
                throw new \RuntimeException('Could not read upload stream.');
            }
            $this->putBytes($path, $bytes);

            return;
        }

        throw new \InvalidArgumentException('Invalid stream source for putStream.');
    }

    public function copyFromCloudKey(string $sourceKey, string $relativePath): bool
    {
        $path = $this->normalize($relativePath);
        $source = $this->normalize($sourceKey);
        if ($path === '' || $source === '') {
            return false;
        }

        try {
            $bytes = $this->disk()->get($source);
        } catch (\Throwable $e) {
            Log::warning('CrmDurableStorage copyFromCloudKey get failed', [
                'source' => $source,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if (! is_string($bytes) || $bytes === '') {
            return false;
        }

        $this->putBytes($path, $bytes);

        return $this->exists($path);
    }

    public function delete(?string $relativePath): void
    {
        $path = $this->normalize($relativePath);
        if ($path === '') {
            return;
        }

        if ($this->usesCloud()) {
            try {
                $this->disk()->delete($path);
            } catch (\Throwable $e) {
                Log::warning('CrmDurableStorage cloud delete failed', [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        foreach ([$this->durableLocalPath($path), $this->legacyPublicPath($path)] as $local) {
            if (is_file($local)) {
                @unlink($local);
            }
        }
    }

    /**
     * Builds a normalized storage path by filtering out empty/null segments.
     * Prevents double-slash bugs (e.g., ['CL-001', '', 'file.pdf'] becomes 'CL-001/file.pdf').
     *
     * @param  list<string|null>  $segments
     */
    public function buildStoragePath(array $segments): string
    {
        $filtered = array_filter(
            array_map(fn ($s) => trim((string) $s, " \t\n\r\0\x0B/"), $segments),
            fn ($s) => $s !== ''
        );

        return implode('/', $filtered);
    }

    /**
     * Extracts and normalizes relative storage key from a myfile URL or local-storage string.
     */
    public function parseStorageKeyFromMyfile(?string $myfile): ?string
    {
        $myfile = trim((string) $myfile);
        if ($myfile === '') {
            return null;
        }

        if (self::isLocalMyfile($myfile)) {
            $key = self::parseLocalMyfileKey($myfile);

            return $key !== null ? $this->normalize($key) : null;
        }

        if (str_starts_with($myfile, 'http://') || str_starts_with($myfile, 'https://')) {
            $parsed = parse_url($myfile);
            if (! isset($parsed['path'])) {
                return null;
            }

            $path = urldecode((string) $parsed['path']);
            $path = ltrim(str_replace('\\', '/', $path), '/');

            // Strip S3 bucket name if path-style URL
            $bucket = (string) config('filesystems.disks.s3.bucket', '');
            if ($bucket !== '' && str_starts_with($path, $bucket . '/')) {
                $path = substr($path, strlen($bucket) + 1);
            } elseif (isset($parsed['host']) && preg_match('/^s3[.-]/i', $parsed['host'])) {
                $parts = explode('/', $path, 2);
                if (count($parts) === 2) {
                    $path = $parts[1];
                }
            }

            foreach (['storage/app/', 'storage/', 'app/'] as $prefix) {
                if (str_starts_with($path, $prefix)) {
                    $path = substr($path, strlen($prefix));
                    break;
                }
            }

            return $this->normalize($path);
        }

        return $this->normalize($myfile);
    }

    /**
     * Resolves all candidate storage keys for a document record to guarantee complete cleanup.
     * Returns an array of normalized relative keys with zero double-slashes.
     *
     * @param  \App\Models\Document|\stdClass|array|int|null  $document
     * @return list<string>
     */
    public function resolveCandidateKeysFromDocument($document, ?string $clientId = null): array
    {
        if (is_numeric($document)) {
            $document = DB::table('documents')->where('id', (int) $document)->first();
        }

        if (! $document) {
            return [];
        }

        $myfile = is_object($document) ? ($document->myfile ?? null) : ($document['myfile'] ?? null);
        $myfileKey = is_object($document) ? ($document->myfile_key ?? null) : ($document['myfile_key'] ?? null);
        $docType = is_object($document) ? ($document->doc_type ?? null) : ($document['doc_type'] ?? null);
        $folderName = is_object($document) ? ($document->folder_name ?? null) : ($document['folder_name'] ?? null);
        $mailType = is_object($document) ? ($document->mail_type ?? null) : ($document['mail_type'] ?? null);
        $docClientId = is_object($document) ? ($document->client_id ?? null) : ($document['client_id'] ?? null);

        $keys = [];

        // 1. Resolve key from myfile if present
        $myfileStr = trim((string) $myfile);
        if ($myfileStr !== '') {
            $parsedKey = $this->parseStorageKeyFromMyfile($myfileStr);
            if ($parsedKey !== null && $parsedKey !== '') {
                $keys[] = $parsedKey;
            }
        }

        // 2. Resolve client code if needed for metadata-based candidate keys
        $clientCode = trim((string) $clientId);
        if ($clientCode === '' && ! empty($docClientId)) {
            if (is_numeric($docClientId)) {
                $adminClientCode = DB::table('admins')
                    ->where('id', (int) $docClientId)
                    ->value('client_id');
                if (is_string($adminClientCode) && trim($adminClientCode) !== '') {
                    $clientCode = trim($adminClientCode);
                }
            } else {
                $clientCode = trim((string) $docClientId);
            }
        }

        $fileName = trim((string) ($myfileKey ?: basename($myfileStr)));
        $docTypeStr = trim((string) $docType);

        if ($clientCode !== '' && $fileName !== '') {
            // Check structured subfolder
            $subfolder = '';
            if ($docTypeStr === 'conversion_email_fetch' && ! empty($mailType)) {
                $subfolder = trim((string) $mailType);
            } elseif (($docTypeStr === 'migration' || $docTypeStr === 'personal') && ! empty($folderName)) {
                $subfolder = trim((string) $folderName);
            }

            // Key with subfolder if applicable (e.g. client/type/inbox/file.pdf or client/folder/file.pdf)
            if ($subfolder !== '') {
                if ($docTypeStr === 'migration') {
                    $keys[] = $this->buildStoragePath([$clientCode, $subfolder, $fileName]);
                } else {
                    $keys[] = $this->buildStoragePath([$clientCode, $docTypeStr, $subfolder, $fileName]);
                }
            }

            // Standard key: client/docType/fileName (filters empty docType to avoid double slashes)
            $keys[] = $this->buildStoragePath([$clientCode, $docTypeStr, $fileName]);

            // Flat key: client/fileName (in case doc_type was omitted or null)
            $keys[] = $this->buildStoragePath([$clientCode, $fileName]);
        }

        // 3. Add matter <-> visa aliases
        $additional = [];
        foreach ($keys as $k) {
            if (str_contains($k, '/matter/')) {
                $additional[] = str_replace('/matter/', '/visa/', $k);
            } elseif (str_contains($k, '/visa/')) {
                $additional[] = str_replace('/visa/', '/matter/', $k);
            }
        }

        $all = array_merge($keys, $additional);

        // Normalize and remove duplicates and empty values
        $unique = [];
        foreach ($all as $k) {
            $normalized = $this->normalize($k);
            if ($normalized !== '' && ! in_array($normalized, $unique, true)) {
                $unique[] = $normalized;
            }
        }

        return $unique;
    }

    /**
     * Resolves the primary storage key (S3 relative path) for a document record.
     * Handles S3 URLs, local-storage markers, legacy paths, and null/empty doc_type gracefully.
     *
     * @param  \App\Models\Document|\stdClass|array|int|null  $document
     */
    public function resolveKeyFromDocument($document, ?string $clientId = null): ?string
    {
        $candidates = $this->resolveCandidateKeysFromDocument($document, $clientId);

        return $candidates[0] ?? null;
    }

    /**
     * Deletes all candidate files in S3 and local mirrors associated with a document record.
     * Also checks and removes raw un-normalized paths if double-slash keys were stored on S3.
     *
     * @param  \App\Models\Document|\stdClass|array|int|null  $document
     */
    public function deleteDocumentFiles($document, ?string $clientId = null): void
    {
        $keys = $this->resolveCandidateKeysFromDocument($document, $clientId);

        // Also check if document has a raw myfile URL/key with double slashes on S3
        $rawPath = null;
        if (is_object($document) && isset($document->myfile)) {
            $rawMyfile = (string) $document->myfile;
            if (str_starts_with($rawMyfile, 'http')) {
                $p = parse_url($rawMyfile, PHP_URL_PATH);
                if ($p) {
                    $rawPath = ltrim(urldecode($p), '/');
                }
            }
        }

        foreach ($keys as $key) {
            $this->delete($key);
        }

        if ($rawPath !== null && str_contains($rawPath, '//') && $this->usesCloud()) {
            try {
                $this->disk()->delete($rawPath);
            } catch (\Throwable $e) {
                Log::warning('CrmDurableStorage raw double-slash delete attempt', [
                    'path' => $rawPath,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return array{path: string, temporary: bool}|null
     */
    public function resolveReadablePath(?string $relativePath, string $extensionHint = ''): ?array
    {
        $path = $this->normalize($relativePath);
        if ($path === '') {
            return null;
        }

        if (! $this->cloudExists($path)) {
            foreach ([$this->durableLocalPath($path), $this->legacyPublicPath($path)] as $local) {
                if (is_file($local)) {
                    return ['path' => $local, 'temporary' => false];
                }
            }
        }

        $bytes = $this->get($path);
        if ($bytes === null || $bytes === '') {
            return null;
        }

        $ext = strtolower(ltrim($extensionHint !== '' ? $extensionHint : pathinfo($path, PATHINFO_EXTENSION), '.'));
        $suffix = $ext !== '' ? '.'.$ext : '';
        $tmp = tempnam(sys_get_temp_dir(), 'crmstore_');
        if ($tmp === false) {
            return null;
        }

        $tmpPath = $tmp.$suffix;
        if ($suffix !== '' && ! @rename($tmp, $tmpPath)) {
            $tmpPath = $tmp;
        }

        if (@file_put_contents($tmpPath, $bytes) === false) {
            @unlink($tmpPath);

            return null;
        }

        return ['path' => $tmpPath, 'temporary' => true];
    }

    /**
     * @param  array<string, string>  $headers
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function downloadResponse(string $relativePath, string $filename, array $headers = [], bool $asAttachment = true, bool $forceLocal = false)
    {
        $path = $this->normalize($relativePath);

        if (! $forceLocal && $this->cloudExists($path) && $this->usesCloud()) {
            try {
                return $asAttachment
                    ? $this->disk()->download($path, $filename, $headers)
                    : $this->disk()->response($path, $filename, $headers);
            } catch (\Throwable $e) {
                Log::warning('CrmDurableStorage cloud download failed', [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        foreach ([$this->durableLocalPath($path), $this->legacyPublicPath($path)] as $local) {
            if (! is_file($local)) {
                continue;
            }

            return $asAttachment
                ? response()->download($local, $filename, $headers)
                : response()->file($local, array_merge([
                    'Content-Disposition' => 'inline; filename="'.str_replace('"', '\\"', $filename).'"',
                ], $headers));
        }

        abort(404, 'File not found.');
    }

    /**
     * Serves directly from the local mirror without making an S3 network call.
     *
     * @param  array<string, string>  $headers
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function localResponse(string $relativePath, string $filename, array $headers = [], bool $asAttachment = false)
    {
        return $this->downloadResponse($relativePath, $filename, $headers, $asAttachment, true);
    }

    public function promotePathToCloud(?string $relativePath): bool
    {
        if (! $this->usesCloud()) {
            return false;
        }

        $path = $this->normalize($relativePath);
        if ($path === '') {
            return false;
        }

        if ($this->cloudExists($path)) {
            app(CrmDurableStorageMyfileSync::class)->updateRecordsAfterCloudPromote($path);

            return true;
        }

        $local = null;
        foreach ([$this->durableLocalPath($path), $this->legacyPublicPath($path)] as $candidate) {
            if (is_file($candidate)) {
                $local = $candidate;
                break;
            }
        }
        if ($local === null) {
            return false;
        }

        $stream = fopen($local, 'r');
        if ($stream === false) {
            return false;
        }

        try {
            $this->disk()->put($path, $stream);
            $onCloud = $this->cloudExists($path);
            if ($onCloud) {
                app(CrmDurableStorageMyfileSync::class)->updateRecordsAfterCloudPromote($path);
            }

            return $onCloud;
        } catch (\Throwable $e) {
            Log::warning('CrmDurableStorage promote to cloud failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return false;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * @return array{promoted: int, skipped: int, failed: int, scanned: int}
     */
    public function promoteAllPendingToCloud(): array
    {
        $stats = ['promoted' => 0, 'skipped' => 0, 'failed' => 0, 'scanned' => 0];

        if (! $this->usesCloud()) {
            return $stats;
        }

        foreach ($this->promotePrefixes() as $prefix) {
            $this->promoteTreeUnderStorageApp($prefix, $stats);
        }

        if ((bool) config('crm.durable_storage.promote_scan_full_app', true)) {
            $this->promoteFullStorageApp($stats);
        }

        $this->promoteLegacyPublicTree('legal_forms/', $stats);

        return $stats;
    }

    /**
     * @return list<string>
     */
    public function promoteSkipPrefixes(): array
    {
        $configured = config('crm.durable_storage.promote_skip_prefixes');
        if (! is_array($configured)) {
            return [
                'document-uploads/',
                'framework/',
                'logs/',
                'temp/',
            ];
        }

        return array_values(array_map(function ($p) {
            $n = $this->normalize((string) $p);

            return str_ends_with($n, '/') ? $n : $n.'/';
        }, $configured));
    }

    /**
     * @param  array{promoted: int, skipped: int, failed: int, scanned: int}  $stats
     */
    private function promoteFullStorageApp(array &$stats): void
    {
        $root = storage_path('app');
        if (! is_dir($root)) {
            return;
        }

        $skip = $this->promoteSkipPrefixes();
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $absolute = $file->getPathname();
            $relative = $this->normalize(substr($absolute, strlen($root) + 1));

            foreach ($skip as $prefix) {
                if (str_starts_with($relative, $prefix)) {
                    continue 2;
                }
            }

            $stats['scanned']++;

            if ($this->cloudExists($relative)) {
                app(CrmDurableStorageMyfileSync::class)->updateRecordsAfterCloudPromote($relative);
                $stats['skipped']++;

                continue;
            }

            if ($this->promotePathToCloud($relative)) {
                $stats['promoted']++;
            } else {
                $stats['failed']++;
            }
        }
    }

    /**
     * @return list<string>
     */
    public function promotePrefixes(): array
    {
        $configured = config('crm.durable_storage.promote_prefixes');

        return is_array($configured) && $configured !== []
            ? array_values(array_filter(array_map(fn ($p) => $this->normalize((string) $p).'/', $configured)))
            : self::DEFAULT_PROMOTE_PREFIXES;
    }

    public function publicUrlForKey(string $relativePath): string
    {
        $path = $this->normalize($relativePath);

        if ($this->usesCloud() && $this->cloudExists($path)) {
            return $this->disk()->url($path);
        }

        return $this->durableLocalPath($path);
    }

    /**
     * @param  array{promoted: int, skipped: int, failed: int, scanned: int}  $stats
     */
    private function promoteTreeUnderStorageApp(string $prefix, array &$stats): void
    {
        $root = storage_path('app/'.trim($prefix, '/'));
        if (! is_dir($root)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $absolute = $file->getPathname();
            $relative = $this->normalize(substr($absolute, strlen(storage_path('app/'))));
            $stats['scanned']++;

            if ($this->cloudExists($relative)) {
                app(CrmDurableStorageMyfileSync::class)->updateRecordsAfterCloudPromote($relative);
                $stats['skipped']++;

                continue;
            }

            if ($this->promotePathToCloud($relative)) {
                $stats['promoted']++;
            } else {
                $stats['failed']++;
            }
        }
    }

    /**
     * @param  array{promoted: int, skipped: int, failed: int, scanned: int}  $stats
     */
    private function promoteLegacyPublicTree(string $prefix, array &$stats): void
    {
        $root = public_path(trim($prefix, '/'));
        if (! is_dir($root)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $absolute = $file->getPathname();
            $relative = $this->normalize(substr($absolute, strlen(public_path().DIRECTORY_SEPARATOR)));
            $stats['scanned']++;

            if ($this->cloudExists($relative)) {
                app(CrmDurableStorageMyfileSync::class)->updateRecordsAfterCloudPromote($relative);
                $stats['skipped']++;

                continue;
            }

            if ($this->promotePathToCloud($relative)) {
                $stats['promoted']++;
            } else {
                $stats['failed']++;
            }
        }
    }

    private function tryCloudPut(string $path, ?string $realPath, ?string $bytes): void
    {
        if (! $this->usesCloud()) {
            return;
        }

        try {
            if ($realPath !== null && is_file($realPath)) {
                $stream = fopen($realPath, 'r');
                if ($stream === false) {
                    throw new \RuntimeException('Could not open file for S3 upload.');
                }
                try {
                    $this->disk()->put($path, $stream);
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }

                return;
            }

            if ($bytes === null) {
                $bytes = $this->get($path) ?? '';
            }
            if ($bytes === '') {
                throw new \RuntimeException('Empty payload for S3 upload.');
            }
            $this->disk()->put($path, $bytes);
        } catch (\Throwable $e) {
            Log::warning('S3 upload failed; durable local copy retained', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  string|resource  $source
     */
    private function writeLocalFile(string $relativePath, $source, bool $sourceIsPath): void
    {
        $fullPath = $this->durableLocalPath($relativePath);
        $dir = dirname($fullPath);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new \RuntimeException('Could not create durable storage directory.');
        }

        if ($sourceIsPath) {
            if (! @copy((string) $source, $fullPath)) {
                throw new \RuntimeException('Could not write durable local mirror.');
            }

            return;
        }

        if (@file_put_contents($fullPath, (string) $source) === false) {
            throw new \RuntimeException('Could not write durable local mirror.');
        }
    }

    public function durableLocalPath(string $relativePath): string
    {
        return storage_path('app/'.$this->normalize($relativePath));
    }

    public function legacyPublicPath(string $relativePath): string
    {
        return public_path($this->normalize($relativePath));
    }
}
