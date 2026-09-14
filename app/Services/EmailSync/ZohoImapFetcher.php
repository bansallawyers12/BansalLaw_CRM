<?php

namespace App\Services\EmailSync;

use App\Models\Email;
use Illuminate\Support\Facades\Log;
use Throwable;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\IMAP;
use Webklex\PHPIMAP\Message;
use Webklex\PHPIMAP\Query\Query;

class ZohoImapFetcher
{
    /**
     * @return list<array{uid: int, raw_eml: string, subject: string, is_seen: bool}>
     */
    public function fetchNewMessages(Email $mailbox, int $afterUid, int $limit, ?array $folders = null): array
    {
        return $this->fetchFromFolders($mailbox, $afterUid, $limit, $folders ?? (array) config('imap_sync.folders', ['INBOX']));
    }

    /**
     * @return list<array{uid: int, raw_eml: string, subject: string, is_seen: bool}>
     */
    public function fetchFromFolders(
        Email $mailbox,
        int $afterUid,
        int $limit,
        array $folders,
        ?\DateTimeInterface $since = null
    ): array {
        $password = $this->resolvePassword($mailbox);
        if ($password === '') {
            throw new \RuntimeException('Zoho app password is missing for ' . $mailbox->email);
        }

        $client = $this->connect($mailbox, $password);
        $results = [];

        try {
            // Use only the first resolvable folder. Mixing UIDs across Sent / Sent Items
            // (separate IMAP UID namespaces) corrupts last_imap_uid_sent watermarks.
            $folder = $this->resolveFirstFolder($client, $folders, (string) $mailbox->email);
            if ($folder === null) {
                throw new \RuntimeException(
                    'IMAP folder not found (tried: ' . implode(', ', array_map('strval', $folders)) . ')'
                );
            }

            // Discover candidate UIDs without bodies, then read authoritative FLAGS before any body fetch.
            $candidateUids = $this->collectCandidateUids($folder, $afterUid, $limit, $since);
            $seenByUid = $this->usePeekFetch()
                ? $this->readSeenFlagsFromClient($client, $candidateUids)
                : [];

            $query = $this->buildFolderQuery($folder, $afterUid, $limit, $since);
            $query = $this->applyPeekFetch($query);

            /** @var \Webklex\PHPIMAP\Support\MessageCollection $messages */
            $messages = $query->get();
            if ($since === null && $afterUid <= 0) {
                $messages = $messages->reverse();
            }

            /** @var Message $message */
            foreach ($messages as $message) {
                $uid = (int) $message->getUid();
                // Date-range sync may re-fetch UIDs below the incremental watermark.
                // When paginating a date window with afterUid, still require uid > afterUid.
                if ($uid <= $afterUid) {
                    continue;
                }

                if ($since !== null) {
                    try {
                        $msgDate = $message->getDate()?->toDate();
                        if ($msgDate && $msgDate->lt($since)) {
                            continue;
                        }
                    } catch (Throwable) {
                    }
                }

                // Never trust post-body hasFlag('seen'). Missing FLAGS => treat as unread (safer).
                $isSeen = $this->usePeekFetch()
                    ? (bool) ($seenByUid[$uid] ?? false)
                    : $this->messageIsSeen($message);

                $rawEml = $this->buildRawEml($message);
                if ($rawEml === '') {
                    continue;
                }

                $this->restoreUnreadStateIfNeeded($message, $isSeen);

                $subject = (string) ($message->getSubject()?->toString() ?? '');

                $results[] = [
                    'uid' => $uid,
                    'raw_eml' => $rawEml,
                    'subject' => $subject,
                    'is_seen' => $isSeen,
                ];

                if (count($results) >= $limit) {
                    break;
                }
            }
        } finally {
            try {
                $client->disconnect();
            } catch (Throwable) {
            }
        }

        usort($results, static fn (array $a, array $b) => $a['uid'] <=> $b['uid']);

        return $results;
    }

