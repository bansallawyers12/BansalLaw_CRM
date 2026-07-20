<?php

namespace App\Services\EmailSync;

use App\Models\Email;
use Illuminate\Support\Facades\Log;
use Throwable;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Message;

class ZohoImapFetcher
{
    /**
     * @return list<array{uid: int, raw_eml: string, subject: string}>
     */
    public function fetchNewMessages(Email $mailbox, int $afterUid, int $limit, ?array $folders = null): array
    {
        return $this->fetchFromFolders($mailbox, $afterUid, $limit, $folders ?? (array) config('imap_sync.folders', ['INBOX']));
    }

    /**
     * @return list<array{uid: int, raw_eml: string, subject: string}>
     */
    public function fetchFromFolders(Email $mailbox, int $afterUid, int $limit, array $folders): array
    {
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

                $query = $afterUid > 0
                    ? $folder->query()->where('UID', ($afterUid + 1) . ':*')->limit($limit)
                    : $folder->messages()->all()->limit($limit)->setFetchOrderDesc();

                /** @var \Webklex\PHPIMAP\Support\MessageCollection $messages */
                $messages = $query->get();
                if ($afterUid <= 0) {
                    $messages = $messages->reverse();
                }

                /** @var Message $message */
                foreach ($messages as $message) {
                    $uid = (int) $message->getUid();
                    if ($uid <= $afterUid) {
                        continue;
                    }

                    $rawEml = $this->buildRawEml($message);
                    if ($rawEml === '') {
                        continue;
                    }

                    $subject = (string) ($message->getSubject()?->toString() ?? '');

                    $results[] = [
                        'uid' => $uid,
                        'raw_eml' => $rawEml,
                        'subject' => $subject,
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

    public function testConnection(Email $mailbox): bool    {
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

        $manager = new ClientManager([]);

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
}
