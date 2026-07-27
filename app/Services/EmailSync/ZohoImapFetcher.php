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
            foreach ($folders as $folderName) {
                $folder = $client->getFolder($folderName);
                if ($folder === null) {
                    Log::warning('IMAP folder not found during sync', [
                        'mailbox' => $mailbox->email,
                        'folder' => $folderName,
                    ]);
                    continue;
                }

                if ($since !== null) {
                    $query = $this->applyPeekFetch($folder->query()->since($since)->limit($limit)->setFetchOrderDesc());
                } elseif ($afterUid > 0) {
                    $query = $this->applyPeekFetch($folder->query()->where('UID', ($afterUid + 1) . ':*')->limit($limit));
                } else {
                    $query = $this->applyPeekFetch($folder->messages()->all()->limit($limit)->setFetchOrderDesc());
                }

                /** @var \Webklex\PHPIMAP\Support\MessageCollection $messages */
                $messages = $query->get();
                if ($since === null && $afterUid <= 0) {
                    $messages = $messages->reverse();
                }

                /** @var Message $message */
                foreach ($messages as $message) {
                    $uid = (int) $message->getUid();
                    // Date-range sync re-fetches messages that may have UIDs below the watermark.
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

                    $isSeen = $this->messageIsSeen($message);

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
                        break 2;
                    }
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

    protected function connect(Email $mailbox, string $password): Client
    {
        $host = $mailbox->imap_host ?: config('imap_sync.default_host');
        $port = (int) ($mailbox->imap_port ?: config('imap_sync.default_port', 993));
        $encryption = $mailbox->imap_encryption ?: config('imap_sync.default_encryption', 'ssl');

        $manager = new ClientManager([
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

    protected function messageIsSeen(Message $message): bool
    {
        try {
            return $message->hasFlag('seen');
        } catch (Throwable) {
            return false;
        }
    }

    protected function restoreUnreadStateIfNeeded(Message $message, bool $wasSeenBeforeFetch): void
    {
        if (! $this->usePeekFetch() || $wasSeenBeforeFetch) {
            return;
        }

        try {
            if (method_exists($message, 'peek')) {
                $message->peek();
            }
        } catch (Throwable $e) {
            Log::debug('IMAP peek restore skipped', [
                'uid' => $message->getUid(),
                'error' => $e->getMessage(),
            ]);
        }
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
            $folder = $client->getFolder($folderName);
            if ($folder === null) {
                Log::warning('IMAP folder not found while reading seen flags', [
                    'mailbox' => $mailbox->email,
                    'folder' => $folderName,
                ]);

                return [];
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
}
