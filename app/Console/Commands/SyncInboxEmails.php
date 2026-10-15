<?php

namespace App\Console\Commands;

use App\Logging\InboxSyncLogger;
use App\Models\Email;
use App\Services\EmailSync\InboxSyncMasterControl;
use App\Services\EmailSync\InboxSyncSummaryPresenter;
use App\Services\EmailSync\IncomingEmailSyncService;
use Illuminate\Console\Command;

class SyncInboxEmails extends Command
{
    protected $signature = 'emails:sync-inbox
                            {email? : Optional mailbox address to sync}
                            {--today : Fetch only mail since today start time (default 9:00 AM, app timezone)}
                            {--full : Reset UID tracking and re-fetch recent mail (backfill history)}';

    protected $description = 'Fetch incoming mail from Zoho IMAP for active CRM mailboxes';

    public function handle(IncomingEmailSyncService $syncService): int
    {
        IncomingEmailSyncService::configureSyncRuntime();

        if (! InboxSyncMasterControl::isEnabled()) {
            $this->warn(InboxSyncMasterControl::disabledMessage());

            return self::SUCCESS;
        }

        $email = $this->argument('email');

        if ($email) {
            $mailbox = IncomingEmailSyncService::findSyncableMailbox((string) $email);
            if ($mailbox === null) {
                $this->warn(
                    "Skipping {$email}: automatic inbox sync requires an active mailbox with a Zoho password."
                );

                return self::SUCCESS;
            }
        }

        if ($this->option('full') && $this->option('today')) {
            $this->error('Use either --today or --full, not both.');

            return self::FAILURE;
        }

        if ($this->option('full')) {
            $query = Email::query()->where('status', true)->where('sync_enabled', true);
            IncomingEmailSyncService::applyMailboxHasZohoPasswordScope($query);
            IncomingEmailSyncService::applyExcludedMailboxesScope($query);
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
            $since = IncomingEmailSyncService::resolveTodaySyncSince();
            $this->info(
                'Fetching mail since '.$since->format('d/m/Y g:i a')
                .' ('.$timezone.') only — messages before '
                .IncomingEmailSyncService::formatTodaySyncStartForDisplay()
                .' today are excluded.'
            );
        }

        $this->info('Starting inbox sync'.($email ? " for {$email}" : ' for all mailboxes').'...');

        InboxSyncLogger::info('Scheduled inbox sync started', [
            'source' => 'cron',
            'email' => $email,
            'since' => $since?->format('c'),
            'full' => $this->option('full'),
            'today' => $this->option('today'),
        ]);

        $summary = $syncService->syncAll($email, $since, 'cron');

        foreach ($summary['mailboxes'] as $mailboxEmail => $result) {
            $line = InboxSyncSummaryPresenter::formatMailboxConsoleLine((string) $mailboxEmail, $result);

            if (! empty($result['errors'])) {
                $this->error($line);
                foreach ($result['errors'] as $error) {
                    $this->line('  - '.$error);
                }
            } else {
                $this->info($line);
            }
        }

        $this->line(InboxSyncSummaryPresenter::formatTotalsConsoleLine(
            (int) ($summary['total_imported'] ?? 0),
            InboxSyncSummaryPresenter::totalAlreadyInCrm($summary),
            (int) ($summary['total_failed'] ?? 0)
        ));

        $logContext = [
            'source' => 'cron',
            'email' => $email,
            'total_imported' => (int) ($summary['total_imported'] ?? 0),
            'total_skipped' => (int) ($summary['total_skipped'] ?? 0),
            'total_already_in_crm' => InboxSyncSummaryPresenter::totalAlreadyInCrm($summary),
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
