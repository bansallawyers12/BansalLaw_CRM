<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Visa Expiry Reminder Template
    |--------------------------------------------------------------------------
    |
    | Template ID for the visa expiry reminder email. Set this after running
    | the email_templates migration. If null, the command will attempt to
    | find a CRM template by name (containing "visa" and "expir").
    |
    */
    'visa_expiry_template_id' => env('EMAIL_TEMPLATE_VISA_EXPIRY_ID', null),

];
