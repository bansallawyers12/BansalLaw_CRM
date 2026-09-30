<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;

/**
 * Legal form uploads — delegates to {@see CrmDurableStorage} (S3 + durable local mirror).
 */
class LegalFormFileStorage
{
    public function __construct(
        private CrmDurableStorage $storage
    ) {}

    public function usesCloud(): bool
    {
        return $this->storage->usesCloud();
    }

    public function disk(): Filesystem
    {
        return $this->storage->disk();
    }

    public function normalize(?string $relativePath): string
    {
        return $this->storage->normalize($relativePath);
    }

    public function exists(?string $relativePath): bool
    {
        return $this->storage->exists($relativePath);
    }

    public function get(?string $relativePath): ?string
    {
        return $this->storage->get($relativePath);
    }

    public function putUploadedFile(UploadedFile $file, string $relativePath): void
    {
        $this->storage->putUploadedFile($file, $relativePath);
    }

    public function putBytes(string $relativePath, string $bytes): void
    {
        $this->storage->putBytes($relativePath, $bytes);
    }

    public function copyFromCloudKey(string $sourceKey, string $relativePath): bool
    {
        return $this->storage->copyFromCloudKey($sourceKey, $relativePath);
    }

    public function delete(?string $relativePath): void
    {
        $this->storage->delete($relativePath);
    }

    /**
     * @return array{path: string, temporary: bool}|null
     */
    public function resolveReadablePath(?string $relativePath, string $extensionHint = ''): ?array
    {
        return $this->storage->resolveReadablePath($relativePath, $extensionHint);
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function downloadResponse(string $relativePath, string $filename, array $headers = [], bool $asAttachment = true)
    {
        return $this->storage->downloadResponse($relativePath, $filename, $headers, $asAttachment);
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function inlineResponse(string $relativePath, string $filename, array $headers = [])
    {
        return $this->downloadResponse($relativePath, $filename, $headers, false);
    }

    public function promoteLegacyToCloud(?string $relativePath): void
    {
        $this->storage->promotePathToCloud($relativePath);
    }
}
