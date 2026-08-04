<?php

namespace App\Services\EmailSync;

use App\Models\Staff;
use App\Services\CrmAccess\CrmAccessService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Super Admin master switch for Zoho inbox sync (cron + manual + Unassigned Mail UI).
 *
 * Runtime state is stored in cache and a small file so a toggle applies on the
 * next request / schedule:run without waiting for config:cache or deploy.
 */
class InboxSyncMasterControl
{
    public const CACHE_KEY = 'inbox_sync.master_enabled';

    public const FILE_RELATIVE = 'runtime/inbox_sync_master.json';

    public static function isEnabled(): bool
    {
        $override = self::readOverride();
        if ($override !== null) {
            return $override;
        }

        return (bool) config('imap_sync.enabled', true);
    }

    public static function isDisabled(): bool
    {
        return ! self::isEnabled();
    }

    public static function disabledMessage(): string
    {
        return 'Inbox auto-sync is turned off by Super Admin. Automatic fetch, manual sync, and Unassigned Mail are unavailable until it is turned back on.';
    }

    /**
     * Only native Super Admin (role 1) or permanent super-admin grant may change this.
     */
    public static function canControl(?Staff $staff): bool
    {
        if (! $staff instanceof Staff) {
            return false;
        }

        return app(CrmAccessService::class)->hasPermanentSuperAdminCapability($staff);
    }

    /**
     * @return array{enabled: bool, source: string, updated_at: string|null, updated_by: int|null}
     */
    public static function statusPayload(): array
    {
        $meta = self::readMeta();
        $override = self::readOverride();

        return [
            'enabled' => self::isEnabled(),
            'source' => $override === null
                ? 'config'
                : ($override ? 'superadmin_on' : 'superadmin_off'),
            'updated_at' => $meta['updated_at'] ?? null,
            'updated_by' => isset($meta['updated_by']) ? (int) $meta['updated_by'] : null,
            'config_default' => (bool) config('imap_sync.enabled', true),
        ];
    }

    public static function setEnabled(bool $enabled, ?Staff $actor = null): void
    {
        $payload = [
            'enabled' => $enabled,
            'updated_at' => now()->toIso8601String(),
            'updated_by' => $actor?->id,
        ];

        try {
            Cache::forever(self::CACHE_KEY, $payload);
        } catch (\Throwable $e) {
            Log::warning('InboxSyncMasterControl: failed to write cache', [
                'error' => $e->getMessage(),
            ]);
        }

        $path = self::filePath();
        $dir = dirname($path);
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents(
            $path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );

        Log::info('Inbox sync master switch updated', [
            'enabled' => $enabled,
            'staff_id' => $actor?->id,
        ]);
    }

    protected static function readOverride(): ?bool
    {
        $meta = self::readMeta();
        if ($meta === null || ! array_key_exists('enabled', $meta)) {
            return null;
        }

        return (bool) $meta['enabled'];
    }

    /**
     * @return array{enabled?: bool, updated_at?: string|null, updated_by?: int|null}|null
     */
    protected static function readMeta(): ?array
    {
        try {
            $cached = Cache::get(self::CACHE_KEY);
            if (is_array($cached) && array_key_exists('enabled', $cached)) {
                return $cached;
            }
            // Legacy: plain bool was stored briefly in early drafts.
            if (is_bool($cached)) {
                return ['enabled' => $cached, 'updated_at' => null, 'updated_by' => null];
            }
        } catch (\Throwable) {
            // Fall through to file.
        }

        $path = self::filePath();
        if (! is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded) || ! array_key_exists('enabled', $decoded)) {
            return null;
        }

        return $decoded;
    }

    protected static function filePath(): string
    {
        return storage_path('app/' . self::FILE_RELATIVE);
    }
}
