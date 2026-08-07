<?php

namespace App\Services\CalendarSync;

use App\Models\Staff;
use App\Services\CrmAccess\CrmAccessService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Super Admin master switch for Zoho calendar sync (CRM ↔ Zoho / Outlook-via-Zoho).
 *
 * Runtime state is cache + file so toggles apply immediately without redeploy.
 */
class CalendarSyncMasterControl
{
    public const CACHE_KEY = 'calendar_sync.master_enabled';

    public const FILE_RELATIVE = 'runtime/calendar_sync_master.json';

    public static function isEnabled(): bool
    {
        $override = self::readOverride();
        if ($override !== null) {
            return $override;
        }

        return (bool) config('zoho_calendar.enabled', false);
    }

    public static function isDisabled(): bool
    {
        return ! self::isEnabled();
    }

    public static function disabledMessage(): string
    {
        return 'Zoho calendar sync is turned off by Super Admin. CRM will not push or pull calendar events until it is turned back on.';
    }

    public static function canControl(?Staff $staff): bool
    {
        if (! $staff instanceof Staff) {
            return false;
        }

        return app(CrmAccessService::class)->hasPermanentSuperAdminCapability($staff);
    }

    /**
     * @return array{enabled: bool, source: string, updated_at: string|null, updated_by: int|null, config_default: bool}
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
            'config_default' => (bool) config('zoho_calendar.enabled', false),
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
            Log::warning('CalendarSyncMasterControl: failed to write cache', [
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

        Log::info('Calendar sync master switch updated', [
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
            if (is_bool($cached)) {
                return ['enabled' => $cached, 'updated_at' => null, 'updated_by' => null];
            }
        } catch (\Throwable) {
            // fall through to file
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
