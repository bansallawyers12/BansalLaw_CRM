<?php

namespace App\Support;

class MatterCompletionChecklist
{
    public const REASON_COMPLETE = 'Complete';

    /** @return array<string, string> */
    public static function labels(): array
    {
        return config('matter_completion.checklist', []);
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::labels());
    }

    /**
     * @param  mixed  $input
     * @return array<string, bool>
     */
    public static function normalizeInput($input): array
    {
        if (! is_array($input)) {
            return [];
        }

        $normalized = [];
        foreach (self::keys() as $key) {
            $normalized[$key] = filter_var($input[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
        }

        return $normalized;
    }

    /**
     * @param  array<string, bool>  $checklist
     */
    public static function allChecked(array $checklist): bool
    {
        foreach (self::keys() as $key) {
            if (empty($checklist[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, bool>  $checklist
     */
    public static function toHtmlSummary(array $checklist): string
    {
        $labels = self::labels();
        $lines = [];
        foreach ($labels as $key => $label) {
            if (! empty($checklist[$key])) {
                $lines[] = e($label);
            }
        }

        return implode('<br>', $lines);
    }
}
