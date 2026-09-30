<?php

namespace App\Services;

use App\Models\ClientLegalForm;
use App\Models\Document;
use App\Models\EmailLogAttachment;
use Illuminate\Support\Facades\Log;

/**
 * Re-copy missing uploaded legal-form files from Documents / email attachments on S3.
 */
class LegalFormUploadRestoreService
{
    public function __construct(
        private LegalFormFileStorage $fileStorage,
    ) {}

    public function restoreForm(ClientLegalForm $form): bool
    {
        if (! $form->is_uploaded) {
            return false;
        }

        $target = $this->fileStorage->normalize($form->pdf_path)
            ?: $this->fileStorage->normalize($form->attachment_path);
        if ($target === '') {
            return false;
        }

        $this->fileStorage->promoteLegacyToCloud($target);
        if ($this->fileStorage->exists($target)) {
            return true;
        }

        $sourceKey = $this->resolveSourceKey($form);
        if ($sourceKey === null) {
            return false;
        }

        try {
            if (! $this->fileStorage->copyFromCloudKey($sourceKey, $target)) {
                return false;
            }

            $updates = [];
            if ($this->fileStorage->normalize($form->pdf_path) === '') {
                $updates['pdf_path'] = $target;
            }
            if ($this->fileStorage->normalize($form->attachment_path) === '') {
                $updates['attachment_path'] = $target;
            }
            if ($updates !== []) {
                $form->update($updates);
            }

            Log::info('Legal form upload restored from S3 source', [
                'form_id' => $form->id,
                'source' => $sourceKey,
                'target' => $target,
            ]);

            return $this->fileStorage->exists($target);
        } catch (\Throwable $e) {
            Log::error('Legal form upload restore failed', [
                'form_id' => $form->id,
                'source' => $sourceKey,
                'target' => $target,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function uploadFileAvailable(ClientLegalForm $form): bool
    {
        if (! $form->is_uploaded) {
            return true;
        }

        foreach (array_unique(array_filter([
            $this->fileStorage->normalize($form->pdf_path),
            $this->fileStorage->normalize($form->attachment_path),
        ])) as $path) {
            $this->fileStorage->promoteLegacyToCloud($path);
            if ($this->fileStorage->exists($path)) {
                return true;
            }
        }

        return false;
    }

    private function resolveSourceKey(ClientLegalForm $form): ?string
    {
        $original = trim((string) $form->attachment_original_name);
        $basename = $original !== '' ? $original : basename((string) ($form->pdf_path ?: $form->attachment_path));
        $stem = pathinfo($basename, PATHINFO_FILENAME);
        $wantExt = strtolower((string) pathinfo($basename, PATHINFO_EXTENSION));
        $stemKey = $this->normalizeMatchKey($stem);

        $docQuery = Document::query()->where('client_id', (int) $form->client_id);
        if (! empty($form->client_matter_id)) {
            $docQuery->where(function ($q) use ($form) {
                $q->where('client_matter_id', (int) $form->client_matter_id)
                    ->orWhereNull('client_matter_id');
            });
        }

        $docs = (clone $docQuery)
            ->where(function ($q) use ($basename, $stem) {
                $q->where('myfile_key', $basename)
                    ->orWhere('file_name', $stem)
                    ->orWhere('file_name', $basename)
                    ->orWhere('myfile_key', 'like', '%'.$stem.'%')
                    ->orWhere('file_name', 'like', '%'.$stem.'%');
            })
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        if ($docs->isEmpty() && $stemKey !== '') {
            $docs = $docQuery->orderByDesc('id')->limit(80)->get();
        }

        $bestKey = null;
        $bestScore = -1;
        foreach ($docs as $doc) {
            $key = $this->s3KeyFromDocument($doc);
            if ($key === null || ! $this->sourceObjectExists($key)) {
                continue;
            }
            $score = $this->matchScore($basename, $stem, $stemKey, $wantExt, (string) $doc->myfile_key, (string) $doc->file_name, $key);
            if (! empty($form->client_matter_id) && (int) $doc->client_matter_id === (int) $form->client_matter_id) {
                $score += 15;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestKey = $key;
            }
        }
        if ($bestKey !== null && $bestScore >= 50) {
            return $bestKey;
        }

        $attachments = EmailLogAttachment::query()
            ->whereHas('emailLog', function ($q) use ($form) {
                $q->where('client_id', (int) $form->client_id);
            })
            ->where(function ($q) use ($basename, $stem) {
                $q->where('filename', $basename)
                    ->orWhere('display_name', $basename)
                    ->orWhere('filename', 'like', '%'.$stem.'%')
                    ->orWhere('display_name', 'like', '%'.$stem.'%');
            })
            ->whereNotNull('s3_key')
            ->where('s3_key', '!=', '')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        foreach ($attachments as $att) {
            $key = ltrim(str_replace('\\', '/', (string) $att->s3_key), '/');
            if ($key === '' || ! $this->sourceObjectExists($key)) {
                continue;
            }
            $score = $this->matchScore(
                $basename,
                $stem,
                $stemKey,
                $wantExt,
                (string) $att->filename,
                (string) $att->display_name,
                $key
            );
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestKey = $key;
            }
        }

        return ($bestKey !== null && $bestScore >= 50) ? $bestKey : null;
    }

    private function sourceObjectExists(string $key): bool
    {
        try {
            return $this->fileStorage->disk()->exists($key);
        } catch (\Throwable) {
            return false;
        }
    }

    private function normalizeMatchKey(string $value): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/', '', $value) ?? '');
    }

    private function matchScore(
        string $basename,
        string $stem,
        string $stemKey,
        string $wantExt,
        string $keyName,
        string $fileName,
        string $s3Key
    ): int {
        $candidates = array_filter([$keyName, $fileName, basename($s3Key)]);
        $score = 0;
        foreach ($candidates as $candidate) {
            $candBase = basename((string) $candidate);
            $candStem = pathinfo($candBase, PATHINFO_FILENAME);
            $candExt = strtolower((string) pathinfo($candBase, PATHINFO_EXTENSION));
            $candKey = $this->normalizeMatchKey($candStem);

            if (strcasecmp($candBase, $basename) === 0) {
                $score = max($score, 100);
            } elseif ($stemKey !== '' && $candKey === $stemKey) {
                $score = max($score, $wantExt !== '' && $candExt === $wantExt ? 95 : 85);
            } elseif (strcasecmp($candStem, $stem) === 0) {
                $score = max($score, $wantExt !== '' && $candExt === $wantExt ? 90 : 40);
            } elseif ($stem !== '' && (str_contains(strtolower($candBase), strtolower($stem)) || str_contains(strtolower($candStem), strtolower($stem)))) {
                $score = max($score, $wantExt !== '' && $candExt === $wantExt ? 70 : 30);
            }
        }

        return $score;
    }

    private function s3KeyFromDocument(Document $document): ?string
    {
        $myfile = (string) ($document->myfile ?? '');
        if ($myfile !== '' && str_starts_with($myfile, 'http')) {
            $parsed = parse_url($myfile);
            if (! isset($parsed['path'])) {
                return null;
            }
            $path = ltrim(urldecode((string) $parsed['path']), '/');
            $bucket = (string) config('filesystems.disks.s3.bucket', '');
            if ($bucket !== '' && str_starts_with($path, $bucket.'/')) {
                $path = substr($path, strlen($bucket) + 1);
            }

            return $path !== '' ? $path : null;
        }

        return null;
    }
}
