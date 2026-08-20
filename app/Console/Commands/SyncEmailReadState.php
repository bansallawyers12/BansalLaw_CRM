<?php

namespace App\Console\Commands;

use App\Logging\InboxSyncLogger;
use App\Models\Email;
use App\Models\EmailLog;
use App\Services\EmailSync\IncomingEmailSyncService;
use App\Services\EmailSync\ZohoImapFetcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SyncEmailReadState extends Command
{
    protected $signature = 'emails:sync-read-state
                            {--mailbox= : Sync read state for one mailbox address only}
                            {--dry-run : Show changes without updating the database}
                            {--chunk=200 : Number of email_logs rows to process per batch}';

    protected $description = 'Align CRM mail_is_read with Zoho/Outlook \\Seen for all existing IMAP-synced emails';

    public function handle(ZohoImapFetcher $fetcher): int
    {
        if (! Schema::hasColumn('email_logs', 'mail_is_read')) {
            $this->error('email_logs.mail_is_read column is missing.');

            return self::FAILURE;
        }

        if (! Schema::hasColumn('email_logs', 'synced_email_id') || ! Schema::hasColumn('email_logs', 'imap_uid')) {
            $this->error('email_logs IMAP sync columns (synced_email_id, imap_uid) are missing.');

            return self::FAILURE;
        }

        $mailboxFilter = strtolower(trim((string) $this->option('mailbox')));
        $dryRun = (bool) $this->option('dry-run');
        $chunkSize = max(50, (int) $this->option('chunk'));

        $mailboxQuery = Email::query()
            ->where('status', true)
            ->where('sync_enabled', true)
            ->orderBy('email');
        IncomingEmailSyncService::applyMailboxHasZohoPasswordScope($mailboxQuery);

        if ($mailboxFilter !== '') {
            $mailboxQuery->whereRaw('LOWER(email) = ?', [$mailboxFilter]);
        }

        $mailboxes = $mailboxQuery->get();
        if ($mailboxes->isEmpty()) {
            $this->warn('No active sync-enabled mailboxes matched.');

            return self::SUCCESS;
        }

        $totals = [
            'checked' => 0,
            'marked_read' => 0,
            'marked_unread' => 0,
            'unchanged' => 0,
            'missing_on_imap' => 0,
            'errors' => 0,
        ];

        foreach ($mailboxes as $mailbox) {
            $this->info('Mailbox: ' . $mailbox->email);
            $mailboxTotals = $this->syncMailboxReadState($fetcher, $mailbox, $dryRun, $chunkSize);
            foreach ($mailboxTotals as $key => $value) {
                $totals[$key] += $value;
            }
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            collect($totals)->map(fn ($count, $metric) => [$metric, $count])->values()->all()
        );

        InboxSyncLogger::info('Read-state backfill completed', array_merge($totals, [
            'dry_run' => $dryRun,
            'mailbox' => $mailboxFilter !== '' ? $mailboxFilter : 'all',
        ]));

        if ($dryRun) {
            $this->comment('Dry run only — no database rows were updated.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    protected function syncMailboxReadState(
        ZohoImapFetcher $fetcher,
        Email $mailbox,
        bool $dryRun,
        int $chunkSize
    ): array {
        $totals = [
            'checked' => 0,
            'marked_read' => 0,
            'marked_unread' => 0,
            'unchanged' => 0,
            'missing_on_imap' => 0,
            'errors' => 0,
        ];

        $inboxFolders = (array) config('imap_sync.folders', ['INBOX']);
        $sentFolders = (array) config('imap_sync.sent_folders', ['Sent']);
        $folderGroups = [
            'inbox' => $inboxFolders[0] ?? 'INBOX',
            'sent' => $sentFolders[0] ?? 'Sent',
        ];

        EmailLog::query()
            ->where('synced_email_id', $mailbox->id)
            ->whereNotNull('imap_uid')
            ->where(function ($query) {
                $query->where('mail_body_type', 'inbox')
                    ->orWhere('mail_body_type', 'sent')
                    ->orWhereNull('mail_body_type');
            })
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use ($fetcher, $mailbox, $folderGroups, $dryRun, &$totals) {
                $uidsByFolder = ['inbox' => [], 'sent' => []];

                foreach ($rows as $row) {
                    $bodyType = strtolower(trim((string) ($row->mail_body_type ?? 'inbox')));
                    $group = $bodyType === 'sent' ? 'sent' : 'inbox';
                    $uidsByFolder[$group][] = (int) $row->imap_uid;
                }

                $seenOnImap = [];
                foreach ($uidsByFolder as $group => $uids) {
                    if ($uids === []) {
                        continue;
                    }

                    try {
                        $folderName = $folderGroups[$group];
                        $seenOnImap += $fetcher->fetchSeenFlagsForUids($mailbox, $uids, $folderName);
                    } catch (\Throwable $e) {
                        $totals['errors'] += count($uids);
                        $this->error('  IMAP flag fetch failed (' . $group . '): ' . $e->getMessage());

                        continue;
                    }
                }

                foreach ($rows as $row) {
                    $totals['checked']++;
                    $uid = (int) $row->imap_uid;

                    if (! array_key_exists($uid, $seenOnImap)) {
                        $totals['missing_on_imap']++;
                        continue;
                    }

                    $shouldBeRead = (bool) $seenOnImap[$uid];
                    $currentlyRead = (bool) ($row->mail_is_read ?? false);

                    if ($shouldBeRead === $currentlyRead) {
                        $totals['unchanged']++;

                        continue;
                    }

                    if ($shouldBeRead) {
                        $totals['marked_read']++;
                        if (! $dryRun) {
                            EmailLog::query()->where('id', $row->id)->update(['mail_is_read' => true]);
                        }
                    } else {
                        $totals['marked_unread']++;
                        if (! $dryRun) {
                            EmailLog::query()->where('id', $row->id)->update(['mail_is_read' => false]);
                        }
                    }
                }
            });

        return $totals;
    }
}
