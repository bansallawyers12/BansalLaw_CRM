<?php

namespace App\Services\EmailSync;

use App\Http\Controllers\CRM\EmailUploadController;
use App\Models\Admin;
use App\Models\Email;
use App\Models\EmailLog;
use App\Models\Staff;
use App\Services\EmailMatchingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class IncomingEmailSyncService
{
    public function __construct(
        private ZohoImapFetcher $imapFetcher,
        private EmailMatchingService $matchingService,
        private EmailUploadController $uploadController,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function syncAll(?string $mailboxFilter = null, ?\DateTimeInterface $since = null): array
    {
        if (! config('imap_sync.enabled', true)) {
            return [
                'success' => false,
                'message' => 'Inbox sync is disabled (MAIL_INBOX_SYNC_ENABLED=false).',
                'mailboxes' => [],
            ];
        }

        $query = Email::query()
            ->where('status', true)
            ->where('sync_enabled', true);

        if ($mailboxFilter !== null && $mailboxFilter !== '') {
            $query->whereRaw('LOWER(email) = ?', [strtolower(trim($mailboxFilter))]);
        }

        $mailboxes = $query->orderBy('email')->get();
        $summary = [
            'success' => true,
            'mailboxes' => [],
            'total_imported' => 0,
            'total_skipped' => 0,
            'total_failed' => 0,
        ];

        foreach ($mailboxes as $mailbox) {
            $summary['mailboxes'][$mailbox->email] = $this->syncMailbox($mailbox, $since);
            $summary['total_imported'] += (int) ($summary['mailboxes'][$mailbox->email]['imported'] ?? 0);
            $summary['total_skipped'] += (int) ($summary['mailboxes'][$mailbox->email]['skipped'] ?? 0);
            $summary['total_failed'] += (int) ($summary['mailboxes'][$mailbox->email]['failed'] ?? 0);
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    public function syncMailbox(Email $mailbox, ?\DateTimeInterface $since = null): array
    {
        $staffUserId = $mailbox->resolveOwnerStaffId();
        if (! $staffUserId) {
            $error = 'No staff user linked to mailbox ' . $mailbox->email;
            $this->markSyncError($mailbox, $error);

            return [
                'mailbox' => $mailbox->email,
                'imported' => 0,
                'skipped' => 0,
                'failed' => 0,
                'errors' => [$error],
                'last_uid' => (int) ($mailbox->last_imap_uid ?? 0),
            ];
        }

        $inboxResult = $this->syncMailboxFolder(
            $mailbox,
            $staffUserId,
            (array) config('imap_sync.folders', ['INBOX']),
            (int) ($mailbox->last_imap_uid ?? 0),
            'inbox',
            $since
        );

        $combined = $inboxResult;
        $mailbox->last_imap_uid = $inboxResult['last_uid'];
        $combined['last_uid'] = $inboxResult['last_uid'];

        if ($mailbox->sync_sent_enabled) {
            $sentResult = $this->syncMailboxFolder(
                $mailbox,
                $staffUserId,
                (array) config('imap_sync.sent_folders', ['Sent']),
                (int) ($mailbox->last_imap_uid_sent ?? 0),
                'sent',
                $since
            );

            $combined['imported'] += $sentResult['imported'];
            $combined['skipped'] += $sentResult['skipped'];
            $combined['failed'] += $sentResult['failed'];
            $combined['errors'] = array_merge($combined['errors'], $sentResult['errors']);
            $combined['sent_last_uid'] = $sentResult['last_uid'];
            $mailbox->last_imap_uid_sent = $sentResult['last_uid'];
        }

        $mailbox->last_synced_at = now();
        $mailbox->last_sync_error = $combined['errors'] === [] ? null : Str::limit(implode('; ', $combined['errors']), 2000);
        $mailbox->save();

        return $combined;
    }

    /**
     * @return array<string, mixed>
     */
    protected function syncMailboxFolder(
        Email $mailbox,
        int $staffUserId,
        array $folders,
        int $afterUid,
        string $defaultMailType,
        ?\DateTimeInterface $since = null
    ): array {
        $result = [
            'mailbox' => $mailbox->email,
            'imported' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
            'last_uid' => $afterUid,
            'folder_type' => $defaultMailType,
        ];

        $limit = (int) config('imap_sync.max_messages_per_mailbox', 25);
        if ($since === null && $afterUid <= 0) {
            $limit *= max(1, (int) config('imap_sync.initial_backfill_multiplier', 4));
        }

        try {
            $messages = $this->imapFetcher->fetchFromFolders($mailbox, $afterUid, $limit, $folders, $since);
        } catch (Throwable $e) {
            $result['errors'][] = strtoupper($defaultMailType) . ': ' . $e->getMessage();
            Log::error('IMAP sync failed', [
                'mailbox' => $mailbox->email,
                'folder_type' => $defaultMailType,
                'error' => $e->getMessage(),
            ]);

            return $result;
        }

        $maxUid = $afterUid;

        foreach ($messages as $message) {
            $uid = (int) $message['uid'];
            $maxUid = max($maxUid, $uid);

            try {
                $importResult = $this->importMessage(
                    $mailbox,
                    $staffUserId,
                    $message['raw_eml'],
                    $uid,
                    $message['subject'] ?? '',
                    $defaultMailType
                );

                if (! empty($importResult['skipped'])) {
                    $result['skipped']++;
                } elseif (! empty($importResult['success'])) {
                    $result['imported']++;
                } else {
                    $result['failed']++;
                    $result['errors'][] = $importResult['error'] ?? ('Import failed for UID ' . $uid);
                }
            } catch (Throwable $e) {
                $result['failed']++;
                $result['errors'][] = 'UID ' . $uid . ': ' . $e->getMessage();
                Log::error('Synced email import failed', [
                    'mailbox' => $mailbox->email,
                    'uid' => $uid,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $result['last_uid'] = $maxUid;

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    protected function importMessage(
        Email $mailbox,
        int $staffUserId,
        string $rawEml,
        int $imapUid,
        string $subjectHint,
        string $defaultMailType = 'inbox'
    ): array {
        $tempPath = storage_path('app/temp/imap-sync-' . Str::uuid() . '.eml');
        if (! is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        file_put_contents($tempPath, $rawEml);

        $guard = Auth::guard('admin');
        $previousUser = $guard->user();
        $didLoginStaff = false;

        try {
            $safeName = $this->buildEmlFilename($subjectHint, $imapUid);
            $uploadedFile = new UploadedFile($tempPath, $safeName, 'message/rfc822', null, true);

            $parsedData = $this->uploadController->parseEmailFileForSync($uploadedFile);
            if (! $parsedData || ! empty($parsedData['error']) || (isset($parsedData['success']) && ! $parsedData['success'])) {
                return [
                    'success' => false,
                    'error' => $parsedData['error'] ?? 'Failed to parse synced email',
                ];
            }

            $messageId = trim((string) ($parsedData['message_id'] ?? ''));

            $mailType = $defaultMailType === 'sent' ? 'sent' : 'inbox';
            if ($this->isDuplicate($mailbox, $messageId, $imapUid, $mailType)) {
                return ['success' => true, 'skipped' => true];
            }

            $staff = Staff::find($staffUserId);
            if ($staff) {
                $guard->login($staff);
                $didLoginStaff = true;
            }

            $match = $this->matchingService->suggestMatches($parsedData);
            if ($defaultMailType !== 'sent') {
                $mailType = $match['mail_type'] ?? 'inbox';
            }

            $bestMatch = $match['best'] ?? null;
            $matchedBy = array_values(array_unique(array_merge(
                $match['matched_by'] ?? [],
                is_array($bestMatch) ? ($bestMatch['matched_by'] ?? []) : []
            )));
            $hasEmailMatch = in_array('email_address', $matchedBy, true);
            $isAutoAssigned = ! empty($bestMatch['client_id'])
                && (! empty($match['is_high_confidence']) || $hasEmailMatch);

            $clientId = $isAutoAssigned ? (int) $bestMatch['client_id'] : null;
            $clientMatterId = $isAutoAssigned ? (int) ($bestMatch['client_matter_id'] ?? 0) : null;
            $clientMatterId = $clientMatterId > 0 ? $clientMatterId : null;
            $recordType = (string) ($bestMatch['record_type'] ?? 'client');

            $clientUniqueId = $this->resolveStoragePrefix($mailbox, $clientId);

            $import = $this->uploadController->importFromSync(
                $uploadedFile,
                $clientId,
                $clientMatterId,
                $recordType,
                $mailType,
                $staffUserId,
                $clientUniqueId,
                [
                    'mailbox_email' => strtolower(trim($mailbox->email)),
                    'synced_email_id' => $mailbox->id,
                    'sync_assignment_status' => $isAutoAssigned ? 'auto_assigned' : 'unassigned',
                    'imap_uid' => $imapUid,
                    'message_id' => $messageId,
                ]
            );

            if (! empty($import['success'])) {
                return $import;
            }

            return [
                'success' => false,
                'error' => $import['error'] ?? 'Import failed',
            ];
        } finally {
            @unlink($tempPath);
            if ($didLoginStaff) {
                $guard->logout();
                if ($previousUser) {
                    $guard->login($previousUser);
                }
            }
        }
    }

    protected function isDuplicate(Email $mailbox, string $messageId, int $imapUid, string $mailType = 'inbox'): bool
    {
        if ($messageId !== '') {
            $exists = EmailLog::query()
                ->where('message_id', $messageId)
                ->where(function ($q) use ($mailbox) {
                    $q->where('mailbox_email', strtolower(trim($mailbox->email)))
                        ->orWhere('synced_email_id', $mailbox->id);
                })
                ->exists();

            if ($exists) {
                return true;
            }
        }

        return EmailLog::query()
            ->where('synced_email_id', $mailbox->id)
            ->where('imap_uid', $imapUid)
            ->where('mail_body_type', $mailType)
            ->exists();
    }

    protected function resolveStoragePrefix(Email $mailbox, ?int $clientId): string
    {
        if ($clientId) {
            $clientRef = Admin::query()->where('id', $clientId)->value('client_id');

            return ! empty($clientRef)
                ? (string) $clientRef
                : 'client_' . $clientId;
        }

        $safeMailbox = preg_replace('/[^a-zA-Z0-9\-_.@]/', '_', strtolower($mailbox->email));

        return config('imap_sync.unassigned_storage_prefix', 'sync-inbox') . '/' . $safeMailbox;
    }

    protected function buildEmlFilename(string $subject, int $uid): string
    {
        $slug = Str::slug(Str::limit($subject, 60, ''), '-');
        if ($slug === '') {
            $slug = 'message';
        }

        return $slug . '-' . $uid . '.eml';
    }

    protected function markSyncError(Email $mailbox, string $error): void
    {
        $mailbox->last_synced_at = now();
        $mailbox->last_sync_error = Str::limit($error, 2000);
        $mailbox->save();
    }

    /**
     * @return array<string, string>
     */
    public static function syncRangeOptions(): array
    {
        return [
            'today' => 'Today',
            '2days' => 'Last 2 days',
            '5days' => 'Last 5 days',
            '1week' => 'Last 1 week',
            '2weeks' => 'Last 2 weeks',
            '1month' => 'Last 1 month',
            'full' => 'Full (reset & backfill)',
        ];
    }

    public static function isValidSyncRange(string $range): bool
    {
        return array_key_exists(strtolower(trim($range)), self::syncRangeOptions());
    }

    public static function resolveSyncSince(string $range): ?\Carbon\Carbon
    {
        $normalized = strtolower(trim($range));
        if ($normalized === 'full') {
            return null;
        }

        $timezone = (string) config('app.timezone', 'UTC');
        $now = now($timezone);

        return match ($normalized) {
            '2days' => $now->copy()->subDays(1)->startOfDay(),
            '5days' => $now->copy()->subDays(4)->startOfDay(),
            '1week' => $now->copy()->subDays(6)->startOfDay(),
            '2weeks' => $now->copy()->subDays(13)->startOfDay(),
            '1month' => $now->copy()->subDays(29)->startOfDay(),
            default => $now->copy()->startOfDay(),
        };
    }

    /**
     * @param list<string>|null $mailboxAddresses
     */
    public function resetUidTracking(?string $mailboxFilter = null, ?array $mailboxAddresses = null): int
    {
        $query = Email::query()
            ->where('status', true)
            ->where('sync_enabled', true);

        if ($mailboxFilter !== null && $mailboxFilter !== '') {
            $query->whereRaw('LOWER(email) = ?', [strtolower(trim($mailboxFilter))]);
        } elseif ($mailboxAddresses !== null && $mailboxAddresses !== []) {
            $normalized = array_values(array_unique(array_filter(array_map(
                static fn (string $address): string => strtolower(trim($address)),
                $mailboxAddresses
            ))));
            if ($normalized !== []) {
                $query->where(function ($builder) use ($normalized) {
                    foreach ($normalized as $address) {
                        $builder->orWhereRaw('LOWER(email) = ?', [$address]);
                    }
                });
            }
        }

        return $query->update([
            'last_imap_uid' => null,
            'last_imap_uid_sent' => null,
        ]);
    }

    /**
     * @return list<string>
     */
    public static function staffRecipientEmailsForSyncFilter(Staff $staff): array
    {
        $emails = [];

        if (trim((string) $staff->email) !== '') {
            $emails[] = strtolower(trim((string) $staff->email));
        }

        foreach (self::mailboxAddressesForStaff((int) $staff->id, $staff->email) as $address) {
            $emails[] = strtolower(trim($address));
        }

        return array_values(array_unique(array_filter($emails)));
    }

    /**
     * True synced inbox queue items still waiting for a client (excludes assigned mail).
     */
    public static function applyUnassignedSyncedInboxScope($query): void
    {
        if (! Schema::hasColumn('email_logs', 'sync_assignment_status')) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where('sync_assignment_status', 'unassigned')
            ->where(function ($clientQuery) {
                $clientQuery->whereNull('client_id')
                    ->orWhere('client_id', 0);
            });

        if (Schema::hasColumn('email_logs', 'synced_email_id')) {
            $query->whereNotNull('synced_email_id');
        }
    }

    /**
     * Limit synced inbox lists to mail relevant to the staff member (To/Cc/Bcc/mailbox/from).
     * Admin and Super Admin see all synced mail.
     */
    public static function applySyncedInboxVisibilityFilter($query, Staff $staff): void
    {
        if ($staff->canViewAllSyncedInboxMail()) {
            return;
        }

        $staffEmails = self::staffRecipientEmailsForSyncFilter($staff);
        if ($staffEmails === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function ($outer) use ($staffEmails) {
            foreach ($staffEmails as $email) {
                $outer->orWhere(function ($match) use ($email) {
                    $match->whereRaw('LOWER(COALESCE(mailbox_email, \'\')) = ?', [$email])
                        ->orWhereRaw('LOWER(COALESCE(from_mail, \'\')) = ?', [$email]);
                    self::applyRecipientEmailMatch($match, 'to_mail', $email);
                    self::applyRecipientEmailMatch($match, 'cc', $email);
                    self::applyRecipientEmailMatch($match, 'bcc', $email);
                });
            }
        });
    }

    protected static function applyRecipientEmailMatch($query, string $column, string $email): void
    {
        if (! in_array($column, ['to_mail', 'cc', 'bcc'], true)) {
            return;
        }

        $query->orWhere(function ($recipient) use ($column, $email) {
            $recipient->whereRaw('LOWER(COALESCE(' . $column . ', \'\')) = ?', [$email])
                ->orWhereRaw('LOWER(COALESCE(' . $column . ', \'\')) LIKE ?', [$email . ',%'])
                ->orWhereRaw('LOWER(COALESCE(' . $column . ', \'\')) LIKE ?', ['%,' . $email . ',%'])
                ->orWhereRaw('LOWER(COALESCE(' . $column . ', \'\')) LIKE ?', ['%,' . $email]);
        });
    }

    /**
     * Mailboxes the given staff member may view (shared + own email match).
     *
     * @return list<string>
     */
    public static function mailboxAddressesForStaff(int $staffId, ?string $staffEmail = null): array
    {
        $addresses = [];

        $accounts = Email::query()
            ->where('status', true)
            ->orderBy('email')
            ->get();

        foreach ($accounts as $account) {
            if ($account->isSharedWithStaff($staffId)) {
                $addresses[] = strtolower(trim($account->email));
                continue;
            }

            if ($staffEmail && strcasecmp(trim($account->email), trim($staffEmail)) === 0) {
                $addresses[] = strtolower(trim($account->email));
                continue;
            }

            if ((int) ($account->resolveOwnerStaffId() ?? 0) === $staffId) {
                $addresses[] = strtolower(trim($account->email));
            }
        }

        return array_values(array_unique(array_filter($addresses)));
    }
}
