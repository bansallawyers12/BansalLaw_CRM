<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Frozen workflow stages (Admin Console)
    |--------------------------------------------------------------------------
    |
    | Stages whose names match these rules cannot be renamed or deleted.
    | Exact matches are compared case-insensitively after trimming.
    | "Contains" rules match if the stage name contains the substring (any case).
    |
    */
    'frozen_stage_names' => [
        'Checklist',
        'Decision Received',
        'Ready to Close',
        'File Closed',
    ],

    /*
     * Stage names that start with this text (any case) are frozen.
     * Matches e.g. "Verification: Payment, Service Agreement, Forms"
     * without locking unrelated names like "Pre-verification review".
     */
    'frozen_stage_name_starts_with' => [
        'verification',
    ],

];
