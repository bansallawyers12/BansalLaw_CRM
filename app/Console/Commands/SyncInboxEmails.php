<?php

namespace App\Console\Commands;

use App\Logging\InboxSyncLogger;
use App\Models\Email;
use App\Services\EmailSync\IncomingEmailSyncService;
use Illuminate\Console\Command;

class SyncInboxEmails extends Command
{
    protected $signature = 'emails:sync-inbox
                            {email? : Optional mailbox address to sync}
                            {--today : Fetch only today\'s incoming mail (app timezone)}
                            {--full : Reset UID tracking and re-fetch recent mail (backfill history)}';

    protected $description = 'Fetch incoming mail from Zoho IMAP for active CRM mailboxes';

    public function handle(IncomingEmailSyncService $syncService): int
    {
        IncomingEmailSyncService::configureSyncRuntime();

        if (! config('imap_sync.enabled', true)) {
            $this->warn('Inbox sync is disabled. Set MAIL_INBOX_SYNC_ENABLED=true to enable.');

            return self::SUCCESS;
        }

        $email = $this->argument('email');

        if ($this->option('full') && $this->option('today')) {
            $this->error('Use either --today or --full, not both.');

            return self::FAILURE;
        }

        if ($this->option('full')) {
            $query = Email::query()->where('status', true)->where('sync_enabled', true);
            if ($email) {
                $query->whereRaw('LOWER(email) = ?', [strtolower(trim((string) $email))]);
            }
            $query->update([
                'last_imap_uid' => null,
                'last_imap_uid_sent' => null,
            ]);
            $this->warn('UID tracking reset for selected mailbox(es). Running backfill sync...');
        }

        $since = null;
        if ($this->option('today')) {
            $timezone = (string) config('app.timezone', 'UTC');
            $since = now($timezone)->startOfDay();
            $this->info('Fetching mail since ' . $since->format('d/m/Y') . ' (' . $timezone . ') only.');
        }

        $this->info('Starting inbox sync' . ($email ? " for {$email}" : ' for all mailboxes') . '...');

        InboxSyncLogger::info('Scheduled inbox sync started', [
            'source' => 'cron',
            'email' => $email,
            'since' => $since?->format('c'),
            'full' => $this->option('full'),
            'today' => $this->option('today'),
        ]);

        $summary = $syncService->syncAll($email, $since, 'cron');

        foreach ($summary['mailboxes'] as $mailboxEmail => $result) {
            $imported = (int) ($result['imported'] ?? 0);
            $skipped = (int) ($result['skipped'] ?? 0);
            $failed = (int) ($result['failed'] ?? 0);
            $line = "{$mailboxEmail}: imported={$imported}, skipped={$skipped}, failed={$failed}";

            if (! empty($result['errors'])) {
                $this->error($line);
                foreach ($result['errors'] as $error) {
                    $this->line('  - ' . $error);
                }
            } else {
                $this->info($line);
            }
        }

        $this->line(sprintf(
            'Done. Imported: %d, Skipped: %d, Failed: %d',
            (int) ($summary['total_imported'] ?? 0),
            (int) ($summary['total_skipped'] ?? 0),
            (int) ($summary['total_failed'] ?? 0)
        ));

        $logContext = [
            'source' => 'cron',
            'email' => $email,
            'total_imported' => (int) ($summary['total_imported'] ?? 0),
            'total_skipped' => (int) ($summary['total_skipped'] ?? 0),
            'total_failed' => (int) ($summary['total_failed'] ?? 0),
            'mailboxes' => array_keys($summary['mailboxes'] ?? []),
        ];

        InboxSyncLogger::logRunSummary('cron', array_merge($summary, [
            'sync_range' => $this->option('full') ? 'full' : ($this->option('today') ? 'today' : 'incremental'),
        ]), $email);

        if ((int) ($summary['total_failed'] ?? 0) > 0) {
            InboxSyncLogger::warning('Scheduled inbox sync completed with failures', $logContext);
        } else {
            InboxSyncLogger::info('Scheduled inbox sync completed', $logContext);
        }

        return ((int) ($summary['total_failed'] ?? 0)) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
