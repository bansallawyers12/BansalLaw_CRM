<?php

namespace App\Console\Commands;

use App\Models\ClientLegalForm;
use App\Models\Document;
use App\Models\EmailLogAttachment;
use App\Services\LegalFormFileStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Rebuild missing uploaded legal-form files from matching Documents / email attachments on S3.
 *
 * Older uploads lived only under public/legal_forms/... and were wiped while DB rows remained.
 */
class RestoreLegalFormUploadsFromDocuments extends Command
{
    protected $signature = 'legal-forms:restore-uploads
                            {--dry-run : Show matches without writing}
                            {--client= : Limit to one client_id}
                            {--form= : Limit to one client_legal_forms.id}';

    protected $description = 'Restore missing uploaded legal form files from Documents / email attachments on S3';

    public function handle(LegalFormFileStorage $fileStorage): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $clientFilter = $this->option('client') !== null ? (int) $this->option('client') : null;
        $formFilter = $this->option('form') !== null ? (int) $this->option('form') : null;

        $query = ClientLegalForm::query()->where('is_uploaded', true)->orderBy('id');
        if ($clientFilter) {
            $query->where('client_id', $clientFilter);
        }
        if ($formFilter) {
            $query->where('id', $formFilter);
        }

        $forms = $query->get();
        $restored = 0;
        $skippedOk = 0;
        $unmatched = 0;
        $failed = 0;

        $this->info(($dryRun ? '[DRY-RUN] ' : '').'Checking '.$forms->count().' uploaded legal form(s)…');

        foreach ($forms as $form) {
            $target = $fileStorage->normalize($form->pdf_path)
                ?: $fileStorage->normalize($form->attachment_path);
            if ($target === '') {
                $this->warn("Form {$form->id}: no stored path — skip");
                $unmatched++;
                continue;
            }

            if ($fileStorage->exists($target)) {
                $skippedOk++;
                continue;
            }

            $sourceKey = $this->resolveSourceKey($form);
            if ($sourceKey === null) {
                $this->warn("Form {$form->id} (client {$form->client_id}): no matching Document/attachment for ".($form->attachment_original_name ?: basename($target)));
                $unmatched++;
                continue;
            }

            $this->line("Form {$form->id}: {$sourceKey} → {$target}");

            if ($dryRun) {
                $restored++;
                continue;
            }

            try {
                if (! $fileStorage->copyFromCloudKey($sourceKey, $target)) {
                    $this->error("Form {$form->id}: copy failed");
                    $failed++;
                    continue;
                }

                // Keep pdf_path / attachment_path aligned when one side was empty.
                $updates = [];
                if ($fileStorage->normalize($form->pdf_path) === '') {
                    $updates['pdf_path'] = $target;
                }
                if ($fileStorage->normalize($form->attachment_path) === '') {
                    $updates['attachment_path'] = $target;
                }
                if ($updates !== []) {
                    $form->update($updates);
                }

                $restored++;
            } catch (\Throwable $e) {
                Log::error('legal-forms:restore-uploads failed', [
                    'form_id' => $form->id,
                    'source' => $sourceKey,
                    'target' => $target,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Form {$form->id}: ".$e->getMessage());
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Already present: {$skippedOk}");
        $this->info(($dryRun ? 'Would restore' : 'Restored').": {$restored}");
        $this->info("Unmatched: {$unmatched}");
        $this->info("Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolveSourceKey(ClientLegalForm $form): ?string
    {
        $original = trim((string) $form->attachment_original_name);
        $basename = $original !== '' ? $original : basename((string) ($form->pdf_path ?: $form->attachment_path));
        $stem = pathinfo($basename, PATHINFO_FILENAME);
        $wantExt = strtolower((string) pathinfo($basename, PATHINFO_EXTENSION));

        $docs = Document::query()
            ->where('client_id', (int) $form->client_id)
            ->where(function ($q) use ($basename, $stem) {
                $q->where('myfile_key', $basename)
                    ->orWhere('file_name', $stem)
                    ->orWhere('file_name', $basename)
                    ->orWhere('myfile_key', 'like', '%'.$stem.'%')
                    ->orWhere('file_name', 'like', '%'.$stem.'%');
            })
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $bestKey = null;
        $bestScore = -1;
        foreach ($docs as $doc) {
            $key = $this->s3KeyFromDocument($doc);
            if ($key === null) {
                continue;
            }
            $score = $this->matchScore($basename, $stem, $wantExt, (string) $doc->myfile_key, (string) $doc->file_name, $key);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestKey = $key;
            }
        }
        if ($bestKey !== null && $bestScore >= 50) {
            return $bestKey;
        }

        // Fallback: email attachments with the same display/original filename.
        $attachments = EmailLogAttachment::query()
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
            if ($key === '') {
                continue;
            }
            $score = $this->matchScore(
                $basename,
                $stem,
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

    private function matchScore(string $basename, string $stem, string $wantExt, string $keyName, string $fileName, string $s3Key): int
    {
        $candidates = array_filter([$keyName, $fileName, basename($s3Key)]);
        $score = 0;
        foreach ($candidates as $candidate) {
            $candBase = basename((string) $candidate);
            $candStem = pathinfo($candBase, PATHINFO_FILENAME);
            $candExt = strtolower((string) pathinfo($candBase, PATHINFO_EXTENSION));

            if (strcasecmp($candBase, $basename) === 0) {
                $score = max($score, 100);
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
