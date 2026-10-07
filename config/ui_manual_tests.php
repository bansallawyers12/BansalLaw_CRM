<?php

/**
 * Manual UI smoke-test catalog (used on local only — System Errors → UI Test Suite tab).
 *
 * Optional .env:
 *   UI_MANUAL_TEST_CLIENT_DETAIL_URL=/clients/detail/{encode}/MATTER_1/personaldocuments
 */
return [

    'storage_key' => 'bansal_crm_ui_manual_tests_v1',

    'meta' => [
        'doc_path' => 'docs/UI_REGRESSION_TRACKING_AND_TESTING_GUIDE.md',
        'lazy_tab_note' => 'For client-detail changes, repeat each marked test using both a direct URL to the tab and opening the tab from Overview (lazy load).',
    ],

    'client_detail_url' => env('UI_MANUAL_TEST_CLIENT_DETAIL_URL'),

    'sections' => [
        [
            'id' => 'shell',
            'title' => 'Auth & application shell',
            'items' => [
                [
                    'id' => 'login_logout',
                    'critical' => true,
                    'title' => 'Login and logout',
                    'steps' => [
                        'Log in as a staff user.',
                        'Open the dashboard without console errors (F12 → Console).',
                        'Log out and confirm you cannot access protected pages.',
                    ],
                    'route' => 'dashboard',
                ],
                [
                    'id' => 'top_nav',
                    'critical' => true,
                    'title' => 'Top navigation',
                    'steps' => [
                        'Open Dashboard, Clients, and Mail from the top menu.',
                        'Each page loads; note any red console errors.',
                    ],
                    'route' => 'clients.index',
                ],
            ],
        ],
        [
            'id' => 'dashboard',
            'title' => 'Dashboard',
            'items' => [
                [
                    'id' => 'dashboard_tasks',
                    'critical' => true,
                    'title' => 'Tasks — add and complete',
                    'steps' => [
                        'Add a task from the header or empty state.',
                        'Complete or update a task; popover/modal closes on save.',
                    ],
                    'route' => 'dashboard',
                ],
            ],
        ],
        [
            'id' => 'clients',
            'title' => 'Clients — list & detail',
            'items' => [
                [
                    'id' => 'client_list',
                    'critical' => true,
                    'title' => 'Client list search',
                    'steps' => [
                        'Search and paginate the client list.',
                        'Open a client detail page.',
                    ],
                    'route' => 'clients.index',
                ],
                [
                    'id' => 'client_tasks',
                    'critical' => true,
                    'title' => 'Client — Tasks tab',
                    'steps' => [
                        'Open Tasks tab (sidebar path, not only deep link).',
                        'Add task; open Update task; close with X and after Save.',
                    ],
                    'client_detail_tab' => 'clientaction',
                ],
                [
                    'id' => 'client_personal_docs',
                    'critical' => true,
                    'title' => 'Client — Personal documents',
                    'steps' => [
                        'Bulk Upload: open dropzone, stays open until Close.',
                        'Upload one file; preview in right pane.',
                        'Switch folder sub-tab and repeat Bulk Upload once.',
                    ],
                    'client_detail_tab' => 'personaldocuments',
                ],
                [
                    'id' => 'client_matter_docs',
                    'critical' => false,
                    'title' => 'Client — Matter documents',
                    'steps' => [
                        'Bulk Upload (visa) toggle open/close.',
                        'Preview a matter document.',
                    ],
                    'client_detail_tab' => 'matterdocuments',
                ],
                [
                    'id' => 'client_emails',
                    'critical' => true,
                    'title' => 'Client — Emails',
                    'steps' => [
                        'Search emails; filter icon visible beside search.',
                        'Open filter drawer; change label/folder filter.',
                        'Open an email; attachments load when present.',
                    ],
                    'client_detail_tab' => 'emails',
                ],
                [
                    'id' => 'lazy_tab_matrix',
                    'critical' => true,
                    'title' => 'Lazy-tab regression (L1–L5)',
                    'steps' => [
                        'L1: Hard refresh on a deep-linked client tab URL.',
                        'L2: From Overview, click Emails → Documents → Tasks in order.',
                        'L3: Leave Documents and return twice.',
                        'L4: Change document folder sub-tabs.',
                        'L5: Switch matter (if applicable) and re-test one critical control.',
                    ],
                ],
            ],
        ],
        [
            'id' => 'mail',
            'title' => 'Mail (global)',
            'items' => [
                [
                    'id' => 'unassigned_inbox',
                    'critical' => true,
                    'title' => 'Unassigned / assigned inbox',
                    'steps' => [
                        'Open unassigned mail view; switch folder/status tabs.',
                        'Open a message; filters toggle without layout break.',
                    ],
                    'path' => '/clients/clientsemaillist',
                ],
            ],
        ],
        [
            'id' => 'devtools',
            'title' => 'Developer sanity (local)',
            'items' => [
                [
                    'id' => 'bootstrap_compat',
                    'critical' => false,
                    'title' => 'Bootstrap / jQuery compat script',
                    'steps' => [
                        'From project root run: npm run verify:bootstrap-compat',
                        'Command exits without errors.',
                    ],
                ],
                [
                    'id' => 'console_clean',
                    'critical' => false,
                    'title' => 'Console clean on critical paths',
                    'steps' => [
                        'With DevTools open, repeat critical items above.',
                        'No uncaught exceptions; note any 419/403 XHR failures.',
                    ],
                ],
            ],
        ],
    ],
];
