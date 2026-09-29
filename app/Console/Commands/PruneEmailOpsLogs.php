<?php

namespace App\Console\Commands;

use App\Logging\EmailOpsLogPruner;
use App\Logging\OutlookAddinLogger;
use Illuminate\Console\Command;

class PruneEmailOpsLogs extends Command
{
    protected $signature = 'logs:prune-email-ops {--days= : Keep this many days (default from config)}';

    protected $description = 'Delete inbox-sync, inbox-sync-run, email-upload-errors, and outlook-addin logs older than retention period';

    public function handle(): int
    {
        $daysOption = $this->option('days');
        $days = $daysOption !== null && $daysOption !== ''
            ? (int) $daysOption
            : null;

        $result = EmailOpsLogPruner::prune($days);
        $outlookResult = OutlookAddinLogger::prune($days ?? 10);

        $this->info(sprintf(
            'Email ops logs pruned. Deleted: %d, Kept: %d (retention %d days).',
            (int) $result['deleted'],
            (int) $result['kept'],
            max(1, $days ?? (int) config('logging.email_ops_retention_days', 7))
        ));

        $this->info(sprintf(
            'Outlook Add-in logs pruned. Deleted: %d, Kept: %d (retention %d days).',
            (int) $outlookResult['deleted'],
            (int) $outlookResult['kept'],
            max(1, $days ?? 10)
        ));

        return self::SUCCESS;
    }
}
