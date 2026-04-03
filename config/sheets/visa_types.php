<?php

/**
 * Visa type sheet configuration.
 * Add new visa types here to enable additional sheets (Visitor, Student, PR, etc.).
 *
 * Each visa type requires:
 * - reference_table: client_matter_references (unified table for client matters)
 * - reference_type: type value for filtering (tr, visitor, student, pr, employer-sponsored)
 * - lead_reference_table: lead_matter_references (unified table for leads in Checklist tab)
 * - reminders_table: matter_reminders (unified table)
 * - lead_reminders_table: lead_reminders (unified table)
 * - checklist_status_column: column on client_matters for checklist tab status (e.g. tr_checklist_status)
 * - matter identification + stage mappings
 */
return [
    'tr' => [
        'title' => 'TR Sheet',
        'route' => 'clients.sheets.visa-type',
        'reference_table' => 'client_matter_references',
        'reference_type' => 'tr',
        'lead_reference_table' => 'lead_matter_references',
        'lead_reminders_table' => 'lead_reminders',
        'reference_model' => \App\Models\ClientMatterReference::class,
        'reference_alias' => 'tr_ref',
        'reminders_table' => 'matter_reminders',
        'checklist_status_column' => 'tr_checklist_status',
        'session_prefix' => 'tr_sheet_',

        'matter_nick_names' => ['tvg', 'pt'],
        'matter_title_patterns' => ['485'],

        'ongoing_stages' => [
            'Cost Agreement, form 956 and First email Sent',
            'Cost Agreement, form 956 Received',
            'Pending documents and payment requested',
            'Documents Completed and Preparing for Lodgement',
            'Verification: Payment, Service Agreement, Forms',
            'Ready for Lodgement/Draft Application sent for confirmation',
            'Draft Application confirmation received',
        ],
        'lodged_stages' => ['Application Lodged', 'Immi Request Received'],
        'checklist_early_stages' => ['Checklist'],
        'discontinue_stages' => ['Decision Received', 'Ready to Close', 'File Closed', 'Withdrawn', 'Refund', 'Discontinued'],
        'checklist_convert_to_client_stage' => 'Cost Agreement, form 956 and First email Sent',
    ],

    'visitor' => [
        'title' => 'Visitor Visa Sheet',
        'route' => 'clients.sheets.visa-type',
        'reference_table' => 'client_matter_references',
        'reference_type' => 'visitor',
        'lead_reference_table' => 'lead_matter_references',
        'lead_reminders_table' => 'lead_reminders',
        'reference_model' => \App\Models\ClientMatterReference::class,
        'reference_alias' => 'visitor_ref',
        'reminders_table' => 'matter_reminders',
        'checklist_status_column' => 'visitor_checklist_status',
        'session_prefix' => 'visitor_sheet_',

        'matter_nick_names' => ['outside australia', 'vbv', 'vvd', 'vsf', 'inside australia'],
        'matter_title_patterns' => ['600 -'],

        'ongoing_stages' => [
            'Cost Agreement, form 956 and First email Sent',
            'Cost Agreement, form 956 Received',
            'Pending documents and payment requested',
            'Documents Completed and Preparing for Lodgement',
            'Verification: Payment, Service Agreement, Forms',
            'Ready for Lodgement/Draft Application sent for confirmation',
            'Draft Application confirmation received',
        ],
        'lodged_stages' => ['Application Lodged', 'Immi Request Received'],
        'checklist_early_stages' => ['Checklist'],
        'discontinue_stages' => ['Decision Received', 'Ready to Close', 'File Closed', 'Withdrawn', 'Refund', 'Discontinued'],
        'checklist_convert_to_client_stage' => 'Cost Agreement, form 956 and First email Sent',
    ],

    'student' => [
        'title' => 'Student Visa Sheet',
        'route' => 'clients.sheets.visa-type',
        'reference_table' => 'client_matter_references',
        'reference_type' => 'student',
        'lead_reference_table' => 'lead_matter_references',
        'lead_reminders_table' => 'lead_reminders',
        'reference_model' => \App\Models\ClientMatterReference::class,
        'reference_alias' => 'student_ref',
        'reminders_table' => 'matter_reminders',
        'checklist_status_column' => 'student_checklist_status',
        'session_prefix' => 'student_sheet_',

        'matter_nick_names' => ['s', 'sse'],
        'matter_title_patterns' => ['500 -'],

        'ongoing_stages' => [
            'Cost Agreement, form 956 and First email Sent',
            'Cost Agreement, form 956 Received',
            'Pending documents and payment requested',
            'Documents Completed and Preparing for Lodgement',
            'Verification: Payment, Service Agreement, Forms',
            'Ready for Lodgement/Draft Application sent for confirmation',
            'Draft Application confirmation received',
        ],
        'lodged_stages' => ['Application Lodged', 'Immi Request Received'],
        'checklist_early_stages' => ['Checklist'],
        'discontinue_stages' => ['Decision Received', 'Ready to Close', 'File Closed', 'Withdrawn', 'Refund', 'Discontinued'],
        'checklist_convert_to_client_stage' => 'Cost Agreement, form 956 and First email Sent',
    ],

    'pr' => [
        'title' => 'PR Application Sheet',
        'route' => 'clients.sheets.visa-type',
        'reference_table' => 'client_matter_references',
        'reference_type' => 'pr',
        'lead_reference_table' => 'lead_matter_references',
        'lead_reminders_table' => 'lead_reminders',
        'reference_model' => \App\Models\ClientMatterReference::class,
        'reference_alias' => 'pr_ref',
        'reminders_table' => 'matter_reminders',
        'checklist_status_column' => 'pr_checklist_status',
        'session_prefix' => 'pr_sheet_',

        'matter_nick_names' => ['si', 'sn', 'sh', 'nn', 'swr', 'ph', 'rps'],
        'matter_title_patterns' => ['189', '190', '191', '491'],

        'ongoing_stages' => [
            'Cost Agreement, form 956 and First email Sent',
            'Cost Agreement, form 956 Received',
            'Pending documents and payment requested',
            'Documents Completed and Preparing for Lodgement',
            'Verification: Payment, Service Agreement, Forms',
            'Ready for Lodgement/Draft Application sent for confirmation',
            'Draft Application confirmation received',
        ],
        'lodged_stages' => ['Application Lodged', 'Immi Request Received'],
        'checklist_early_stages' => ['Checklist'],
        'discontinue_stages' => ['Decision Received', 'Ready to Close', 'File Closed', 'Withdrawn', 'Refund', 'Discontinued'],
        'checklist_convert_to_client_stage' => 'Cost Agreement, form 956 and First email Sent',
    ],

    'employer-sponsored' => [
        'title' => 'Employer Sponsored Visa Sheet',
        'route' => 'clients.sheets.visa-type',
        'reference_table' => 'client_matter_references',
        'reference_type' => 'employer-sponsored',
        'lead_reference_table' => 'lead_matter_references',
        'lead_reminders_table' => 'lead_reminders',
        'reference_model' => \App\Models\ClientMatterReference::class,
        'reference_alias' => 'emp_ref',
        'reminders_table' => 'matter_reminders',
        'checklist_status_column' => 'employer_sponsored_checklist_status',
        'session_prefix' => 'employer_sponsored_sheet_',

        'matter_nick_names' => ['en', 'es', 'ee', 'st', 'sd', 'ls', 'ssr', 'ses', '407'],
        'matter_title_patterns' => ['482', '494', '186', '407'],

        'ongoing_stages' => [
            'Cost Agreement, form 956 and First email Sent',
            'Cost Agreement, form 956 Received',
            'Pending documents and payment requested',
            'Documents Completed and Preparing for Lodgement',
            'Verification: Payment, Service Agreement, Forms',
            'Ready for Lodgement/Draft Application sent for confirmation',
            'Draft Application confirmation received',
        ],
        'lodged_stages' => ['Application Lodged', 'Immi Request Received'],
        'checklist_early_stages' => ['Checklist'],
        'discontinue_stages' => ['Decision Received', 'Ready to Close', 'File Closed', 'Withdrawn', 'Refund', 'Discontinued'],
        'checklist_convert_to_client_stage' => 'Cost Agreement, form 956 and First email Sent',
    ],

    'partner' => [
        'title' => 'Partner Visa Sheet',
        'route' => 'clients.sheets.visa-type',
        'reference_table' => 'client_matter_references',
        'reference_type' => 'partner',
        'lead_reference_table' => 'lead_matter_references',
        'lead_reminders_table' => 'lead_reminders',
        'reference_model' => \App\Models\ClientMatterReference::class,
        'reference_alias' => 'partner_ref',
        'reminders_table' => 'matter_reminders',
        'checklist_status_column' => 'partner_checklist_status',
        'session_prefix' => 'partner_sheet_',

        'matter_nick_names' => ['pv', 'pa', 'pm'],
        'matter_title_patterns' => ['820', '801', '309', '100', '300'],

        'ongoing_stages' => [
            'Cost Agreement, form 956 and First email Sent',
            'Cost Agreement, form 956 Received',
            'Pending documents and payment requested',
            'Documents Completed and Preparing for Lodgement',
            'Verification: Payment, Service Agreement, Forms',
            'Ready for Lodgement/Draft Application sent for confirmation',
            'Draft Application confirmation received',
        ],
        'lodged_stages' => ['Application Lodged', 'Immi Request Received'],
        'checklist_early_stages' => ['Checklist'],
        'discontinue_stages' => ['Decision Received', 'Ready to Close', 'File Closed', 'Withdrawn', 'Refund', 'Discontinued'],
        'checklist_convert_to_client_stage' => 'Cost Agreement, form 956 and First email Sent',
    ],

    'parents' => [
        'title' => 'Parents Visa Sheet',
        'route' => 'clients.sheets.visa-type',
        'reference_table' => 'client_matter_references',
        'reference_type' => 'parents',
        'lead_reference_table' => 'lead_matter_references',
        'lead_reminders_table' => 'lead_reminders',
        'reference_model' => \App\Models\ClientMatterReference::class,
        'reference_alias' => 'parents_ref',
        'reminders_table' => 'matter_reminders',
        'checklist_status_column' => 'parents_checklist_status',
        'session_prefix' => 'parents_sheet_',

        'matter_nick_names' => ['cp', 'ap', 'prt'],
        'matter_title_patterns' => ['103', '143', '173', '864', '884', '870'],

        'ongoing_stages' => [
            'Cost Agreement, form 956 and First email Sent',
            'Cost Agreement, form 956 Received',
            'Pending documents and payment requested',
            'Documents Completed and Preparing for Lodgement',
            'Verification: Payment, Service Agreement, Forms',
            'Ready for Lodgement/Draft Application sent for confirmation',
            'Draft Application confirmation received',
        ],
        'lodged_stages' => ['Application Lodged', 'Immi Request Received'],
        'checklist_early_stages' => ['Checklist'],
        'discontinue_stages' => ['Decision Received', 'Ready to Close', 'File Closed', 'Withdrawn', 'Refund', 'Discontinued'],
        'checklist_convert_to_client_stage' => 'Cost Agreement, form 956 and First email Sent',
    ],
];
