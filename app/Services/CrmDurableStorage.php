<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
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

    public function localMirrorExists(string $relativePath): bool
    {
        $path = $this->normalize($relativePath);

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
                if ($this->disk()->exists($path)) {
                    $this->disk()->delete($path);
                }
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
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\Response
     */
    public function downloadResponse(string $relativePath, string $filename, array $headers = [], bool $asAttachment = true)
    {
        $path = $this->normalize($relativePath);

        if ($this->cloudExists($path) && $this->usesCloud()) {
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
