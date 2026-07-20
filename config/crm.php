<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Staff roles that may delete/grant CRM email delete (Super Admin + Admin)
    |--------------------------------------------------------------------------
    |
    | Canonical ids: 1 = Super Admin, 17 = Admin (see config/crm_roles.php).
    | Person Responsible (12) and others need the per-staff checkbox instead.
    |
    */
    'email_delete_grant_role_ids' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('CRM_EMAIL_DELETE_GRANT_ROLE_IDS', '1,17'))
    ), static fn (int $id) => $id > 0)),

    /*
    |--------------------------------------------------------------------------
    | Staff roles that may use/grant Zoho inbox sync (Super Admin + Admin)
    |--------------------------------------------------------------------------
    |
    | Other staff need {@see Staff::can_sync_inbox_emails} on their profile.
    |
    */
    'inbox_sync_grant_role_ids' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('CRM_INBOX_SYNC_GRANT_ROLE_IDS', '1,17'))
    ), static fn (int $id) => $id > 0)),

    /*
    |--------------------------------------------------------------------------
    | Staff roles that may close/discontinue matters and grant per-user flag
    |--------------------------------------------------------------------------
    |
    | Canonical ids: 1 = Super Admin, 17 = Admin (see config/crm_roles.php).
    | Other staff need {@see Staff::can_close_discontinue_matter} on their profile.
    |
    */
    'close_discontinue_grant_role_ids' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('CRM_CLOSE_DISCONTINUE_GRANT_ROLE_IDS', '1,17'))
    ), static fn (int $id) => $id > 0)),

    /*
    |--------------------------------------------------------------------------
    | Roles allowed to delete CRM email logs (legacy — superseded by staff flag)
    |--------------------------------------------------------------------------
    |
    | Prefer granting {@see Staff::can_delete_email_with_attachments} per user
    | from Admin Console (Admin / Super Admin only). Super Admin and Admin roles
    | may always delete without the flag.
    |
    */
    'email_log_delete_role_ids' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('CRM_EMAIL_LOG_DELETE_ROLE_IDS', '1,12,16'))
    ), static fn (int $id) => $id > 0)),

    /*
    |--------------------------------------------------------------------------
    | Person Assisting role IDs (user_roles.id)
    |--------------------------------------------------------------------------
    |
    | Staff with these roles only see clients/leads where they appear on a matter
    | as Legal Practitioner, person responsible, or person assisting (client_matters
    | sel_legal_practitioner / sel_person_responsible / sel_person_assisting), or
    | are assigned on the lead record (admins.user_id). Super admin (role 1) is
    | never restricted. Override via CRM_PERSON_ASSISTING_ROLE_IDS e.g. "13,21".
    |
    */
    'person_assisting_role_ids' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('CRM_PERSON_ASSISTING_ROLE_IDS', '13'))
    ), static fn (int $id) => $id > 0)),

    /*
    |--------------------------------------------------------------------------
    | Lead list / lead record full access (staff.role → user_roles.id)
    |--------------------------------------------------------------------------
    |
    | These roles see every lead (list + detail). Everyone else sees leads where
    | admins.user_id matches, or any client_matters row for that lead lists them as
    | MA / PR / PA, or they have an active cross-access grant (see StaffClientVisibility).
    |
    | Default mapping: 1 = Super Admin, 17 = Admin, 12 = Person Responsible.
    | Override via CRM_LEAD_FULL_ACCESS_ROLE_IDS e.g. "1,17,12".
    |
    */
    'lead_full_access_role_ids' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('CRM_LEAD_FULL_ACCESS_ROLE_IDS', '1,12,17'))
    ), static fn (int $id) => $id > 0)),

    /*
    |--------------------------------------------------------------------------
    | Lead list: which user_roles.module_access keys unlock the page
    |--------------------------------------------------------------------------
    |
    | Previously only key "20" (view all clients) was checked, so staff with
    | only 21–23 (add/edit/view assigned clients) saw an empty list. Default
    | includes 20–23. Set CRM_LEAD_LIST_MODULE_ACCESS_KEYS e.g. "20,21,22,23".
    |
    | CRM_LEAD_LIST_EXTRA_ROLE_IDS: optional staff role ids that may open the
    | lead list even without those keys (row visibility still uses
    | restrictLeadListQuery).
    |
    */
    'lead_list_module_access_keys' => array_values(array_filter(
        array_map(
            static fn (string $k) => trim($k),
            array_map('strval', explode(',', (string) env('CRM_LEAD_LIST_MODULE_ACCESS_KEYS', '20,21,22,23')))
        ),
        static fn (string $k) => $k !== ''
    )),

    /*
    |--------------------------------------------------------------------------
    | Client list (/clients, matters tabs, client emails): module_access keys
    |--------------------------------------------------------------------------
    |
    | Same issue as leads: checking only key "20" hides staff who only have
    | 21–23 (e.g. "view assigned clients" = 23). Default 20–23 matches role UI.
    | Row visibility still uses StaffClientVisibility. Override via
    | CRM_CLIENT_LIST_MODULE_ACCESS_KEYS.
    |
    | Opening without these keys: use the same role bypasses as the lead list
    | (lead_full_access_role_ids, lead_list_extra_role_ids,
    | lead_list_assigned_only_role_ids) via ClientAuthorization::hasClientListModuleAccess().
    |
    */
    'client_list_module_access_keys' => array_values(array_filter(
        array_map(
            static fn (string $k) => trim($k),
            array_map('strval', explode(',', (string) env('CRM_CLIENT_LIST_MODULE_ACCESS_KEYS', '20,21,22,23')))
        ),
        static fn (string $k) => $k !== ''
    )),

    'lead_list_extra_role_ids' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('CRM_LEAD_LIST_EXTRA_ROLE_IDS', ''))
    ), static fn (int $id) => $id > 0)),

    /*
    |--------------------------------------------------------------------------
    | Lead list: "assigned leads only" roles (staff.role → user_roles.id)
    |--------------------------------------------------------------------------
    |
    | These roles may open /leads without client module keys 20–23. They only
    | see rows where admins.user_id = their staff id (via restrictLeadListQuery).
    | Default: PA (13), Calling (14), Accountant (15), Solicitor (16; user_roles name).
    | Set CRM_LEAD_LIST_ASSIGNED_ONLY_ROLE_IDS to override, e.g. "13,14,15,16".
    |
    */
    'lead_list_assigned_only_role_ids' => (($__leadAssignedRoles = array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('CRM_LEAD_LIST_ASSIGNED_ONLY_ROLE_IDS', '13,14,15,16'))
    ), static fn (int $id) => $id > 0))) !== []
        ? $__leadAssignedRoles
        : [13, 14, 15, 16]),

    /*
    |--------------------------------------------------------------------------
    | Super-admin-only client file IDs (admins.client_id when type = client)
    |--------------------------------------------------------------------------
    |
    | Staff with role 1 (Super Admin) see these clients everywhere. Other staff
    | cannot list, search, or open them. Applies only to type=client, not leads.
    | Override or extend via CRM_SUPER_ADMIN_ONLY_CLIENT_FILE_IDS e.g.
    | "GURP2502080,OASH2505088".
    |
    */
    'super_admin_only_client_file_ids' => array_values(array_unique(array_filter(
        array_map(
            static fn (string $id) => trim($id),
            explode(',', (string) env(
                'CRM_SUPER_ADMIN_ONLY_CLIENT_FILE_IDS',
                'GURP2502080,OASH2505088,PRAB2504834,PALW2502036'
            ))
        ),
        static fn (string $id) => $id !== ''
    ))),

    /*
    |--------------------------------------------------------------------------
    | Google review reminder modal (client/lead detail)
    |--------------------------------------------------------------------------
    |
    | staff.role values (user_roles.id) that never see the reminder popup.
    | Default: 14 = Calling Team, 15 = Accountant (accounts). Override via
    | CRM_GOOGLE_REVIEW_REMINDER_EXCLUDE_ROLE_IDS e.g. "14,15,20".
    |
    | Delay before the modal opens (milliseconds). Default 60000 = 1 minute.
    | CRM_GOOGLE_REVIEW_REMINDER_DELAY_MS=0 opens immediately.
    | Capped at 30 minutes to avoid accidental huge values in .env.
    |
    */
    'google_review_reminder_exclude_role_ids' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('CRM_GOOGLE_REVIEW_REMINDER_EXCLUDE_ROLE_IDS', '14,15'))
    ), static fn (int $id) => $id > 0)),

    'google_review_reminder_modal_delay_ms' => min(
        30 * 60 * 1000,
        max(0, (int) env('CRM_GOOGLE_REVIEW_REMINDER_DELAY_MS', 60000))
    ),

    /*
    |--------------------------------------------------------------------------
    | Admin Console (/adminconsole) — staff.role (user_roles.id)
    |--------------------------------------------------------------------------
    |
    | Default: 1 = Super Admin, 12 = Person Responsible, 17 = Admin.
    | Staff with grant_super_admin_access + session elevation also qualify.
    | Override via CRM_ADMIN_CONSOLE_ROLE_IDS e.g. "1,12,17".
    |
    */
    'admin_console_role_ids' => array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('CRM_ADMIN_CONSOLE_ROLE_IDS', '1,12,17'))
    ), static fn (int $id) => $id > 0)),

    /*
    |--------------------------------------------------------------------------
    | Front-desk check-in wizard (header icon + /front-desk/checkin)
    |--------------------------------------------------------------------------
    |
    | staff.role values (user_roles.id) that may open the wizard. Default includes
    | 1 = Super Admin, 12 = Person Responsible, 14 = Calling / Reception,
    | 17 = Admin. Exempt roles from crm_access (CRM_ACCESS_EXEMPT_ROLE_IDS) are
    | merged in at runtime. Override via CRM_FRONT_DESK_CHECKIN_ROLE_IDS e.g. "1,14,17".
    |
    */
    'front_desk_checkin_role_ids' => (($__fdCheckinRoles = array_values(array_filter(array_map(
        'intval',
        explode(',', (string) env('CRM_FRONT_DESK_CHECKIN_ROLE_IDS', '1,12,14,17'))
    ), static fn (int $id) => $id > 0))) !== []
        ? $__fdCheckinRoles
        : [1, 12, 14, 17]),

    /*
    |--------------------------------------------------------------------------
    | Email upload — allowed Outlook email file extensions (lowercase, no dot)
    |--------------------------------------------------------------------------
    */
    'email_upload_allowed_extensions' => array_values(array_unique(array_filter(array_map(
        static function (string $ext): string {
            return strtolower(ltrim(trim($ext), '.'));
        },
        explode(',', (string) env('EMAIL_UPLOAD_ALLOWED_EXTENSIONS', 'msg,eml'))
    )))),

    /*
    |--------------------------------------------------------------------------
    | Email upload limits (matches EmailUploadController validation)
    |--------------------------------------------------------------------------
    */
    'email_upload_max_kb' => max(1, (int) env('EMAIL_UPLOAD_MAX_KB', 30720)),

    /*
    |--------------------------------------------------------------------------
    | Personal document video upload queue
    |--------------------------------------------------------------------------
    | Default sync + afterResponse processes videos in the same web request after
    | the JSON response is sent — no separate queue worker required.
    | Use PERSONAL_VIDEO_UPLOAD_QUEUE_CONNECTION=redis with a running queue worker
    | only on servers where storage/app/video-uploads is shared with workers.
    */
    'personal_video_upload' => [
        // Stream video directly to S3 during the upload request (fastest; no queue worker needed).
        'direct_upload' => filter_var(env('PERSONAL_VIDEO_UPLOAD_DIRECT', true), FILTER_VALIDATE_BOOLEAN),
        'queue_connection' => env('PERSONAL_VIDEO_UPLOAD_QUEUE_CONNECTION', 'sync'),
        'after_response' => filter_var(env('PERSONAL_VIDEO_UPLOAD_AFTER_RESPONSE', true), FILTER_VALIDATE_BOOLEAN),
        'cache_store' => env('PERSONAL_VIDEO_UPLOAD_CACHE_STORE'),
        // PHP / S3 processing limits (also set in public/.user.ini for upload receive phase).
        'execution_time_seconds' => max(300, (int) env('PERSONAL_VIDEO_UPLOAD_EXECUTION_TIME', 1800)),
        'max_input_time_seconds' => max(300, (int) env('PERSONAL_VIDEO_UPLOAD_MAX_INPUT_TIME', 1800)),
        'socket_timeout_seconds' => max(120, (int) env('PERSONAL_VIDEO_UPLOAD_SOCKET_TIMEOUT', 600)),
        'max_size_mb' => max(1, (int) env('PERSONAL_VIDEO_UPLOAD_MAX_MB', 200)),
    ],

];
