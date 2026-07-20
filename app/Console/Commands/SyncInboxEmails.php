<?php

namespace App\Console\Commands;

use App\Models\Email;
use App\Services\EmailSync\IncomingEmailSyncService;
use Illuminate\Console\Command;

class SyncInboxEmails extends Command
{
    protected $signature = 'emails:sync-inbox
                            {email? : Optional mailbox address to sync}
                            {--full : Reset UID tracking and re-fetch recent mail (backfill)}';

    protected $description = 'Fetch new incoming mail from Zoho IMAP for all active CRM mailboxes';

    public function handle(IncomingEmailSyncService $syncService): int
    {
        if (! config('imap_sync.enabled', true)) {
            $this->warn('Inbox sync is disabled. Set MAIL_INBOX_SYNC_ENABLED=true to enable.');

            return self::SUCCESS;
        }

        $email = $this->argument('email');

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

        $this->info('Starting inbox sync' . ($email ? " for {$email}" : ' for all mailboxes') . '...');

        $summary = $syncService->syncAll($email);

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

        return ((int) ($summary['total_failed'] ?? 0)) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
