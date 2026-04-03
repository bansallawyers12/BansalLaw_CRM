<?php

namespace App\Support;

/**
 * CRM sheet identifiers (keys match routes / staff.sheet_access JSON).
 */
class CrmSheets
{
    public const KEY_EOI_ROI = 'eoi-roi';

    public const KEY_ART = 'art';

    /**
     * @return array<string, string> sheet_key => display label
     */
    public static function definitions(): array
    {
        $def = [
            self::KEY_EOI_ROI => 'EOI/ROI Sheet',
            self::KEY_ART => 'ART Submission and Hearing Files',
        ];
        foreach (config('sheets.visa_types', []) as $key => $cfg) {
            $def[(string) $key] = $cfg['title'] ?? ucfirst((string) $key);
        }

        return $def;
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::definitions());
    }

    public static function urlForKey(string $key): string
    {
        if (! array_key_exists($key, self::definitions())) {
            return url('/dashboard');
        }
        if ($key === self::KEY_EOI_ROI) {
            return route('clients.sheets.eoi-roi');
        }
        if ($key === self::KEY_ART) {
            return route('clients.sheets.art');
        }

        return route('clients.sheets.visa-type', ['visaType' => $key]);
    }
}
