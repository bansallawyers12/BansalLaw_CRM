<?php

namespace App\Console\Commands;

use App\Logging\EmailOpsLogPruner;
use Illuminate\Console\Command;

class PruneEmailOpsLogs extends Command
{
    protected $signature = 'logs:prune-email-ops {--days= : Keep this many days (default from config)}';

    protected $description = 'Delete inbox-sync, inbox-sync-run, and email-upload-errors logs older than 7 days';

    public function handle(): int
    {
        $daysOption = $this->option('days');
        $days = $daysOption !== null && $daysOption !== ''
            ? (int) $daysOption
            : null;

        $result = EmailOpsLogPruner::prune($days);

        $this->info(sprintf(
            'Email ops logs pruned. Deleted: %d, Kept: %d (retention %d days).',
            (int) $result['deleted'],
            (int) $result['kept'],
            max(1, $days ?? (int) config('logging.email_ops_retention_days', 7))
        ));

        return self::SUCCESS;
    }
}
