<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Roles allowed to delete CRM email logs (inbox/sent thread rows)
    |--------------------------------------------------------------------------
    |
    | Comma-separated user_roles.id values. Default matches legacy behaviour
    | (Super Admin, Admin, Migration Agent). Set CRM_EMAIL_LOG_DELETE_ROLE_IDS
    | in .env to add roles without code changes, e.g. "1,12,16,20".
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
    | as migration agent, person responsible, or person assisting (client_matters
    | sel_migration_agent / sel_person_responsible / sel_person_assisting), or
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
    | Default: PA (13), Calling (14), Accountant (15), Migration Agent (16).
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

];
