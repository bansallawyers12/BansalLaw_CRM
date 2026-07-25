<?php

namespace App\Support;

class MatterCompletionChecklist
{
    public const REASON_COMPLETE = 'Complete';

    public const REASON_COMPLETED_LEGACY = 'Completed';

    /** @return list<string> */
    public static function completeReasonValues(): array
    {
        return [self::REASON_COMPLETE, self::REASON_COMPLETED_LEGACY];
    }

    public static function isCompleteReason(?string $reason): bool
    {
        return in_array(trim((string) $reason), self::completeReasonValues(), true);
    }

    /**
     * @param  mixed  $raw
     * @return array<string, bool>
     */
    public static function parseStored($raw): array
    {
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? self::normalizeInput($decoded) : [];
        }

        return is_array($raw) ? self::normalizeInput($raw) : [];
    }

    /**
     * @param  array<string, bool>  $checklist
     * @return list<string>
     */
    public static function checkedLabels(array $checklist): array
    {
        $labels = self::labels();
        $checked = [];
        foreach ($labels as $key => $label) {
            if (! empty($checklist[$key])) {
                $checked[] = $label;
            }
        }

        return $checked;
    }

    /**
     * @param  array<string, bool>  $checklist
     */
    public static function checkedCount(array $checklist): int
    {
        return count(self::checkedLabels($checklist));
    }

    public static function totalCount(): int
    {
        return count(self::labels());
    }

    /**
     * @param  object|array<string, mixed>  $matter
     */
    public static function closureStatusLabel($matter): string
    {
        $row = (array) $matter;
        $matterStatus = (int) ($row['matter_status'] ?? 1);
        $reason = trim((string) ($row['discontinue_reason'] ?? ''));

        if ($matterStatus === 0) {
            return self::isCompleteReason($reason) ? 'Complete' : 'Discontinued';
        }

        return trim((string) ($row['workflow_stage_name'] ?? '')) !== ''
            ? (string) $row['workflow_stage_name']
            : 'Closed';
    }

    /**
     * @param  object|array<string, mixed>  $matter
     */
    public static function closureStatusBadgeClass($matter): string
    {
        $row = (array) $matter;
        $matterStatus = (int) ($row['matter_status'] ?? 1);
        $reason = trim((string) ($row['discontinue_reason'] ?? ''));

        if ($matterStatus === 0) {
            return self::isCompleteReason($reason) ? 'badge-complete' : 'badge-discontinued';
        }

        return 'badge-closed';
    }

    /**
     * @param  object|array<string, mixed>  $matter
     */
    public static function displayReason($matter): string
    {
        $row = (array) $matter;
        $reason = trim((string) ($row['discontinue_reason'] ?? ''));

        if ($reason === '') {
            return '—';
        }

        if (self::isCompleteReason($reason)) {
            return 'Complete';
        }

        return $reason;
    }

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
