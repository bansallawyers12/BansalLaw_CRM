<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Support\Facades\Log;

/**
 * After a file lands on S3, switch {@see Document::$myfile} from local-storage markers to the S3 URL.
 */
class CrmDurableStorageMyfileSync
{
    public function updateRecordsAfterCloudPromote(string $relativePath): void
    {
        $storage = app(CrmDurableStorage::class);
        if (! $storage->usesCloud() || ! $storage->cloudExists($relativePath)) {
            return;
        }

        $key = $storage->normalize($relativePath);
        if ($key === '') {
            return;
        }

        try {
            $cloudUrl = $storage->disk()->url($key);
        } catch (\Throwable $e) {
            Log::warning('CrmDurableStorageMyfileSync could not build S3 URL', [
                'path' => $key,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $localMyfile = CrmDurableStorage::localMyfile($key);

        $updated = Document::query()
            ->where('myfile', $localMyfile)
            ->update(['myfile' => $cloudUrl]);

        if ($updated === 0) {
            Document::query()
                ->where('myfile', 'like', CrmDurableStorage::MYFILE_LOCAL_PREFIX.'%')
                ->orderBy('id')
                ->chunkById(100, function ($documents) use ($storage, $cloudUrl, $key) {
                    foreach ($documents as $document) {
                        $parsed = CrmDurableStorage::parseLocalMyfileKey((string) $document->myfile);
                        if ($parsed !== null && $storage->normalize($parsed) === $key) {
                            $document->myfile = $cloudUrl;
                            $document->saveQuietly();
                        }
                    }
                });
        }
    }
}
