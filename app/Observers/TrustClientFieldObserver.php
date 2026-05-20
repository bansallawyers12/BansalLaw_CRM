<?php

namespace App\Observers;

use App\Models\Admin;
use App\Services\TrustAccounting\TrustLedgerAuditLogger;

/**
 * Rule 39: audit trail for client name and address changes affecting trust records.
 */
class TrustClientFieldObserver
{
    /** @var list<string> */
    private const WATCHED = [
        'first_name',
        'last_name',
        'address',
        'city',
        'state',
        'zip',
        'country',
    ];

    public function updating(Admin $admin): void
    {
        if (($admin->type ?? '') !== 'client') {
            return;
        }

        foreach (self::WATCHED as $field) {
            if (! $admin->isDirty($field)) {
                continue;
            }

            TrustLedgerAuditLogger::logForTable(
                'admins',
                'field_updated',
                (int) $admin->id,
                $field,
                $admin->getOriginal($field),
                $admin->{$field},
                'Rule 39 client field change'
            );
        }
    }
}
