<?php

/**
 * Canonical staff role definitions (user_roles.id → labels).
 * Used by migrations to seed missing rows; config/crm.php behaviour keys off these ids.
 *
 * Do not renumber ids — staff.role and env overrides reference numeric ids.
 */
return [
    'defaults' => [
        1 => [
            'name' => 'Super Admin',
            'description' => 'Super Admin',
        ],
        12 => [
            'name' => 'Person Responsible',
            'description' => 'Person Responsible',
        ],
        13 => [
            'name' => 'Person Assisting',
            'description' => 'Person Assisting',
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
    ],
];
