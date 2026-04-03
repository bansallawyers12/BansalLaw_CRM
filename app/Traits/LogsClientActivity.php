<?php

namespace App\Traits;

use App\Models\ActivitiesLog;
use Illuminate\Support\Facades\Auth;

trait LogsClientActivity
{
    /**
     * Log client activity to activities_logs table
     * 
     * @param int $clientId The client ID
     * @param string $subject The activity subject (e.g., "updated personal information")
     * @param string $description Optional description/details
     * @param string $activityType The activity type (default: 'activity')
     * @return ActivitiesLog
     */
    protected function logClientActivity($clientId, $subject, $description = '', $activityType = 'activity')
    {
        return ActivitiesLog::create([
            'client_id' => $clientId,
            'created_by' => Auth::user()->id ?? Auth::id(),
            'subject' => $subject,
            'description' => $description,
            'activity_type' => $activityType,
            'task_status' => 0, // Default to 0 for non-task activities (PostgreSQL NOT NULL requirement)
            'pin' => 0, // Default to 0 (false) for non-pinned activities (PostgreSQL NOT NULL requirement)
        ]);
    }

    /**
     * Log client activity with field change details
     * 
     * @param int $clientId The client ID
     * @param string $subject The activity subject
     * @param array $changedFields Array of changed field names or field changes with old/new values
     * @param string $activityType The activity type (default: 'activity')
     * @param string $descriptionPrefix Optional HTML/text prepended to the built description (e.g. call note phone)
     * @return ActivitiesLog
     */
    protected function logClientActivityWithChanges($clientId, $subject, array $changedFields = [], $activityType = 'activity', string $descriptionPrefix = '')
    {
        $description = '';
        if (!empty($changedFields)) {
            // Check if we have detailed changes (with old/new values)
            $firstKey = array_key_first($changedFields);
            $hasDetailedChanges = is_array($changedFields[$firstKey]) && 
                                 isset($changedFields[$firstKey]['old']) && 
                                 isset($changedFields[$firstKey]['new']);

            if ($hasDetailedChanges) {
                // Format with old and new values
                $description = '<div style="margin-top: 5px;">';
                foreach ($changedFields as $fieldName => $change) {
                    $oldValue = $this->formatValue($change['old']);
                    $newValue = $this->formatValue($change['new']);
                    $description .= '<div style="margin-bottom: 8px;">';
                    $description .= '<strong>' . htmlspecialchars($fieldName) . ':</strong> ';
                    $description .= '<span style="color: #dc3545; text-decoration: line-through;">' . $oldValue . '</span> ';
                    $description .= '<span style="color: #666;">→</span> ';
                    $description .= '<span style="color: #28a745; font-weight: 600;">' . $newValue . '</span>';
                    $description .= '</div>';
                }
                $description .= '</div>';
            } else {
                // Simple format (just field names)
                $fieldCount = count($changedFields);
                if ($fieldCount === 1) {
                    $description = '<p>Updated <strong>' . $changedFields[0] . '</strong></p>';
                } else {
                    $description = '<p>Updated <strong>' . implode(', ', array_slice($changedFields, 0, -1)) . 
                                  '</strong> and <strong>' . end($changedFields) . '</strong></p>';
                }
            }
        }

        if ($descriptionPrefix !== '') {
            $description = $descriptionPrefix . $description;
        }

        return $this->logClientActivity($clientId, $subject, $description, $activityType);
    }

    /**
     * Format a value for display in activity log
     * 
     * @param mixed $value The value to format
     * @return string Formatted value
     */
    private function formatValue($value)
    {
        if ($value === null || $value === '') {
            return '<em style="color: #999;">(empty)</em>';
        }
        
        // Format dates nicely
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            try {
                return date('d/m/Y', strtotime($value));
            } catch (\Exception $e) {
                return htmlspecialchars($value);
            }
        }
        
        return htmlspecialchars($value);
    }
}

