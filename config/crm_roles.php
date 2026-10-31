<?php

/**
 * Canonical staff role definitions (user_roles.id → labels).
 * Used by the staff form and by migrations to seed missing rows.
 * config/crm.php behaviour keys off these ids.
 *
 * Do not renumber ids — staff.role and env overrides reference numeric ids.
 * Person Responsible (12) and Person Assisting (13) stay in the database for
 * existing staff. They are matter seats (Responsible Solicitor / Paralegal),
 * not job titles, so they are not offered when assigning a staff role.
 */
return [
    'defaults' => [
        1 => [
            'name' => 'Super Admin',
            'description' => 'Super Admin',
        ],
        14 => [
            'name' => 'Calling Team',
            'description' => 'Calling Team',
        ],
        15 => [
            'name' => 'Accountant',
            'description' => 'Accountant',
        ],
        16 => [
            'name' => 'Solicitor',
            'description' => 'Solicitor',
        ],
        17 => [
            'name' => 'Admin',
            'description' => 'Admin',
        ],
        18 => [
            'name' => 'Paralegal (Australia)',
            'description' => 'Paralegal based in Australia',
        ],
        19 => [
            'name' => 'Paralegal (India)',
            'description' => 'Paralegal based in India',
        ],
        20 => [
            'name' => 'Reception',
            'description' => 'Reception',
        ],
    ],

    /** Matter-seat roles kept for existing staff.role values. Not shown as new choices. */
    'legacy' => [
        12 => [
            'name' => 'Person Responsible',
            'description' => 'Person Responsible',
        ],
        13 => [
            'name' => 'Person Assisting',
            'description' => 'Person Assisting',
        ],
    ],

    'paralegal_role_ids' => [18, 19],
];
