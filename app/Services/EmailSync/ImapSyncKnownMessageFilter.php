<?php

namespace App\Services\EmailSync;

use App\Models\Email;
use App\Models\EmailLog;

/**
 * Detects CRM rows that match IMAP candidates without downloading full message bodies.
 */
class ImapSyncKnownMessageFilter
{
    /**
     * @param  list<array{uid: int, message_id: string}>  $candidates
     * @return array<int, true> UIDs safe to skip (already represented in email_logs)
     */
    public function knownUidsWithoutBodyFetch(Email $mailbox, string $mailBodyType, array $candidates): array
    {
        if ($candidates === []) {
            return [];
        }

        $mailBodyType = $mailBodyType === 'sent' ? 'sent' : 'inbox';
        $skip = [];

        $uids = [];
        foreach ($candidates as $row) {
            $uid = (int) ($row['uid'] ?? 0);
            if ($uid > 0) {
                $uids[] = $uid;
            }
        }

        $uids = array_values(array_unique($uids));
        if ($uids !== []) {
            $knownUids = EmailLog::query()
                ->where('synced_email_id', $mailbox->id)
                ->where('mail_body_type', $mailBodyType)
                ->whereIn('imap_uid', $uids)
                ->pluck('imap_uid')
                ->map(static fn ($uid) => (int) $uid)
                ->all();

            foreach ($knownUids as $uid) {
                $skip[$uid] = true;
            }
        }

        $messageIdRows = [];
        foreach ($candidates as $row) {
            $uid = (int) ($row['uid'] ?? 0);
            if ($uid <= 0 || isset($skip[$uid])) {
                continue;
            }

            $rawMessageId = trim((string) ($row['message_id'] ?? ''));
            if ($rawMessageId === '') {
                continue;
            }

            $messageIdRows[] = [
                'uid' => $uid,
                'raw' => $rawMessageId,
                'normalized' => $this->normalizeMessageId($rawMessageId),
            ];
        }

        if ($messageIdRows === []) {
            return $skip;
        }

        $normalizedIds = [];
        $rawIds = [];
        foreach ($messageIdRows as $row) {
            if ($row['normalized'] !== '') {
                $normalizedIds[$row['normalized']] = true;
            }
            $rawIds[$row['raw']] = true;
        }

        $existingMessageIds = $this->existingMessageIdKeys(array_keys($normalizedIds), array_keys($rawIds));

        foreach ($messageIdRows as $row) {
            $keys = array_filter([
                $row['normalized'],
                $row['raw'],
                $row['normalized'] !== '' ? '<'.$row['normalized'].'>' : null,
            ]);

            foreach ($keys as $key) {
                if (isset($existingMessageIds[$key])) {
                    $skip[$row['uid']] = true;
                    break;
                }
            }
        }

        return $skip;
    }

    /**
     * @param  list<string>  $normalizedIds
     * @param  list<string>  $rawIds
     * @return array<string, true>
     */
    protected function existingMessageIdKeys(array $normalizedIds, array $rawIds): array
    {
        $variants = [];
        foreach (array_merge($normalizedIds, $rawIds) as $id) {
            $id = trim($id);
            if ($id === '') {
                continue;
            }
            $variants[$id] = true;
            $normalized = $this->normalizeMessageId($id);
            if ($normalized !== '') {
                $variants[$normalized] = true;
                $variants['<'.$normalized.'>'] = true;
            }
        }

        if ($variants === []) {
            return [];
        }

        $found = [];
        foreach (array_chunk(array_keys($variants), 100) as $chunk) {
            $rows = EmailLog::query()
                ->whereIn('message_id', $chunk)
                ->pluck('message_id');

            foreach ($rows as $stored) {
                $stored = trim((string) $stored);
                if ($stored === '') {
                    continue;
                }
                $found[$stored] = true;
                $normalized = $this->normalizeMessageId($stored);
                if ($normalized !== '') {
                    $found[$normalized] = true;
                    $found['<'.$normalized.'>'] = true;
                }
            }
        }

        return $found;
    }

    public function normalizeMessageId(string $messageId): string
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
}
