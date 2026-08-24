<?php

namespace App\Traits;

use App\Models\ActivitiesLog;
use App\Support\ClientActivity;

trait LogsClientActivity
{
    /**
     * Log client activity to activities_logs (Timeline).
     *
     * @param  int  $clientId  The client ID
     * @param  string  $subject  The activity subject (e.g., "updated personal information")
     * @param  string  $description  Optional description/details
     * @param  string  $activityType  The activity type (default: 'activity')
     * @return ActivitiesLog|null
     */
    protected function logClientActivity($clientId, $subject, $description = '', $activityType = 'activity')
    {
        return ClientActivity::log(
            (int) $clientId,
            (string) $subject,
            (string) $activityType,
            (string) $description
        );
    }

    /**
     * Log client activity with field change details.
     *
     * @param  int  $clientId  The client ID
     * @param  string  $subject  The activity subject
     * @param  array  $changedFields  Array of changed field names or field changes with old/new values
     * @param  string  $activityType  The activity type (default: 'activity')
     * @param  string  $descriptionPrefix  Optional HTML/text prepended to the built description
     * @return ActivitiesLog|null
     */
    protected function logClientActivityWithChanges($clientId, $subject, array $changedFields = [], $activityType = 'activity', string $descriptionPrefix = '')
    {
        return ClientActivity::logWithChanges(
            (int) $clientId,
            (string) $subject,
            $changedFields,
            (string) $activityType,
            $descriptionPrefix
        );
    }

    protected function bulkUploadActivitySubject(int $count, string $docLabel = '', ?string $matterRef = null): string
    {
        $word = $count === 1 ? 'document' : 'documents';
        $label = $docLabel !== '' ? "{$docLabel} " : '';
        $core = "bulk uploaded {$count} {$label}{$word}";

        return ($matterRef !== null && $matterRef !== '') ? "{$core} - {$matterRef}" : $core;
    }

    protected function bulkUploadActivityDescription(int $count, string $docLabel = 'personal'): string
    {
        $word = $count === 1 ? 'document' : 'documents';

        return "<p>Bulk uploaded {$count} {$docLabel} {$word}</p>";
    }
}