    /**
     * Open the first folder that exists for the given candidate names.
     *
     * @param  list<string>  $folderNames
     * @return mixed|null
     */
    public function resolveFirstFolder(Client $client, array $folderNames, string $mailboxEmail = ''): mixed
    {
        $candidates = [];
        foreach ($folderNames as $folderName) {
            $folderName = trim((string) $folderName);
            if ($folderName !== '' && ! in_array($folderName, $candidates, true)) {
                $candidates[] = $folderName;
            }
        }

        if ($candidates === []) {
            return null;
        }

        foreach ($candidates as $folderName) {
            $folder = $this->openFolder($client, $folderName);
            if ($folder !== null) {
                return $folder;
            }
        }

        // Case-insensitive / partial match against the live folder list (Zoho naming varies).
        try {
            $available = $client->getFolders(false, null, true);
        } catch (Throwable $e) {
            Log::warning('IMAP folder list failed during sync', [
                'mailbox' => $mailboxEmail,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $normalizedCandidates = array_map(
            static fn (string $name): string => strtolower($name),
            $candidates
        );

        foreach ($available as $folder) {
            $name = strtolower(trim((string) ($folder->name ?? '')));
            $path = strtolower(trim((string) ($folder->path ?? '')));
            $fullName = strtolower(trim((string) ($folder->full_name ?? '')));

            foreach ($normalizedCandidates as $candidate) {
                if ($name === $candidate || $path === $candidate || $fullName === $candidate) {
                    return $folder;
                }
            }
        }

        // Sent-folder fallback: match common Zoho/Outlook sent names when config missed them.
        $lookingForSent = false;
        foreach ($normalizedCandidates as $candidate) {
            if ($this->folderNameLooksLikeSent($candidate)) {
                $lookingForSent = true;
                break;
            }
        }

        if ($lookingForSent) {
            foreach ($available as $folder) {
                $name = strtolower(trim((string) ($folder->name ?? '')));
                if ($this->folderNameLooksLikeSent($name)) {
                    Log::info('Resolved Sent folder by common name fallback', [
                        'mailbox' => $mailboxEmail,
                        'folder' => (string) ($folder->path ?? $folder->name ?? ''),
                    ]);

                    return $folder;
                }
            }
        }

        Log::warning('IMAP folder not found during sync', [
            'mailbox' => $mailboxEmail,
            'tried' => $candidates,
        ]);

        return null;
    }

    protected function folderNameLooksLikeSent(string $name): bool
    {
        $name = strtolower(trim($name));

        return $name === 'sent'
            || $name === 'sent items'
            || $name === 'sent messages'
            || $name === 'sent mail'
            || str_ends_with($name, '/sent')
            || str_ends_with($name, '.sent');
    }

    /**
     * @return mixed|null
     */
    protected function openFolder(Client $client, string $folderName): mixed
    {
        try {
            // Prefer soft-fail name/path lookups so a missing Sent folder does not abort INBOX sync.
            if (method_exists($client, 'getFolderByName')) {
                $folder = $client->getFolderByName($folderName, true);
                if ($folder !== null) {
                    return $folder;
                }
            }

            if (method_exists($client, 'getFolderByPath')) {
                $folder = $client->getFolderByPath($folderName, false, true);
                if ($folder !== null) {
                    return $folder;
                }
            }

            $folder = $client->getFolder($folderName);
            if ($folder !== null) {
                return $folder;
            }
        } catch (Throwable $e) {
            Log::warning('IMAP folder open failed', [
                'folder' => $folderName,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    public function testConnection(Email $mailbox): bool
    {
        $password = $this->resolvePassword($mailbox);
        if ($password === '') {
            throw new \RuntimeException('Zoho app password is missing.');
        }

        $client = $this->connect($mailbox, $password);
        try {
            $client->getFolder('INBOX');

            return true;
        } finally {
            try {
                $client->disconnect();
            } catch (Throwable) {
            }
        }
    }

    /**
     * Fetch a single message body by IMAP UID (used for local repair/restore).
     *
     * @return array{uid: int, raw_eml: string, subject: string, folder: string}|null
     */
    public function fetchRawMessageByUid(Email $mailbox, int $uid, ?string $preferredFolder = null): ?array
    {
        if ($uid <= 0) {
            return null;
        }

        $password = $this->resolvePassword($mailbox);
        if ($password === '') {
            throw new \RuntimeException('Zoho app password is missing for ' . $mailbox->email);
        }

        $folders = [];
        if ($preferredFolder) {
            $folders[] = $preferredFolder;
        }
        foreach (array_merge(
            (array) config('imap_sync.folders', ['INBOX']),
            (array) config('imap_sync.sent_folders', ['Sent'])
        ) as $folderName) {
            $folderName = trim((string) $folderName);
            if ($folderName !== '' && ! in_array($folderName, $folders, true)) {
                $folders[] = $folderName;
            }
        }

        $client = $this->connect($mailbox, $password);

        try {
            foreach ($folders as $folderName) {
                $folder = $this->openFolder($client, $folderName);
                if ($folder === null) {
                    continue;
                }

                $message = $this->findMessageByUid($folder, $uid);
                if (! $message instanceof Message) {
                    continue;
                }

                $isSeen = $this->messageIsSeen($message);
                $rawEml = $this->buildRawEml($message);
                $this->restoreUnreadStateIfNeeded($message, $isSeen);

                if ($rawEml === '') {
                    return null;
                }

                return [
                    'uid' => (int) $message->getUid(),
                    'raw_eml' => $rawEml,
                    'subject' => (string) ($message->getSubject()?->toString() ?? ''),
                    'folder' => $folderName,
                ];
            }

            // Last resort: resolve Sent/Inbox by live folder list when configured names missed.
            $folder = $this->resolveFirstFolder($client, $folders, (string) $mailbox->email);
            if ($folder !== null) {
                $message = $this->findMessageByUid($folder, $uid);
                if ($message instanceof Message) {
                    $isSeen = $this->messageIsSeen($message);
                    $rawEml = $this->buildRawEml($message);
                    $this->restoreUnreadStateIfNeeded($message, $isSeen);
                    if ($rawEml !== '') {
                        return [
                            'uid' => (int) $message->getUid(),
                            'raw_eml' => $rawEml,
                            'subject' => (string) ($message->getSubject()?->toString() ?? ''),
                            'folder' => (string) ($folder->path ?? $folder->name ?? ''),
                        ];
                    }
                }
            }
        } finally {
            try {
                $client->disconnect();
            } catch (Throwable) {
            }
        }

        return null;
    }

    protected function connect(Email $mailbox, string $password): Client
    {
        $host = $mailbox->imap_host ?: config('imap_sync.default_host');
        $port = (int) ($mailbox->imap_port ?: config('imap_sync.default_port', 993));
        $encryption = $mailbox->imap_encryption ?: config('imap_sync.default_encryption', 'ssl');

        // Zoho rejects quoted SEARCH dates/UID ranges (BAD CLIENTBUG unexpected '"').
        // Keep RFC3501 date-text format and leave SINCE/BEFORE unquoted.
        $manager = new ClientManager([
            'date_format' => 'd-M-Y',
            'options' => $this->imapOptions(),
        ]);

        /** @var Client $client */
        $client = $manager->make([
            'host' => $host,
            'port' => $port,
            'encryption' => $encryption,
            'validate_cert' => (bool) config('imap_sync.validate_cert', true),
            'username' => $mailbox->email,
            'password' => $password,
            'protocol' => 'imap',
            'timeout' => 30,
        ]);

        $client->connect();

        return $client;
    }

    /**
     * @return array<string, mixed>
     */
    protected function imapOptions(): array
    {
        return [
            'fetch' => $this->usePeekFetch() ? IMAP::FT_PEEK : IMAP::FT_UID,
            'sequence' => IMAP::ST_UID,
            'fetch_body' => true,
            'fetch_flags' => true,
            // Prevents SINCE "11-Aug-2026" (quoted) which Zoho IMAP rejects.
            'unescaped_search_dates' => true,
        ];
    }

    protected function usePeekFetch(): bool
    {
        return (bool) config('imap_sync.use_peek_fetch', true);
    }

    /**
     * @param  Query|\Webklex\PHPIMAP\Query\WhereQuery  $query
     * @return Query|\Webklex\PHPIMAP\Query\WhereQuery
     */
    protected function applyPeekFetch($query)
    {
        if (! $this->usePeekFetch()) {
            return $query;
        }

        if (method_exists($query, 'leaveUnread')) {
            return $query->leaveUnread();
        }

        return $query;
    }

    /**
     * Build the folder query used for both UID discovery and body fetch.
     *
     * @param  mixed  $folder
     * @return Query|\Webklex\PHPIMAP\Query\WhereQuery
     */
    protected function buildFolderQuery(mixed $folder, int $afterUid, int $limit, ?\DateTimeInterface $since = null)
    {
        if ($since !== null) {
            // Ascending UID pages inside the date window. DESC + a single batch used to
            // grab only the newest N messages, advance the watermark, and permanently
            // skip older UIDs in the same day/range (common for Sent).
            $query = $folder->query()->since($since)->limit($limit);
            if ($afterUid > 0) {
                $query->where('CUSTOM UID ' . ($afterUid + 1) . ':*');
            }
            if (method_exists($query, 'setFetchOrderAsc')) {
                return $query->setFetchOrderAsc();
            }

            return $query;
        }

        if ($afterUid > 0) {
            // CUSTOM avoids webklex quoting the range as UID "n:*" (Zoho BAD CLIENTBUG).
            return $folder->query()
                ->where('CUSTOM UID ' . ($afterUid + 1) . ':*')
                ->limit($limit);
        }

        return $folder->messages()->all()->limit($limit)->setFetchOrderDesc();
    }

    /**
     * Discover candidate UIDs without fetching bodies.
     *
     * @return list<int>
     */
    protected function collectCandidateUids(
        mixed $folder,
        int $afterUid,
        int $limit,
        ?\DateTimeInterface $since = null
    ): array {
        if (! $this->usePeekFetch()) {
            return [];
        }

        try {
            $query = $this->applyPeekFetch($this->buildFolderQuery($folder, $afterUid, $limit, $since));
            if (method_exists($query, 'setFetchBody')) {
                $query->setFetchBody(false);
            }

            /** @var \Webklex\PHPIMAP\Support\MessageCollection $messages */
            $messages = $query->get();
            $uids = [];

            /** @var Message $message */
            foreach ($messages as $message) {
                $uid = (int) $message->getUid();
                if ($uid <= 0) {
                    continue;
                }
                if ($since === null && $uid <= $afterUid) {
                    continue;
                }

                if ($since !== null) {
                    try {
                        $msgDate = $message->getDate()?->toDate();
                        if ($msgDate && $msgDate->lt($since)) {
                            continue;
                        }
                    } catch (Throwable) {
                    }
                }

                $uids[] = $uid;
            }

            return array_values(array_unique($uids));
        } catch (Throwable $e) {
            Log::warning('IMAP candidate UID discovery failed; treating messages as unread for restore', [
                'after_uid' => $afterUid,
                'limit' => $limit,
                'since' => $since?->format('c'),
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Authoritative FLAGS-only Seen map (no body fetch).
     *
     * @param  list<int>  $uids
     * @return array<int, bool> uid => was seen before body fetch
     */
    protected function readSeenFlagsFromClient(Client $client, array $uids): array
    {
        $uids = array_values(array_unique(array_filter(array_map('intval', $uids), static fn (int $uid): bool => $uid > 0)));
        if ($uids === []) {
            return [];
        }

        $seen = [];

        try {
            foreach (array_chunk($uids, 100) as $chunk) {
                $flagsByUid = $client->getConnection()->flags($chunk, IMAP::ST_UID)->validatedData();
                foreach ($chunk as $uid) {
                    // Missing flag payload => treat as unread (safer than leaving wrongly Read).
                    $seen[$uid] = $this->flagsIndicateSeen($flagsByUid[$uid] ?? []);
                }
            }
        } catch (Throwable $e) {
            Log::warning('IMAP FLAGS read failed; treating messages as unread for restore', [
                'uids' => $uids,
                'error' => $e->getMessage(),
            ]);

            foreach ($uids as $uid) {
                $seen[$uid] = false;
            }
        }

        return $seen;
    }

    protected function messageIsSeen(Message $message): bool
    {
        try {
            return $message->hasFlag('seen');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * If the message was unread before sync, clear server \Seen after body fetch.
     * Already-read mail (FLAGS said Seen) is left untouched.
     */
    protected function restoreUnreadStateIfNeeded(Message $message, bool $wasSeenBeforeFetch): void
    {
        if (! $this->usePeekFetch() || $wasSeenBeforeFetch) {
            return;
        }

        $lastError = null;

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                if (method_exists($message, 'unsetFlag')) {
                    $message->unsetFlag('Seen');

                    return;
                }

                if (method_exists($message, 'peek')) {
                    $message->peek();

                    return;
                }

                return;
            } catch (Throwable $e) {
                $lastError = $e;
                if ($attempt < 2) {
                    usleep(100000);
                }
            }
        }

        Log::warning('IMAP unread restore failed', [
            'uid' => $message->getUid(),
            'error' => $lastError?->getMessage(),
        ]);
    }

    protected function buildRawEml(Message $message): string
    {
        $header = $message->getHeader();
        $rawHeader = $header?->raw ?? '';
        $rawBody = $message->getRawBody();

        if ($rawHeader === '' && $rawBody === '') {
            return '';
        }

        return rtrim($rawHeader, "\r\n") . "\r\n\r\n" . ltrim($rawBody, "\r\n");
    }

    protected function resolvePassword(Email $mailbox): string
    {
        $password = (string) ($mailbox->password ?? '');
        if ($password === '') {
            return '';
        }

        try {
            return (string) decrypt($password);
        } catch (Throwable) {
            return $password;
        }
    }

    /**
     * Delete messages by IMAP UID from one folder. Returns counts only.
     *
     * @param  list<int>  $uids
     * @return array{deleted: int, missing: int, failed: int, errors: list<string>}
     */
    public function deleteMessagesByUids(Email $mailbox, array $uids, string $folderName): array
    {
        $uids = array_values(array_unique(array_filter(array_map('intval', $uids), static fn (int $uid): bool => $uid > 0)));
        $result = [
            'deleted' => 0,
            'missing' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        if ($uids === []) {
            return $result;
        }

        $password = $this->resolvePassword($mailbox);
        if ($password === '') {
            throw new \RuntimeException('Zoho app password is missing for ' . $mailbox->email);
        }

        $client = $this->connect($mailbox, $password);

        try {
            $folder = $this->resolveFirstFolder($client, [$folderName], (string) $mailbox->email);
            if ($folder === null) {
                $result['errors'][] = 'IMAP folder not found: ' . $folderName;
                $result['failed'] = count($uids);

                return $result;
            }

            if (method_exists($client, 'openFolder') && ! empty($folder->path)) {
                $client->openFolder($folder->path);
            }

            foreach (array_chunk($uids, 40) as $chunk) {
                foreach ($chunk as $uid) {
                    try {
                        $message = $this->findMessageByUid($folder, $uid);
                        if ($message === null) {
                            $result['missing']++;
                            continue;
                        }

                        if (method_exists($message, 'delete')) {
                            $message->delete(true);
                        } elseif (method_exists($message, 'setFlag')) {
                            $message->setFlag('Deleted');
                            if (method_exists($folder, 'expunge')) {
                                $folder->expunge();
                            }
                        } else {
                            throw new \RuntimeException('IMAP delete is not supported by the installed php-imap client.');
                        }

                        $result['deleted']++;
                    } catch (Throwable $e) {
                        $result['failed']++;
                        $result['errors'][] = 'UID ' . $uid . ': ' . $e->getMessage();
                        Log::warning('IMAP message delete failed', [
                            'mailbox' => $mailbox->email,
                            'folder' => $folderName,
                            'uid' => $uid,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        } finally {
            try {
                $client->disconnect();
            } catch (Throwable) {
            }
        }

        return $result;
    }

    /**
     * @param  mixed  $folder
     */
    protected function findMessageByUid(mixed $folder, int $uid): ?Message
    {
        if (method_exists($folder, 'query')) {
            try {
                $messages = $folder->query()->where('CUSTOM UID ' . $uid)->limit(1)->get();
                $message = $messages->first();
                if ($message instanceof Message) {
                    return $message;
                }
            } catch (Throwable) {
            }

            try {
                if (method_exists($folder->query(), 'whereUid')) {
                    $messages = $folder->query()->whereUid($uid)->limit(1)->get();
                    $message = $messages->first();
                    if ($message instanceof Message) {
                        return $message;
                    }
                }
            } catch (Throwable) {
            }
        }

        if (method_exists($folder, 'messages') && method_exists($folder->messages(), 'getMessageByUid')) {
            try {
                $message = $folder->messages()->getMessageByUid($uid);
                if ($message instanceof Message) {
                    return $message;
                }
            } catch (Throwable) {
            }
        }

        return null;
    }

    /**
     * Read IMAP \Seen state for specific message UIDs in one folder (no body fetch).
     *
     * @param  list<int>  $uids
     * @return array<int, bool>
     */
    public function fetchSeenFlagsForUids(Email $mailbox, array $uids, string $folderName): array
    {
        $uids = array_values(array_unique(array_filter(array_map('intval', $uids), static fn (int $uid): bool => $uid > 0)));
        if ($uids === []) {
            return [];
        }

        $password = $this->resolvePassword($mailbox);
        if ($password === '') {
            throw new \RuntimeException('Zoho app password is missing for ' . $mailbox->email);
        }

        $client = $this->connect($mailbox, $password);
        $seen = [];

        try {
            $folder = $this->resolveFirstFolder($client, [$folderName], (string) $mailbox->email);
            if ($folder === null) {
                Log::warning('IMAP folder not found while reading seen flags', [
                    'mailbox' => $mailbox->email,
                    'folder' => $folderName,
                ]);

                return [];
            }

            if (method_exists($client, 'openFolder') && ! empty($folder->path)) {
                $client->openFolder($folder->path);
            }

            foreach (array_chunk($uids, 100) as $chunk) {
                $flagsByUid = $client->getConnection()->flags($chunk, IMAP::ST_UID)->validatedData();
                foreach ($chunk as $uid) {
                    $seen[$uid] = $this->flagsIndicateSeen($flagsByUid[$uid] ?? []);
                }
            }
        } finally {
            try {
                $client->disconnect();
            } catch (Throwable) {
            }
        }

        return $seen;
    }

    /**
     * @param  array<int|string, mixed>|mixed  $flags
     */
    protected function flagsIndicateSeen(mixed $flags): bool
    {
        if (! is_array($flags)) {
            return false;
        }

        foreach ($flags as $flag) {
            $normalized = strtolower(ltrim(trim((string) $flag), '\\'));
            if ($normalized === 'seen') {
                return true;
            }
        }

        return false;
    }
}
