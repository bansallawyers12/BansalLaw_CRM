<?php

namespace App\Services\EmailSync;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class InboxSyncStatusStore
{
    private const TTL_SECONDS = 3600;

    /**
     * @param  array<string, mixed>  $meta
     */
    public function create(int $staffId, array $meta = []): string
    {
        $syncId = (string) Str::uuid();

        Cache::put($this->key($syncId), array_merge([
            'sync_id' => $syncId,
            'staff_id' => $staffId,
            'status' => 'pending',
            'message' => null,
            'summary' => null,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ], $meta), self::TTL_SECONDS);

        Cache::put($this->activeKey($staffId), $syncId, self::TTL_SECONDS);

        return $syncId;
    }

    public function getActiveSyncId(int $staffId): ?string
    {
        $syncId = Cache::get($this->activeKey($staffId));

        return is_string($syncId) && $syncId !== '' ? $syncId : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $syncId, int $staffId): ?array
    {
        $data = Cache::get($this->key($syncId));
        if (! is_array($data) || (int) ($data['staff_id'] ?? 0) !== $staffId) {
            return null;
        }

        return $data;
    }

    public function markRunning(string $syncId): void
    {
        $this->update($syncId, ['status' => 'running']);
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    public function markCompleted(string $syncId, array $summary): void
    {
        $data = Cache::get($this->key($syncId));
        if (! is_array($data)) {
            return;
        }

        $this->update($syncId, [
            'status' => 'completed',
            'summary' => $summary,
            'message' => self::buildResultMessage($summary),
        ]);

        Cache::forget($this->activeKey((int) $data['staff_id']));
    }

    /**
     * @param  array<string, mixed>|null  $summary
     */
    public function markFailed(string $syncId, string $message, ?array $summary = null): void
    {
        $data = Cache::get($this->key($syncId));
        if (! is_array($data)) {
            return;
        }

        $this->update($syncId, [
            'status' => 'failed',
            'message' => $message,
            'summary' => $summary,
        ]);

        Cache::forget($this->activeKey((int) $data['staff_id']));
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    public static function buildResultMessage(array $summary): string
    {
        $syncRange = (string) ($summary['sync_range'] ?? '');
        $isPurgeOnly = IncomingEmailSyncService::isPurgeOnlySyncRange($syncRange);
        $purged = (int) ($summary['purged_unassigned_before_cutoff'] ?? 0);

        if ($isPurgeOnly) {
            $cutoff = (string) ($summary['unassigned_available_from'] ?? '');
            $detail = 'Deleted unassigned from CRM: ' . $purged
                . ($cutoff !== '' ? ' (before ' . $cutoff . ')' : '');

            if (IncomingEmailSyncService::purgeRangeDeletesFromImap($syncRange)) {
                $detail .= ', Deleted from Zoho: ' . (int) ($summary['imap_deleted'] ?? 0);
                $imapMissing = (int) ($summary['imap_missing'] ?? 0);
                $imapFailed = (int) ($summary['imap_failed'] ?? 0);
                if ($imapMissing > 0) {
                    $detail .= ', Missing on Zoho: ' . $imapMissing;
                }
                if ($imapFailed > 0) {
                    $detail .= ', Zoho delete failed: ' . $imapFailed;
                }
            }
        } else {
            $detail = 'Imported: ' . (int) ($summary['total_imported'] ?? 0)
                . ', Skipped: ' . (int) ($summary['total_skipped'] ?? 0)
                . ', Failed: ' . (int) ($summary['total_failed'] ?? 0);

            if ($purged > 0) {
                $cutoff = (string) ($summary['unassigned_available_from'] ?? '');
                $detail .= ', Deleted older unassigned: ' . $purged
                    . ($cutoff !== '' ? ' (before ' . $cutoff . ')' : '');
            }
        }

        $mailboxErrors = [];
        $mailboxes = $summary['mailboxes'] ?? [];
        if (is_array($mailboxes)) {
            foreach ($mailboxes as $mailboxKey => $result) {
                if (! is_array($result) || empty($result['errors']) || ! is_array($result['errors'])) {
                    continue;
                }
                $mailboxErrors[] = $mailboxKey . ': ' . implode('; ', $result['errors']);
            }
        }

        if ($mailboxErrors !== []) {
            $detail .= '. ' . implode(' | ', $mailboxErrors);
        }

        return $detail;
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function update(string $syncId, array $changes): void
    {
        $data = Cache::get($this->key($syncId));
        if (! is_array($data)) {
            return;
        }

        Cache::put(
            $this->key($syncId),
            array_merge($data, $changes, ['updated_at' => now()->toIso8601String()]),
            self::TTL_SECONDS
        );
    }

    private function key(string $syncId): string
    {
        return 'inbox_sync_status:' . $syncId;
    }

    private function activeKey(int $staffId): string
    {
        return 'inbox_sync_active:' . $staffId;
    }
}
