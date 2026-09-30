<?php

namespace App\Console\Commands;

use App\Models\ClientLegalForm;
use App\Services\LegalFormUploadRestoreService;
use Illuminate\Console\Command;

/**
 * Rebuild missing uploaded legal-form files from matching Documents / email attachments on S3.
 */
class RestoreLegalFormUploadsFromDocuments extends Command
{
    protected $signature = 'legal-forms:restore-uploads
                            {--dry-run : Show what would be restored without writing to S3}
                            {--client= : Limit to one client_id}
                            {--form= : Limit to one client_legal_forms.id}
                            {--force : Skip confirmation when not using --dry-run}';

    protected $description = 'Restore missing uploaded legal form files from Documents / email attachments on S3';

    public function handle(LegalFormUploadRestoreService $restoreService): int
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

        if (! $dryRun && ! $this->option('force')) {
            if (! $this->confirm('Copy missing legal form files from matching S3 documents/attachments?', true)) {
                $this->info('Cancelled.');

                return self::SUCCESS;
            }
        }

        $this->info(($dryRun ? '[DRY-RUN] ' : '').'Checking '.$forms->count().' uploaded legal form(s)…');

        foreach ($forms as $form) {
            if ($restoreService->uploadFileAvailable($form)) {
                $skippedOk++;
                continue;
            }

            if ($dryRun) {
                $this->line("Form {$form->id}: would attempt restore for ".($form->attachment_original_name ?: 'upload'));
                $restored++;
                continue;
            }

            if ($restoreService->restoreForm($form)) {
                $this->line("Form {$form->id}: restored");
                $restored++;
            } else {
                $this->warn("Form {$form->id}: no matching file on S3 — re-upload required");
                $unmatched++;
            }
        }

        $this->newLine();
        $this->info("Already present: {$skippedOk}");
        $this->info(($dryRun ? 'Would restore' : 'Restored').": {$restored}");
        $this->info("Unmatched: {$unmatched}");
        $this->info("Failed: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
