<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Frozen workflow stages (Admin Console)
    |--------------------------------------------------------------------------
    |
    | Stages whose names match these rules cannot be renamed or deleted.
    | Exact matches are compared case-insensitively after trimming.
    |
    */
    'frozen_stage_names' => [
        'Checklist',
        'Decision Received',
        'Ready to Close',
        'File Closed',
    ],

];
