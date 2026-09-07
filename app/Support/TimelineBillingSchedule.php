<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Super Admin–managed fee schedule for Timeline billing.
 * Amounts are GST-inclusive and applied per matching timeline category.
 */
class TimelineBillingSchedule
{
    public const STORAGE_RELATIVE = 'crm/timeline_billing_rates.json';

    /**
     * Default structure: key => label, applies_to, amount_incl_gst.
     *
     * @return array<string, array{label: string, applies_to: string, amount_incl_gst: float}>
     */
    public static function defaults(): array
    {
        $fallback = (float) config('crm.timeline_billing.unit_rate_incl_gst', 55);

        $configured = config('crm.timeline_billing.categories', []);
        if (is_array($configured) && $configured !== []) {
            $out = [];
            foreach ($configured as $key => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $out[(string) $key] = [
                    'label' => (string) ($row['label'] ?? ucfirst((string) $key)),
                    'applies_to' => (string) ($row['applies_to'] ?? ''),
                    'amount_incl_gst' => round((float) ($row['amount_incl_gst'] ?? $fallback), 2),
                ];
            }
            if ($out !== []) {
                return $out;
            }
        }

        return [
            'email' => [
                'label' => 'Email',
                'applies_to' => 'Emails and email-related notes',
                'amount_incl_gst' => $fallback,
            ],
            'note' => [
                'label' => 'Note',
                'applies_to' => 'Client notes (non-email)',
                'amount_incl_gst' => $fallback,
            ],
            'document' => [
                'label' => 'Document',
                'applies_to' => 'Document uploads and checklist items',
                'amount_incl_gst' => $fallback,
            ],
            'signature' => [
                'label' => 'Signature',
                'applies_to' => 'Signature / signing activity',
                'amount_incl_gst' => $fallback,
            ],
            'activity' => [
                'label' => 'Task / Action',
                'applies_to' => 'Tasks and actions on the file',
                'amount_incl_gst' => $fallback,
            ],
            'sms' => [
                'label' => 'SMS',
                'applies_to' => 'SMS messages',
                'amount_incl_gst' => $fallback,
            ],
            'search' => [
                'label' => 'Search / attending',
                'applies_to' => 'Searching, attending, InfoTrack-style work',
                'amount_incl_gst' => round($fallback * 2, 2),
            ],
            'review' => [
                'label' => 'Review',
                'applies_to' => 'Reviewing documents or orders',
                'amount_incl_gst' => round($fallback * 2, 2),
            ],
        ];
    }

    /**
     * @return array<string, array{label: string, applies_to: string, amount_incl_gst: float}>
     */
    public static function all(): array
    {
        $defaults = self::defaults();
        $saved = self::readSavedAmounts();

        foreach ($defaults as $key => &$row) {
            if (array_key_exists($key, $saved)) {
                $row['amount_incl_gst'] = round((float) $saved[$key], 2);
            }
        }
        unset($row);

        return $defaults;
    }

    /**
     * @return array<int, array{key: string, label: string, applies_to: string, amount_incl_gst: float}>
     */
    public static function structure(): array
    {
        $rows = [];
        foreach (self::all() as $key => $row) {
            $rows[] = [
                'key' => $key,
                'label' => $row['label'],
                'applies_to' => $row['applies_to'],
                'amount_incl_gst' => $row['amount_incl_gst'],
            ];
        }

        return $rows;
    }

    public static function amountFor(string $key): float
    {
        $all = self::all();

        return isset($all[$key]) ? (float) $all[$key]['amount_incl_gst'] : 0.0;
    }

    /**
     * Resolve category key from activity type + subject (same rules as the Timeline UI).
     */
    public static function resolveCategory(?string $activityType, ?string $subject): ?string
    {
        $type = strtolower(trim((string) $activityType));
        $text = (string) $subject;

        if ($type === 'stage' || $type === 'lead_converted' || $type === 'financial') {
            return null;
        }
        if (preg_match('/^(?:stage updated|lead converted|pin(?:ned)?|unpinned?)\b/i', $text)) {
            return null;
        }
        if (preg_match('/\bemail\b/i', $text) || str_contains($type, 'note-email')) {
            return 'email';
        }
        if (preg_match('/\b(?:search|attending to|infotrack)\b/i', $text)) {
            return 'search';
        }
        if (preg_match('/\breview(?:ing|ed)?\b/i', $text)) {
            return 'review';
        }
        if ($type === 'sms' || str_starts_with($type, 'sms')) {
            return 'sms';
        }
        if ($type === 'document' || str_starts_with($type, 'document')) {
            return 'document';
        }
        if ($type === 'signature' || str_starts_with($type, 'signature')) {
            return 'signature';
        }
        if ($type === 'note' || str_starts_with($type, 'note')) {
            return 'note';
        }
        if ($type === 'activity' || $type === '' || preg_match('/\b(?:action|task)\b/i', $text)) {
            return 'activity';
        }

        return null;
    }

    /**
     * @param  array<string, float|int|string>  $amounts  key => amount_incl_gst
     * @return array<string, array{label: string, applies_to: string, amount_incl_gst: float}>
     */
    public static function saveAmounts(array $amounts): array
    {
        $defaults = self::defaults();
        $payload = [];

        foreach ($defaults as $key => $row) {
            if (! array_key_exists($key, $amounts)) {
                $payload[$key] = round((float) $row['amount_incl_gst'], 2);
                continue;
            }
            $payload[$key] = round(max(0, (float) $amounts[$key]), 2);
        }

        $path = self::storagePath();
        $dir = dirname($path);
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        File::put($path, json_encode([
            'updated_at' => now()->toIso8601String(),
            'amounts' => $payload,
        ], JSON_PRETTY_PRINT));

        return self::all();
    }

    /**
     * @return array<string, float>
     */
    private static function readSavedAmounts(): array
    {
        $path = self::storagePath();
        if (! File::exists($path)) {
            return [];
        }

        try {
            $decoded = json_decode((string) File::get($path), true);
        } catch (\Throwable $e) {
            return [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        $amounts = $decoded['amounts'] ?? $decoded;
        if (! is_array($amounts)) {
            return [];
        }

        $out = [];
        foreach ($amounts as $key => $value) {
            if (is_numeric($value)) {
                $out[(string) $key] = round((float) $value, 2);
            }
        }

        return $out;
    }

    public static function storagePath(): string
    {
        return storage_path('app/'.self::STORAGE_RELATIVE);
    }
}
