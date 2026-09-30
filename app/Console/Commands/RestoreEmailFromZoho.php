<?php

namespace App\Console\Commands;

use App\Models\EmailLog;
use App\Services\EmailSync\EmailLogZohoRestoreService;
use Illuminate\Console\Command;

/**
 * Re-fetch one email_logs row from Zoho IMAP and refresh HTML body + Parsed email.pdf.
 * Safe for APP_ENV=local (and testing).
 */
class RestoreEmailFromZoho extends Command
{
    protected $signature = 'emails:restore-from-zoho
                            {email-log-id : email_logs.id to restore}
                            {--force : Required outside local/testing (e.g. production)}';

    protected $description = 'Re-fetch one synced email from Zoho and refresh body/PDF (local by default; production needs --force)';

    public function handle(EmailLogZohoRestoreService $restoreService): int
    {
        $isLocalLike = app()->environment(['local', 'testing']);
        if (! $isLocalLike && ! $this->option('force')) {
            $this->error('Refusing to run in ' . app()->environment() . ' without --force.');
            $this->line('Example: php83 artisan emails:restore-from-zoho {id} --force');
            $this->warn('Use the production email_logs.id (local ids may differ).');

            return self::FAILURE;
        }

        if (! $isLocalLike) {
            $this->warn('Running Zoho restore in ' . app()->environment() . ' with --force.');
            if (! $this->confirm('Re-fetch this email from Zoho and overwrite body/PDF on this server?', false)) {
                $this->info('Cancelled.');

                return self::SUCCESS;
            }
        }

        $emailLogId = (int) $this->argument('email-log-id');
        $email = EmailLog::query()->find($emailLogId);
        if (! $email) {
            $this->error("email_logs row {$emailLogId} not found.");

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Restoring email_logs#%d from %s UID %d…',
            $email->id,
            $email->mailbox_email ?? '?',
            (int) ($email->imap_uid ?? 0)
        ));

        $result = $restoreService->restore($email);
        if (! $result['success']) {
            $this->error($result['message']);

            return self::FAILURE;
        }

        if (! empty($result['pdf_doc_id'])) {
            $this->info('PDF preview refreshed (documents.id=' . $result['pdf_doc_id'] . ').');
        }
        $this->info($result['message'] . ' email_logs#' . $email->id);

        return self::SUCCESS;
    }
}
