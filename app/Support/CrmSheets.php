<?php

namespace App\Support;

/**
 * CRM sheet identifiers (keys match routes / staff.sheet_access JSON).
 */
class CrmSheets
{
    public const KEY_ART = 'art';

    /**
     * @return array<string, string> sheet_key => display label
     */
    public static function definitions(): array
    {
        return [
            self::KEY_ART => 'ART Submission and Hearing Files',
        ];
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
        if ($key === self::KEY_ART) {
            return route('clients.sheets.art');
        }

        return url('/dashboard');
    }
}
