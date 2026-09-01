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
 * with legacy fallback to public/legal_forms/... for older local files.
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
                    // Fall through to local legacy path.
                }
            } catch (\Throwable $e) {
                Log::warning('Legal form cloud exists() failed', [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return is_file($this->legacyPublicPath($path));
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

        $legacy = $this->legacyPublicPath($path);
        if (! is_file($legacy)) {
            return null;
        }

        $bytes = file_get_contents($legacy);

        return is_string($bytes) ? $bytes : null;
    }

    public function putUploadedFile(UploadedFile $file, string $relativePath): void
    {
        $path = $this->normalize($relativePath);
        if ($path === '') {
            throw new \InvalidArgumentException('Legal form storage path is empty.');
        }

        if ($this->usesCloud()) {
            $realPath = $file->getRealPath();
            if (! is_string($realPath) || $realPath === '' || ! is_file($realPath)) {
                $this->disk()->put($path, $file->get());

                return;
            }

            $stream = fopen($realPath, 'r');
            if ($stream === false) {
                $this->disk()->put($path, $file->get());

                return;
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

        $fullPath = $this->legacyPublicPath($path);
        $dir = dirname($fullPath);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new \RuntimeException('Could not create legal form upload directory.');
        }

        $file->move($dir, basename($fullPath));
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

        $legacy = $this->legacyPublicPath($path);
        if (is_file($legacy)) {
            @unlink($legacy);
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

        $legacy = $this->legacyPublicPath($path);
        if (is_file($legacy)) {
            return ['path' => $legacy, 'temporary' => false];
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

        $legacy = $this->legacyPublicPath($path);
        if (! is_file($legacy)) {
            abort(404, 'Uploaded form file not found.');
        }

        return $asAttachment
            ? response()->download($legacy, $filename, $headers)
            : response()->file($legacy, array_merge([
                'Content-Disposition' => 'inline; filename="'.str_replace('"', '\\"', $filename).'"',
            ], $headers));
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
     * If a legacy public/ file exists and cloud disk is enabled, copy it up so future
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

        $legacy = $this->legacyPublicPath($path);
        if (! is_file($legacy)) {
            return;
        }

        try {
            if ($this->disk()->exists($path)) {
                return;
            }
        } catch (\Throwable $e) {
            // Continue and attempt put.
        }

        $stream = fopen($legacy, 'r');
        if ($stream === false) {
            return;
        }

        try {
            $this->disk()->put($path, $stream);
        } catch (\Throwable $e) {
            Log::warning('Legal form legacy→cloud promote failed', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function legacyPublicPath(string $relativePath): string
    {
        return public_path($relativePath);
    }
}
