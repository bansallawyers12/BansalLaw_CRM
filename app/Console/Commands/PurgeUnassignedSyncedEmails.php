<?php

namespace App\Console\Commands;

use App\Logging\InboxSyncLogger;
use App\Services\EmailSync\IncomingEmailSyncService;
use Illuminate\Console\Command;

class PurgeUnassignedSyncedEmails extends Command
{
    protected $signature = 'emails:purge-unassigned-before-floor
                            {--mailbox= : Limit to one mailbox address}
                            {--with-zoho : Also delete matching messages from Zoho IMAP}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Delete unassigned synced emails before the availability floor (default 10 Aug 2026) from CRM, optionally also from Zoho';

    public function handle(IncomingEmailSyncService $syncService): int
    {
        $floor = IncomingEmailSyncService::resolveUnassignedAvailableFrom();
        if ($floor === null) {
            $this->error('MAIL_SYNC_UNASSIGNED_AVAILABLE_FROM is empty; nothing to purge.');

            return self::FAILURE;
        }

        $mailbox = strtolower(trim((string) $this->option('mailbox')));
        $withZoho = (bool) $this->option('with-zoho');
        $addresses = $mailbox !== '' ? [$mailbox] : null;

        $this->warn('Cutoff: ' . $floor->toDateString() . ' (app timezone)');
        $this->warn('Scope: ' . ($mailbox !== '' ? $mailbox : 'all sync-enabled mailboxes'));
        $this->warn('Zoho IMAP delete: ' . ($withZoho ? 'YES' : 'no (CRM only)'));

        if (! $this->option('force') && ! $this->confirm('Delete matching unassigned emails permanently?', false)) {
            $this->comment('Cancelled.');

            return self::SUCCESS;
        }

        $result = $syncService->purgeUnassignedSyncedBeforeAvailabilityFloor($addresses, $withZoho);

        $this->table(
            ['Metric', 'Count'],
            [
                ['CRM deleted', (int) ($result['deleted'] ?? 0)],
                ['Zoho deleted', (int) ($result['imap_deleted'] ?? 0)],
                ['Zoho missing', (int) ($result['imap_missing'] ?? 0)],
                ['Zoho failed', (int) ($result['imap_failed'] ?? 0)],
            ]
        );

        foreach (($result['imap_errors'] ?? []) as $error) {
            $this->error((string) $error);
        }

        InboxSyncLogger::info('Artisan purge-unassigned-before-floor finished', array_merge($result, [
            'mailbox' => $mailbox !== '' ? $mailbox : 'all',
            'with_zoho' => $withZoho,
        ]));

        return ((int) ($result['imap_failed'] ?? 0) > 0) ? self::FAILURE : self::SUCCESS;
    }
}
