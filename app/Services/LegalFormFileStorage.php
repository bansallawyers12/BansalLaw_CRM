<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToCheckFileExistence;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Legal form uploads use the shared documents disk (S3 when configured),
 * with a durable local mirror under storage/app/legal_forms/... and legacy
 * fallback to public/legal_forms/... for older local files.
 *
 * Storing only under public/ caused mass loss: untracked upload files disappear
 * on deploy/clean while DB rows remain.
 */
class LegalFormFileStorage
{
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

    public function exists(?string $relativePath): bool
    {
        $path = $this->normalize($relativePath);
        if ($path === '') {
            return false;
        }

        if ($this->usesCloud()) {
            try {
                if ($this->disk()->exists($path)) {
                    return true;
                }
            } catch (UnableToCheckFileExistence $e) {
                // HeadObject can fail while GetObject still works — try a cheap get size check below.
                try {
                    $size = $this->disk()->size($path);

                    return is_numeric($size) && (int) $size >= 0;
                } catch (\Throwable $ignored) {
                    // Fall through to local paths.
                }
            } catch (\Throwable $e) {
                Log::warning('Legal form cloud exists() failed', [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return is_file($this->durableLocalPath($path)) || is_file($this->legacyPublicPath($path));
    }

    public function get(?string $relativePath): ?string
    {
        $path = $this->normalize($relativePath);
        if ($path === '') {
            return null;
        }

        if ($this->usesCloud()) {
            try {
                if ($this->disk()->exists($path)) {
                    $bytes = $this->disk()->get($path);

                    return is_string($bytes) ? $bytes : null;
                }
            } catch (UnableToCheckFileExistence $e) {
                try {
                    $bytes = $this->disk()->get($path);

                    return is_string($bytes) ? $bytes : null;
                } catch (\Throwable $ignored) {
                    // Fall through to local.
                }
            } catch (\Throwable $e) {
                Log::warning('Legal form cloud get() failed', [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        foreach ([$this->durableLocalPath($path), $this->legacyPublicPath($path)] as $local) {
            if (! is_file($local)) {
                continue;
            }
            $bytes = file_get_contents($local);

            return is_string($bytes) ? $bytes : null;
        }

        return null;
    }

    public function putUploadedFile(UploadedFile $file, string $relativePath): void
    {
        $path = $this->normalize($relativePath);
        if ($path === '') {
            throw new \InvalidArgumentException('Legal form storage path is empty.');
        }

        $bytes = null;
        $realPath = $file->getRealPath();
        $useStream = is_string($realPath) && $realPath !== '' && is_file($realPath);

        if ($this->usesCloud()) {
            if ($useStream) {
                $stream = fopen($realPath, 'r');
                if ($stream === false) {
                    $bytes = $file->get();
                    $this->disk()->put($path, $bytes);
                } else {
                    try {
                        $this->disk()->put($path, $stream);
                    } finally {
                        if (is_resource($stream)) {
                            fclose($stream);
                        }
                    }
                }
            } else {
                $bytes = $file->get();
                $this->disk()->put($path, $bytes);
            }
        }

        // Always keep a durable local mirror outside public/ so deploy/clean cannot wipe uploads.
        if ($useStream && $bytes === null) {
            $this->writeLocalFile($path, $realPath, true);
        } else {
            if ($bytes === null) {
                $bytes = $file->get();
            }
            $this->writeLocalFile($path, $bytes, false);
        }

        if (! $this->usesCloud() && $useStream && is_file($realPath)) {
            // Local-only mode already wrote durable mirror; nothing else required.
            return;
        }
    }

    /**
     * Store raw bytes at the legal-form relative path (cloud + durable local).
     */
    public function putBytes(string $relativePath, string $bytes): void
    {
        $path = $this->normalize($relativePath);
        if ($path === '') {
            throw new \InvalidArgumentException('Legal form storage path is empty.');
        }
        if ($bytes === '') {
            throw new \InvalidArgumentException('Legal form file content is empty.');
        }

        if ($this->usesCloud()) {
            $this->disk()->put($path, $bytes);
        }

        $this->writeLocalFile($path, $bytes, false);
    }

    /**
     * Copy an existing S3 object into the legal-forms key (and durable local mirror).
     */
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
            Log::warning('Legal form copyFromCloudKey get failed', [
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
                Log::warning('Legal form cloud delete() failed', [
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

        foreach ([$this->durableLocalPath($path), $this->legacyPublicPath($path)] as $local) {
            if (is_file($local)) {
                return ['path' => $local, 'temporary' => false];
            }
        }

        $bytes = $this->get($path);
        if ($bytes === null || $bytes === '') {
            return null;
        }

        $ext = strtolower(ltrim($extensionHint !== '' ? $extensionHint : pathinfo($path, PATHINFO_EXTENSION), '.'));
        $suffix = $ext !== '' ? '.'.$ext : '';
        $tmp = tempnam(sys_get_temp_dir(), 'lfpreview_');
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
     */
    public function downloadResponse(string $relativePath, string $filename, array $headers = [], bool $asAttachment = true)
    {
        $path = $this->normalize($relativePath);

        if ($this->usesCloud()) {
            try {
                if ($this->disk()->exists($path)) {
                    return $asAttachment
                        ? $this->disk()->download($path, $filename, $headers)
                        : $this->disk()->response($path, $filename, $headers);
                }
            } catch (UnableToCheckFileExistence $e) {
                try {
                    return $asAttachment
                        ? $this->disk()->download($path, $filename, $headers)
                        : $this->disk()->response($path, $filename, $headers);
                } catch (\Throwable $ignored) {
                    // Fall through.
                }
            } catch (\Throwable $e) {
                Log::warning('Legal form cloud download failed', [
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

        abort(404, 'Uploaded form file not found.');
    }

    /**
     * @param  array<string, string>  $headers
     * @return \Illuminate\Http\Response|StreamedResponse
     */
    public function inlineResponse(string $relativePath, string $filename, array $headers = [])
    {
        return $this->downloadResponse($relativePath, $filename, $headers, false);
    }

    /**
     * If a legacy public/ or durable local file exists and cloud disk is enabled, copy it up so future
     * previews work from any environment.
     */
    public function promoteLegacyToCloud(?string $relativePath): void
    {
        if (! $this->usesCloud()) {
            return;
        }

        $path = $this->normalize($relativePath);
        if ($path === '') {
            return;
        }

        try {
            if ($this->disk()->exists($path)) {
                return;
            }
        } catch (\Throwable $e) {
            // Continue and attempt put.
        }

        $local = null;
        foreach ([$this->durableLocalPath($path), $this->legacyPublicPath($path)] as $candidate) {
            if (is_file($candidate)) {
                $local = $candidate;
                break;
            }
        }
        if ($local === null) {
            return;
        }

        $stream = fopen($local, 'r');
        if ($stream === false) {
            return;
        }

        try {
            $this->disk()->put($path, $stream);
        } catch (\Throwable $e) {
            Log::warning('Legal form local→cloud promote failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * @param  string|resource  $source  Absolute path (when $sourceIsPath) or raw bytes
     */
    private function writeLocalFile(string $relativePath, $source, bool $sourceIsPath): void
    {
        $fullPath = $this->durableLocalPath($relativePath);
        $dir = dirname($fullPath);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new \RuntimeException('Could not create legal form storage directory.');
        }

        if ($sourceIsPath) {
            if (! @copy((string) $source, $fullPath)) {
                throw new \RuntimeException('Could not write legal form local mirror.');
            }

            return;
        }

        if (@file_put_contents($fullPath, (string) $source) === false) {
            throw new \RuntimeException('Could not write legal form local mirror.');
        }
    }

    private function durableLocalPath(string $relativePath): string
    {
        return storage_path('app/'.$relativePath);
    }

    private function legacyPublicPath(string $relativePath): string
    {
        return public_path($relativePath);
    }
}
