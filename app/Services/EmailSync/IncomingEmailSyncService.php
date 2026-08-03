<?php

namespace App\Services\EmailSync;

use App\Http\Controllers\CRM\EmailUploadController;
use App\Logging\InboxSyncLogger;
use App\Models\Admin;
use App\Models\Email;
use App\Models\EmailLog;
use App\Models\Staff;
use App\Services\EmailMatchingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class IncomingEmailSyncService
{
    private const PARSER_DOWN_CACHE_KEY = 'inbox_sync_parser_unavailable';

    private string $currentSyncSource = 'cron';

    public function __construct(
        private ZohoImapFetcher $imapFetcher,
        private EmailMatchingService $matchingService,
        private EmailUploadController $uploadController,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function syncAll(?string $mailboxFilter = null, ?\DateTimeInterface $since = null, string $syncSource = 'cron'): array
    {
        self::configureSyncRuntime();

        $this->currentSyncSource = in_array($syncSource, ['manual', 'cron', 'compose'], true) ? $syncSource : 'cron';

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

        if (! self::isPythonParserAvailable()) {
            self::markParserUnavailable();
        }

        $forceCatchup = self::isPythonParserAvailable() && self::consumeParserRecoveryFlag();

        $summary = [
            'success' => true,
            'mailboxes' => [],
            'total_imported' => 0,
            'total_skipped' => 0,
            'total_failed' => 0,
            'auto_catchup' => $forceCatchup,
        ];

        foreach ($mailboxes as $mailbox) {
            $summary['mailboxes'][$mailbox->email] = $this->syncMailbox($mailbox, $since, $forceCatchup);
            $summary['total_imported'] += (int) ($summary['mailboxes'][$mailbox->email]['imported'] ?? 0);
            $summary['total_skipped'] += (int) ($summary['mailboxes'][$mailbox->email]['skipped'] ?? 0);
            $summary['total_failed'] += (int) ($summary['mailboxes'][$mailbox->email]['failed'] ?? 0);
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    public function syncMailbox(Email $mailbox, ?\DateTimeInterface $since = null, bool $forceCatchup = false): array
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

        $inboxAfterUid = $this->resolveImapCursor($mailbox, 'inbox');

        $inboxResult = $this->syncMailboxFolder(
            $mailbox,
            $staffUserId,
            (array) config('imap_sync.folders', ['INBOX']),
            $inboxAfterUid,
            'inbox',
            $since
        );

        $combined = $inboxResult;
        $mailbox->last_imap_uid = $inboxResult['last_uid'];
        $combined['last_uid'] = $inboxResult['last_uid'];

        if ($mailbox->sync_sent_enabled) {
            $sentAfterUid = $this->resolveImapCursor($mailbox, 'sent');
            $sentResult = $this->syncMailboxFolder(
                $mailbox,
                $staffUserId,
                (array) config('imap_sync.sent_folders', ['Sent']),
                $sentAfterUid,
                'sent',
                $since
            );

            $combined = $this->mergeSyncResults($combined, $sentResult);
            $combined['sent_last_uid'] = $sentResult['last_uid'];
            $mailbox->last_imap_uid_sent = $sentResult['last_uid'];
        }

        $combined = $this->runAutoCatchupIfNeeded($mailbox, $staffUserId, $combined, $forceCatchup);

        $mailbox->last_synced_at = now();
        $mailbox->last_sync_error = $combined['errors'] === [] ? null : Str::limit(implode('; ', $combined['errors']), 2000);
        $mailbox->save();

        return $combined;
    }

    /**
     * Roll back IMAP UID watermarks that were advanced without a successful import.
     */
    protected function resolveImapCursor(Email $mailbox, string $folderType): int
    {
        $column = $folderType === 'sent' ? 'last_imap_uid_sent' : 'last_imap_uid';
        $stored = (int) ($mailbox->$column ?? 0);
        $mailBodyType = $folderType === 'sent' ? 'sent' : 'inbox';

        $importedMax = (int) (EmailLog::query()
            ->where('synced_email_id', $mailbox->id)
            ->where('mail_body_type', $mailBodyType)
            ->whereNotNull('imap_uid')
            ->max('imap_uid') ?? 0);

        if ($importedMax > 0 && $stored > $importedMax) {
            InboxSyncLogger::warning('Healed IMAP UID watermark from imported mail history', [
                'mailbox' => $mailbox->email,
                'folder_type' => $folderType,
                'stored_uid' => $stored,
                'imported_max_uid' => $importedMax,
            ]);

            $mailbox->$column = $importedMax;

            return $importedMax;
        }

        return $stored;
    }

    /**
     * @param  array<string, mixed>  $primary
     */
    protected function runAutoCatchupIfNeeded(
        Email $mailbox,
        int $staffUserId,
        array $primary,
        bool $forceCatchup
    ): array {
        if (! config('imap_sync.auto_catchup_enabled', true)) {
            return $primary;
        }

        if (! self::isPythonParserAvailable()) {
            self::markParserUnavailable();

            return $primary;
        }

        $needsCatchup = $forceCatchup
            || $this->shouldRunPeriodicCatchup($mailbox)
            || $this->primarySyncHadImportFailures($primary);

        if (! $needsCatchup) {
            return $primary;
        }

        $days = max(1, (int) config('imap_sync.auto_catchup_days', 7));
        $timezone = (string) config('app.timezone', 'UTC');
        $catchupSince = now($timezone)->subDays($days - 1)->startOfDay();

        $reason = $forceCatchup
            ? 'parser_recovery'
            : ($this->primarySyncHadImportFailures($primary) ? 'import_failures' : 'periodic');

        InboxSyncLogger::info('Automatic catch-up inbox sync started', [
            'mailbox' => $mailbox->email,
            'since' => $catchupSince->format('c'),
            'reason' => $reason,
        ]);

        $catchupInbox = $this->syncMailboxFolder(
            $mailbox,
            $staffUserId,
            (array) config('imap_sync.folders', ['INBOX']),
            (int) ($mailbox->last_imap_uid ?? 0),
            'inbox',
            $catchupSince
        );

        $combined = $this->mergeSyncResults($primary, $catchupInbox);
        $combined['auto_catchup'] = true;
        $combined['auto_catchup_reason'] = $reason;
        $mailbox->last_imap_uid = max((int) ($mailbox->last_imap_uid ?? 0), (int) ($catchupInbox['last_uid'] ?? 0));
        $combined['last_uid'] = $mailbox->last_imap_uid;

        if ($mailbox->sync_sent_enabled) {
            $catchupSent = $this->syncMailboxFolder(
                $mailbox,
                $staffUserId,
                (array) config('imap_sync.sent_folders', ['Sent']),
                (int) ($mailbox->last_imap_uid_sent ?? 0),
                'sent',
                $catchupSince
            );

            $combined = $this->mergeSyncResults($combined, $catchupSent);
            $mailbox->last_imap_uid_sent = max(
                (int) ($mailbox->last_imap_uid_sent ?? 0),
                (int) ($catchupSent['last_uid'] ?? 0)
            );
            $combined['sent_last_uid'] = $mailbox->last_imap_uid_sent;
        }

        $this->markCatchupRan($mailbox);

        $catchupImported = (int) ($catchupInbox['imported'] ?? 0);
        if ($mailbox->sync_sent_enabled) {
            $catchupImported += (int) ($catchupSent['imported'] ?? 0);
        }

        InboxSyncLogger::info('Automatic catch-up inbox sync finished', [
            'mailbox' => $mailbox->email,
            'reason' => $reason,
            'imported' => $catchupImported,
            'skipped' => (int) ($catchupInbox['skipped'] ?? 0),
            'failed' => (int) ($catchupInbox['failed'] ?? 0),
        ]);

        return $combined;
    }

    /**
     * @param  array<string, mixed>  $left
     * @param  array<string, mixed>  $right
     * @return array<string, mixed>
     */
    protected function mergeSyncResults(array $left, array $right): array
    {
        $merged = $left;
        $merged['imported'] = (int) ($left['imported'] ?? 0) + (int) ($right['imported'] ?? 0);
        $merged['skipped'] = (int) ($left['skipped'] ?? 0) + (int) ($right['skipped'] ?? 0);
        $merged['failed'] = (int) ($left['failed'] ?? 0) + (int) ($right['failed'] ?? 0);
        $merged['errors'] = array_values(array_merge(
            (array) ($left['errors'] ?? []),
            (array) ($right['errors'] ?? [])
        ));

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function primarySyncHadImportFailures(array $result): bool
    {
        return (int) ($result['failed'] ?? 0) > 0
            && (int) ($result['imported'] ?? 0) === 0
            && (int) ($result['skipped'] ?? 0) === 0;
    }

    protected function shouldRunPeriodicCatchup(Email $mailbox): bool
    {
        $hours = max(1, (int) config('imap_sync.auto_catchup_interval_hours', 6));
        $last = Cache::get($this->catchupCacheKey($mailbox));

        if ($last === null) {
            return true;
        }

        try {
            return now()->diffInHours(\Illuminate\Support\Carbon::parse($last)) >= $hours;
        } catch (Throwable) {
            return true;
        }
    }

    protected function markCatchupRan(Email $mailbox): void
    {
        Cache::put(
            $this->catchupCacheKey($mailbox),
            now()->toIso8601String(),
            now()->addDays(30)
        );
    }

    protected function catchupCacheKey(Email $mailbox): string
    {
        return 'inbox_sync_catchup_at:' . $mailbox->id;
    }

    public static function markParserUnavailable(): void
    {
        Cache::put(self::PARSER_DOWN_CACHE_KEY, true, now()->addDays(14));
    }

    public static function consumeParserRecoveryFlag(): bool
    {
        return (bool) Cache::pull(self::PARSER_DOWN_CACHE_KEY, false);
    }

    /**
     * Raise PHP memory/time limits for IMAP sync when the current CLI limit is too low.
     * Skips web requests that already have unlimited memory (-1).
     */
    public static function configureSyncRuntime(): void
    {
        $memoryLimit = trim((string) config('mail_sync.memory_limit', '1G'));
        if ($memoryLimit !== '' && $memoryLimit !== '-1') {
            $currentBytes = self::memoryLimitToBytes((string) ini_get('memory_limit'));
            $targetBytes = self::memoryLimitToBytes($memoryLimit);

            if ($currentBytes !== -1 && ($targetBytes === -1 || $targetBytes > $currentBytes)) {
                @ini_set('memory_limit', $memoryLimit);
            }
        }

        $timeout = (int) config('mail_sync.sync_timeout', 900);
        if ($timeout > 0) {
            @ini_set('max_execution_time', (string) $timeout);
        }
    }

    protected static function memoryLimitToBytes(string $limit): int
    {
        $limit = trim($limit);
        if ($limit === '' || $limit === '-1') {
            return -1;
        }

        $unit = strtolower(substr($limit, -1));
        $value = (int) $limit;

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => (int) $limit,
        };
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
        } elseif ($since !== null) {
            $limit *= max(1, (int) config('imap_sync.range_sync_multiplier', 4));
        }

        $maxBatches = $since !== null
            ? 1
            : max(1, (int) config('imap_sync.max_incremental_batches', 6));

        $cursorUid = $afterUid;
        $maxUid = $afterUid;

        for ($batch = 0; $batch < $maxBatches; $batch++) {
            try {
                $messages = $this->imapFetcher->fetchFromFolders(
                    $mailbox,
                    $cursorUid,
                    $limit,
                    $folders,
                    $since
                );
            } catch (Throwable $e) {
                $result['errors'][] = strtoupper($defaultMailType) . ': ' . $e->getMessage();
                InboxSyncLogger::error('IMAP sync failed', [
                    'mailbox' => $mailbox->email,
                    'folder_type' => $defaultMailType,
                    'batch' => $batch + 1,
                    'error' => $e->getMessage(),
                ], $e);

                break;
            }

            if ($messages === []) {
                break;
            }

            $batchHighestUid = $cursorUid;

            foreach ($messages as $message) {
                $uid = (int) $message['uid'];

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
                        $batchHighestUid = max($batchHighestUid, $uid);
                        $maxUid = max($maxUid, $uid);
                    } elseif (! empty($importResult['success'])) {
                        $result['imported']++;
                        $batchHighestUid = max($batchHighestUid, $uid);
                        $maxUid = max($maxUid, $uid);
                    } else {
                        $result['failed']++;
                        $result['errors'][] = $importResult['error'] ?? ('Import failed for UID ' . $uid);
                    }
                } catch (Throwable $e) {
                    $result['failed']++;
                    $result['errors'][] = 'UID ' . $uid . ': ' . $e->getMessage();
                    InboxSyncLogger::error('Synced email import failed', [
                        'mailbox' => $mailbox->email,
                        'uid' => $uid,
                        'error' => $e->getMessage(),
                    ], $e);
                }

                unset($message);
            }

            $fetchedCount = count($messages);
            unset($messages);

            if ($since === null) {
                if ($batchHighestUid <= $cursorUid) {
                    break;
                }
                $cursorUid = $batchHighestUid;
            }

            if ($fetchedCount < $limit) {
                break;
            }
        }

        $result['last_uid'] = max($afterUid, $maxUid);

        return $result;
    }

    /**
     * Verify the Python email parser is reachable before importing synced mail.
     */
    public static function isPythonParserAvailable(): bool
    {
        $url = rtrim((string) config('services.python.url', ''), '/');
        if ($url === '') {
            return false;
        }

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(5)->get($url . '/health');

            return $response->successful();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array{available: bool, url: string, message: string}
     */
    public static function pythonParserStatus(): array
    {
        $url = rtrim((string) config('services.python.url', ''), '/');
        if ($url === '') {
            return [
                'available' => false,
                'url' => '',
                'message' => 'Python email parser URL is not configured.',
            ];
        }

        if (self::isPythonParserAvailable()) {
            return [
                'available' => true,
                'url' => $url,
                'message' => 'Python email parser is available.',
            ];
        }

        return [
            'available' => false,
            'url' => $url,
            'message' => 'Cannot connect to the email processing service at ' . $url
                . '. Start the Python parser (python_services/start_services.py) or update PYTHON_SERVICE_URL / PYTHON_CONVERTER_URL.',
        ];
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
            $fileHash = md5($rawEml);

            $match = $this->matchingService->suggestMatches($parsedData);
            $mailType = $defaultMailType === 'sent'
                ? 'sent'
                : ($match['mail_type'] ?? 'inbox');

            // When Sent-folder sync is enabled, outgoing mail must only be imported from Sent.
            // Zoho/mobile clients can surface the same sent message under INBOX or date catch-up.
            if ($defaultMailType === 'inbox' && $mailbox->sync_sent_enabled && $mailType === 'sent') {
                InboxSyncLogger::info('Skipped sent mail from INBOX; Sent folder sync handles outgoing mail', [
                    'mailbox' => $mailbox->email,
                    'imap_uid' => $imapUid,
                    'subject' => $parsedData['subject'] ?? $subjectHint,
                ]);

                return ['success' => true, 'skipped' => true];
            }

            if ($this->isDuplicate($mailbox, $messageId, $imapUid, $mailType, $parsedData, $fileHash)) {
                return ['success' => true, 'skipped' => true];
            }

            $staff = Staff::find($staffUserId);
            if ($staff) {
                $guard->login($staff);
                $didLoginStaff = true;
            }

            $bestMatch = $match['best'] ?? null;
            // Auto-assign only on clear high-confidence matches. Ambiguous / sub-80
            // email hits stay unassigned for manual review instead of guessing.
            $isAutoAssigned = ! empty($bestMatch['client_id'])
                && ! empty($match['is_high_confidence'])
                && empty($match['is_ambiguous']);

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
                    'sync_source' => $this->currentSyncSource,
                    // New incoming mail must always appear unread in the CRM; the IMAP
                    // \Seen flag is unreliable (body fetch or webmail access sets it).
                    'mail_is_read' => $mailType === 'sent',
                ]
            );

            if (! empty($import['success'])) {
                return $import;
            }

            if (! empty($import['skipped'])) {
                return ['success' => true, 'skipped' => true];
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

    protected function isDuplicate(
        Email $mailbox,
        string $messageId,
        int $imapUid,
        string $mailType = 'inbox',
        array $parsedData = [],
        ?string $fileHash = null
    ): bool {
        $mailboxEmail = strtolower(trim($mailbox->email));
        $normalizedMessageId = $this->normalizeMessageId($messageId);

        if ($normalizedMessageId !== '') {
            $exists = EmailLog::query()
                ->where(function ($q) use ($normalizedMessageId, $messageId) {
                    $q->where('message_id', $normalizedMessageId)
                        ->orWhere('message_id', $messageId)
                        ->orWhere('message_id', '<' . $normalizedMessageId . '>');
                })
                ->exists();

            if ($exists) {
                return true;
            }
        }

        if ($fileHash !== null && $fileHash !== '') {
            $hashExists = EmailLog::query()
                ->where('file_hash', $fileHash)
                ->where(function ($q) use ($mailbox, $mailboxEmail) {
                    $q->where('synced_email_id', $mailbox->id)
                        ->orWhereRaw('LOWER(mailbox_email) = ?', [$mailboxEmail]);
                })
                ->exists();

            if ($hashExists) {
                return true;
            }
        }

        if (EmailLog::query()
            ->where('synced_email_id', $mailbox->id)
            ->where('imap_uid', $imapUid)
            ->where('mail_body_type', $mailType)
            ->exists()) {
            return true;
        }

        return $this->isFuzzyMailboxDuplicate($mailbox, $parsedData);
    }

    protected function normalizeMessageId(string $messageId): string
    {
        $messageId = trim($messageId);
        if ($messageId === '') {
            return '';
        }

        if (preg_match('/^<(.+)>$/', $messageId, $matches)) {
            return trim($matches[1]);
        }

        return $messageId;
    }

    /**
     * Match same logical email across Sent/Inbox folders or date catch-up re-fetches.
     */
    protected function isFuzzyMailboxDuplicate(Email $mailbox, array $parsedData): bool
    {
        $subject = strtolower(trim((string) ($parsedData['subject'] ?? '')));
        $sender = strtolower(trim((string) ($parsedData['sender_email'] ?? $parsedData['from_mail'] ?? '')));
        $mailboxEmail = strtolower(trim($mailbox->email));

        if ($subject === '') {
            return false;
        }

        $dupQuery = EmailLog::query()
            ->where(function ($q) use ($mailbox, $mailboxEmail) {
                $q->where('synced_email_id', $mailbox->id)
                    ->orWhereRaw('LOWER(mailbox_email) = ?', [$mailboxEmail]);
            })
            ->whereRaw('LOWER(subject) = ?', [$subject]);

        if ($sender !== '') {
            $dupQuery->where(function ($q) use ($sender, $mailboxEmail) {
                $q->whereRaw('LOWER(from_mail) LIKE ?', ['%' . $sender . '%'])
                    ->orWhereRaw('LOWER(from_mail) LIKE ?', ['%' . $mailboxEmail . '%']);
            });
        }

        $sentAt = $this->resolveParsedSentTime($parsedData);
        if ($sentAt !== null) {
            $dupQuery->whereBetween('fetch_mail_sent_time', [
                $sentAt->copy()->subMinutes(5),
                $sentAt->copy()->addMinutes(5),
            ]);
        }

        return $dupQuery->exists();
    }

    protected function resolveParsedSentTime(array $parsedData): ?\Carbon\Carbon
    {
        foreach (['sent_date', 'received_date'] as $key) {
            if (empty($parsedData[$key])) {
                continue;
            }

            try {
                return $this->uploadController->parseEmailDateTimeForStorage((string) $parsedData[$key]);
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
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
     * Sync ranges for Admin / Super Admin on the Unassigned mail tab.
     *
     * @return array<string, string>
     */
    public static function adminUnassignedSyncRangeOptions(): array
    {
        return [
            '10min' => '10 minutes',
            '20min' => '20 minutes',
            '1hour' => '1 hour',
            '2hours' => '2 hours',
            '5hours' => '5 hours',
            'today' => 'Today',
            '2days' => 'Last 2 days',
            '5days' => 'Last 5 days',
            '1week' => 'Last 1 week',
            '2weeks' => 'Last 2 weeks',
            '1month' => 'Last 1 month',
            'full' => 'Full (reset & backfill)',
        ];
    }

    /**
     * Sync range choices shown on the Unassigned mail tab.
     *
     * @return array<string, string>
     */
    public static function syncRangeOptionsForUnassignedTab(Staff $staff): array
    {
        if ($staff->canViewAllSyncedInboxMail()) {
            return self::adminUnassignedSyncRangeOptions();
        }

        return [
            'today' => 'Today',
        ];
    }

    public static function isValidSyncRangeForStaff(Staff $staff, string $range): bool
    {
        $normalized = strtolower(trim($range));

        return array_key_exists($normalized, self::syncRangeOptionsForUnassignedTab($staff));
    }

    /**
     * @return array<string, string>
     */
    public static function syncRangeOptions(): array
    {
        return [
            '10min' => '10 minutes',
            '30min' => '30 minutes',
            '1hour' => '1 hrs',
            '2hours' => '2 hrs',
            '5hours' => '5 hrs',
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
            '10min', '10minutes', '10_minutes' => $now->copy()->subMinutes(10),
            '20min', '20minutes', '20_minutes' => $now->copy()->subMinutes(20),
            '30min', '30minutes', '30_minutes' => $now->copy()->subMinutes(30),
            '1hour', '1hrs', '1_hour', '1_hrs' => $now->copy()->subHours(1),
            '2hours', '2hrs', '2_hours', '2_hrs' => $now->copy()->subHours(2),
            '5hours', '5hrs', '5_hours', '5_hrs' => $now->copy()->subHours(5),
            '2days' => $now->copy()->subDays(1)->startOfDay(),
            '5days' => $now->copy()->subDays(4)->startOfDay(),
            '1week' => $now->copy()->subDays(6)->startOfDay(),
            '2weeks' => $now->copy()->subDays(13)->startOfDay(),
            '1month' => $now->copy()->subDays(29)->startOfDay(),
            default => $now->copy()->startOfDay(),
        };
    }

    /**
     * Active CRM mailboxes that may be selected for manual inbox sync.
     *
     * @return list<string>
     */
    public static function syncableMailboxAddresses(): array
    {
        return Email::query()
            ->where('status', true)
            ->where('sync_enabled', true)
            ->orderBy('email')
            ->pluck('email')
            ->map(static fn ($address) => strtolower(trim((string) $address)))
            ->filter(static fn (string $address) => $address !== '')
            ->values()
            ->all();
    }

    public static function findSyncableMailbox(string $email): ?Email
    {
        $normalized = strtolower(trim($email));
        if ($normalized === '') {
            return null;
        }

        return Email::query()
            ->where('status', true)
            ->where('sync_enabled', true)
            ->whereRaw('LOWER(email) = ?', [$normalized])
            ->first();
    }

    /**
     * @return list<string>
     */
    public static function allowedSyncMailboxAddressesForStaff(Staff $staff): array
    {
        $addresses = self::mailboxAddressesForStaff((int) $staff->id, $staff->email);
        if ($addresses === [] && trim((string) $staff->email) !== '') {
            $addresses = [strtolower(trim((string) $staff->email))];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (string $address): string => strtolower(trim($address)),
            $addresses
        ))));
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
     * Staff login email used for To/Cc visibility matching.
     *
     * @return list<string>
     */
    public static function staffLoginEmailsForSyncFilter(Staff $staff): array
    {
        $email = strtolower(trim((string) ($staff->email ?? '')));

        return $email !== '' ? [$email] : [];
    }

    /**
     * Staff login + linked mailbox addresses for synced inbox visibility.
     *
     * @return list<string>
     */
    public static function staffRecipientEmailsForSyncFilter(Staff $staff): array
    {
        $emails = self::staffLoginEmailsForSyncFilter($staff);

        foreach (self::mailboxAddressesForStaff((int) $staff->id, $staff->email) as $address) {
            $emails[] = strtolower(trim($address));
        }

        return array_values(array_unique(array_filter($emails)));
    }

    /**
     * Normalize a single parsed recipient (plain email or "Name <email>") for storage/matching.
     */
    public static function normalizeRecipientEntry(string $entry): string
    {
        $entry = trim($entry);
        if ($entry === '') {
            return '';
        }

        $lower = strtolower($entry);
        if (preg_match('/<([^>]+)>/', $lower, $matches)) {
            $lower = strtolower(trim($matches[1]));
        }

        if (filter_var($lower, FILTER_VALIDATE_EMAIL)) {
            return $lower;
        }

        if (preg_match('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $entry, $matches)) {
            return strtolower($matches[0]);
        }

        return $lower;
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
     * All Zoho-synced inbox mail (assigned + unassigned).
     */
    public static function applyAllSyncedInboxScope($query): void
    {
        if (Schema::hasColumn('email_logs', 'synced_email_id')) {
            $query->whereNotNull('synced_email_id');
        } else {
            $query->whereRaw('1 = 0');
        }
    }

    public static function countUnassignedSyncedInboxMail(Staff $staff): int
    {
        if (! Schema::hasColumn('email_logs', 'sync_assignment_status')) {
            return 0;
        }

        $query = EmailLog::query();
        self::applyUnassignedSyncedInboxScope($query);
        self::applySyncedInboxVisibilityFilter($query, $staff);

        return (int) $query->count();
    }

    /**
     * Filter synced inbox lists to mail addressed To the selected mailbox only.
     */
    public static function applySyncedMailboxListFilter($query, string $mailboxEmail): void
    {
        $email = strtolower(trim($mailboxEmail));
        if ($email === '') {
            return;
        }

        $query->where(function ($match) use ($email) {
            self::applyStaffEmailFieldMatch($match, 'to_mail', $email);
        });
    }

    /**
     * Limit synced inbox lists to mail addressed to the staff member.
     * Native Super Admin and staff with can_view_all_synced_inbox_mail see all
     * synced mail; every other role must appear in To, Cc, or Bcc.
     */
    public static function applySyncedInboxVisibilityFilter($query, Staff $staff): void
    {
        if ($staff->canViewAllSyncedInboxMail()) {
            return;
        }

        $loginEmails = self::staffLoginEmailsForSyncFilter($staff);

        if ($loginEmails === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function ($outer) use ($loginEmails) {
            foreach ($loginEmails as $email) {
                $outer->orWhere(function ($match) use ($email) {
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
            self::applyStaffEmailFieldMatch($recipient, $column, $email);
        });
    }

    protected static function applyStaffEmailFieldMatch($query, string $column, string $email): void
    {
        if (! in_array($column, ['to_mail', 'cc', 'bcc', 'from_mail'], true)) {
            return;
        }

        $columnExpr = 'LOWER(COALESCE(' . $column . ', \'\'))';

        $query->whereRaw($columnExpr . ' = ?', [$email])
            ->orWhereRaw($columnExpr . ' LIKE ?', ['%<' . $email . '>%'])
            ->orWhereRaw($columnExpr . ' LIKE ?', ['%' . $email . '%']);

        foreach ([',', ';'] as $separator) {
            $query->orWhereRaw($columnExpr . ' LIKE ?', [$email . $separator . '%'])
                ->orWhereRaw($columnExpr . ' LIKE ?', ['%' . $separator . $email . $separator . '%'])
                ->orWhereRaw($columnExpr . ' LIKE ?', ['%' . $separator . $email]);
        }
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
