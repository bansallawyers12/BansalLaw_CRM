<?php

namespace App\Support;

use App\Models\ActivitiesLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Single entry point for client Timeline rows (activities_logs).
 *
 * Prefer this over ad-hoc ActivitiesLog::create() so every staff action
 * for a client is recorded the same way.
 */
final class ClientActivity
{
    public const TYPE_ACTIVITY = 'activity';

    public const TYPE_NOTE = 'note';

    public const TYPE_DOCUMENT = 'document';

    public const TYPE_EMAIL = 'email';

    public const TYPE_SMS = 'sms';

    public const TYPE_STAGE = 'stage';

    public const TYPE_SIGNATURE = 'signature';

    public const TYPE_FINANCIAL = 'financial';

    public const TYPE_LEAD_CONVERTED = 'lead_converted';

    /** Extra columns allowed on activities_logs besides the core four. */
    private const EXTRA_KEYS = [
        'created_by',
        'sms_log_id',
        'source',
        'use_for',
        'followup_date',
        'task_group',
        'task_status',
        'pin',
    ];

    /**
     * @param  array<string, mixed>  $extra
     */
    public static function log(
        int $clientId,
        string $subject,
        string $activityType = self::TYPE_ACTIVITY,
        string $description = '',
        array $extra = []
    ): ?ActivitiesLog {
        if ($clientId < 1) {
            return null;
        }

        $subject = trim($subject);
        if ($subject === '') {
            return null;
        }

        $createdBy = $extra['created_by'] ?? Auth::guard('admin')->id() ?? Auth::id();
        if ($createdBy === null || (int) $createdBy < 1) {
            Log::warning('ClientActivity: skipped log — no staff id', [
                'client_id' => $clientId,
                'subject' => $subject,
            ]);

            return null;
        }

        $attrs = [
            'client_id' => $clientId,
            'created_by' => (int) $createdBy,
            'subject' => $subject,
            'description' => $description,
            'activity_type' => $activityType !== '' ? $activityType : self::TYPE_ACTIVITY,
            'task_status' => array_key_exists('task_status', $extra) ? $extra['task_status'] : 0,
            'pin' => array_key_exists('pin', $extra) ? $extra['pin'] : 0,
        ];

        foreach (self::EXTRA_KEYS as $key) {
            if ($key === 'created_by' || $key === 'task_status' || $key === 'pin') {
                continue;
            }
            if (array_key_exists($key, $extra) && $extra[$key] !== null) {
                $attrs[$key] = $extra[$key];
            }
        }

        try {
            return ActivitiesLog::create($attrs);
        } catch (\Throwable $e) {
            Log::warning('ClientActivity: failed to write timeline row', [
                'client_id' => $clientId,
                'subject' => $subject,
                'activity_type' => $attrs['activity_type'],
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<int|string, mixed>  $changedFields
     * @param  array<string, mixed>  $extra
     */
    public static function logWithChanges(
        int $clientId,
        string $subject,
        array $changedFields = [],
        string $activityType = self::TYPE_ACTIVITY,
        string $descriptionPrefix = '',
        array $extra = []
    ): ?ActivitiesLog {
        $description = self::buildChangesDescription($changedFields);
        if ($descriptionPrefix !== '') {
            $description = $descriptionPrefix.$description;
        }

        return self::log($clientId, $subject, $activityType, $description, $extra);
    }

    /**
     * @param  array<int|string, mixed>  $changedFields
     */
    public static function buildChangesDescription(array $changedFields): string
    {
        if ($changedFields === []) {
            return '';
        }

        $firstKey = array_key_first($changedFields);
        $hasDetailedChanges = is_array($changedFields[$firstKey] ?? null)
            && array_key_exists('old', $changedFields[$firstKey])
            && array_key_exists('new', $changedFields[$firstKey]);

        if ($hasDetailedChanges) {
            $description = '<div style="margin-top: 5px;">';
            foreach ($changedFields as $fieldName => $change) {
                if (! is_array($change)) {
                    continue;
                }
                $oldValue = self::formatValue($change['old'] ?? null);
                $newValue = self::formatValue($change['new'] ?? null);
                $description .= '<div style="margin-bottom: 8px;">';
                $description .= '<strong>'.htmlspecialchars((string) $fieldName, ENT_QUOTES, 'UTF-8').':</strong> ';
                $description .= '<span style="color: #dc3545; text-decoration: line-through;">'.$oldValue.'</span> ';
                $description .= '<span style="color: #666;">→</span> ';
                $description .= '<span style="color: #28a745; font-weight: 600;">'.$newValue.'</span>';
                $description .= '</div>';
            }
            $description .= '</div>';

            return $description;
        }

        $fields = array_values(array_map('strval', $changedFields));
        $fieldCount = count($fields);
        if ($fieldCount === 1) {
            return '<p>Updated <strong>'.htmlspecialchars($fields[0], ENT_QUOTES, 'UTF-8').'</strong></p>';
        }

        $last = array_pop($fields);

        return '<p>Updated <strong>'.htmlspecialchars(implode(', ', $fields), ENT_QUOTES, 'UTF-8')
            .'</strong> and <strong>'.htmlspecialchars((string) $last, ENT_QUOTES, 'UTF-8').'</strong></p>';
    }

    public static function formatValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '<em style="color: #999;">(empty)</em>';
        }

        $string = is_scalar($value) ? (string) $value : json_encode($value);
        if ($string === false) {
            return '<em style="color: #999;">(empty)</em>';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $string)) {
            try {
                return date('d/m/Y', strtotime($string));
            } catch (\Throwable $e) {
                return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
            }
        }

        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}
