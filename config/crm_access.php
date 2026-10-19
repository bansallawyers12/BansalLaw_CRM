<?php

/**
 * Helper: parse a comma-separated env string into an array of positive ints.
 * Falls back to $default if the parsed list is empty (e.g. empty or invalid env value).
 */
$intList = static function (string $envKey, string $envDefault, array $hardDefault): array {
    $raw      = env($envKey, $envDefault);
    $filtered = array_values(array_filter(
        array_map('intval', explode(',', (string) $raw)),
        static fn (int $v) => $v > 0
    ));

    return $filtered !== [] ? $filtered : $hardDefault;
};

// Canonical default exempt staff ids (used when env is missing or invalid).
$defaultPrivilegedStaffIdsCsv = '';
$defaultPrivilegedStaffIdsArr = [];
$exemptEnvRaw = env('CRM_ACCESS_EXEMPT_STAFF_IDS');
$exemptEnvTrimmed = is_string($exemptEnvRaw) ? trim($exemptEnvRaw) : '';
if ($exemptEnvRaw === null || $exemptEnvRaw === false || $exemptEnvTrimmed === '') {
    $exempt_staff_ids = $defaultPrivilegedStaffIdsArr;
} else {
    $exempt_staff_ids = array_values(array_filter(
        array_map('intval', explode(',', $exemptEnvTrimmed)),
        static fn (int $v) => $v > 0
    ));
    if ($exempt_staff_ids === []) {
        $exempt_staff_ids = $defaultPrivilegedStaffIdsArr;
    }
}

return [
    // Role IDs that bypass allocation and the grant flow entirely.
    // Falls back to [1, 17] if env is misconfigured to prevent locking out super-admins.
    'exempt_role_ids' => $intList('CRM_ACCESS_EXEMPT_ROLE_IDS', '1,17', [1, 17]),

    // Specific staff.id values that bypass allocation like exempt roles (e.g. supervisors who are not Super Admin).
    // Leave CRM_ACCESS_EXEMPT_STAFF_IDS blank to use the canonical default exempt staff ids below.
    'exempt_staff_ids' => $exempt_staff_ids,

    'quick_reason_options' => [
        'calling'    => 'Calling / Reception',
        'cover'      => 'Covering Absent Colleague',
        'urgent'     => 'Urgent Client Follow-up',
        'admin_task' => 'Administrative Task',
    ],

    // Roles restricted to quick access only (supervisor path hard-blocked).
    // Falls back to [14] (Calling Team) if env is misconfigured.
    'quick_access_only_role_ids' => $intList('CRM_ACCESS_QUICK_ONLY_ROLE_IDS', '14', [14]),

    'quick_grant_minutes' => max(1, (int) env('CRM_ACCESS_QUICK_GRANT_MINUTES', 15)),

    'supervisor_grant_hours' => max(1, (int) env('CRM_ACCESS_SUPERVISOR_GRANT_HOURS', 24)),

    'strict_allocation' => filter_var(env('CRM_ACCESS_STRICT_ALLOCATION', false), FILTER_VALIDATE_BOOLEAN),

    // Master switch for matter allocation + cross-access restrictions (lists, search lock, detail gates).
    // When false, staff see all client/lead rows except super-admin-only file IDs (config/crm.php).
    // Set true to restore allocation filtering and the quick/supervisor grant flow.
    'allocation_enabled' => filter_var(env('CRM_ACCESS_ALLOCATION_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

    // When true, staff only see leads/clients/mail they are assigned to (matter LP/PR/PA, lead owner,
    // active cross-access grants, or mail To/Cc/Bcc). Super Admin (role 1) still bypasses unless disabled below.
    'assignment_only_access_enabled' => filter_var(env('CRM_ACCESS_ASSIGNMENT_ONLY', true), FILTER_VALIDATE_BOOLEAN),

    // Roles that bypass assignment-only rules (default: Super Admin only).
    'assignment_only_bypass_role_ids' => $intList('CRM_ACCESS_ASSIGNMENT_ONLY_BYPASS_ROLE_IDS', '1', [1]),

    // When true, matter URLs and matter-scoped data require LP/PR/PA on that matter (or a matter_temp grant).
    'matter_scoped_access_enabled' => filter_var(env('CRM_ACCESS_MATTER_SCOPED', true), FILTER_VALIDATE_BOOLEAN),

    // Roles that may open any matter without LP/PR/PA (default: Super Admin role 1 only).
    'matter_scope_bypass_role_ids' => $intList('CRM_ACCESS_MATTER_SCOPE_BYPASS_ROLE_IDS', '1', [1]),

    // Staff roles that may grant/revoke matter team access on the client detail card (default: Super Admin only).
    'matter_temp_manager_role_ids' => $intList('CRM_ACCESS_MATTER_TEMP_MANAGER_ROLE_IDS', '1', [1]),

    // Keys are form values; values are duration in minutes.
    'matter_temp_duration_minutes' => [
        '1_hour' => 60,
        '1_day' => 24 * 60,
        '1_week' => 7 * 24 * 60,
    ],

    'max_pending_supervisor_requests' => max(1, (int) env('CRM_ACCESS_MAX_PENDING_SUPERVISOR_REQUESTS', 5)),

    // Days after which un-actioned pending supervisor requests are auto-expired by the scheduled job.
    'pending_ttl_days' => max(1, (int) env('CRM_ACCESS_PENDING_TTL_DAYS', 14)),
];
