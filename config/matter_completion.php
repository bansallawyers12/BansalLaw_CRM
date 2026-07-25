<?php

return [
    /*
    | Required checklist items when closing a matter with reason "Complete".
    | Keys are stored in client_matters.matter_completion_checklist (JSON).
    */
    'checklist' => [
        'authority_to_act_signed' => 'Authority to Act signed',
        'costs_agreement_signed' => 'Costs Agreement signed',
        'final_invoice_issued' => 'Final invoice issued',
        'account_cleared' => 'Account cleared',
        'matter_outcome_recorded' => 'Matter outcome recorded',
        'file_notes' => 'File notes',
        'original_documents_returned' => 'Original documents returned',
        'client_notified_matter_closed' => 'Client notified matter is closed',
    ],
];
