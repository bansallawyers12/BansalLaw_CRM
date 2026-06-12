<?php

namespace App\Console\Commands;

use App\Models\EmailLog;
use App\Services\EmailPdfBackfillService;
use Illuminate\Console\Command;

class BackfillEmailPdfPreviews extends Command
{
    protected $signature = 'emails:backfill-pdf
                            {--limit=50 : Maximum number of emails to process}
                            {--client-id= : Only process emails for this client_id}
                            {--email-log-id= : Process a single email_logs row}
                            {--dry-run : Show what would be processed without making changes}
                            {--replace : Regenerate PDFs even when pdf_doc_id already exists}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Generate PDF previews for uploaded .msg emails missing pdf_doc_id';

    public function handle(EmailPdfBackfillService $backfillService): int
    {
        if (! $backfillService->isPythonServiceHealthy()) {
            $this->error('Python email service is not healthy. Start it before running backfill.');

            return self::FAILURE;
        }

        $replace = (bool) $this->option('replace');

        $query = EmailLog::query()
            ->whereNotNull('uploaded_doc_id')
            ->where('conversion_type', 'conversion_email_fetch')
            ->orderBy('id');

        if (! $replace) {
            $query->whereNull('pdf_doc_id');
        }

        if ($this->option('email-log-id')) {
            $query->where('id', (int) $this->option('email-log-id'));
        }

        if ($this->option('client-id')) {
            $query->where('client_id', (int) $this->option('client-id'));
        }

        $limit = max(1, (int) $this->option('limit'));
        $emails = $query->limit($limit)->get();

        if ($emails->isEmpty()) {
            $this->info($replace
                ? 'No uploaded emails matched for PDF regeneration.'
                : 'No uploaded emails need PDF backfill.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        $this->info(sprintf(
            'Found %d email(s) to %s (limit %d%s).',
            $emails->count(),
            $dryRun ? 'inspect' : 'process',
            $limit,
            $replace ? ', replace mode' : ''
        ));

        if (! $dryRun && ! $this->option('force')) {
            if (! $this->confirm('Continue with PDF backfill?')) {
                $this->info('Cancelled.');

                return self::SUCCESS;
            }
        }

        $counts = [
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'dry_run' => 0,
        ];

        foreach ($emails as $email) {
            $freshQuery = EmailLog::query()->where('id', $email->id);
            if (! $replace) {
                $freshQuery->whereNull('pdf_doc_id');
            }
            $fresh = $freshQuery->first();

            if (! $fresh) {
                $counts['skipped']++;
                $this->line("  [skip] #{$email->id} — PDF already linked");
                continue;
            }

            $result = $backfillService->backfillEmailLog($fresh, $dryRun, $replace);
            $status = $result['status'];
            $counts[$status] = ($counts[$status] ?? 0) + 1;

            $subject = $fresh->subject ?: '(no subject)';
            $this->line(sprintf(
                '  [%s] #%d — %s — %s',
                $status,
                $fresh->id,
                $subject,
                $result['message']
            ));
        }

        $this->newLine();
        $this->table(
            ['Result', 'Count'],
            collect($counts)->map(fn ($count, $key) => [$key, $count])->values()->all()
        );

        return ($counts['failed'] ?? 0) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
