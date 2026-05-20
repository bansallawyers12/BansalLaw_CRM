<?php

namespace App\Observers;

use App\Models\ClientMatter;
use App\Services\TrustAccounting\TrustLedgerAuditLogger;

/**
 * Rule 39: audit trail for matter reference and description changes.
 */
class TrustMatterFieldObserver
{
    /** @var list<string> */
    private const WATCHED = [
        'client_unique_matter_no',
        'case_detail',
    ];

    public function updating(ClientMatter $matter): void
    {
        foreach (self::WATCHED as $field) {
            if (! $matter->isDirty($field)) {
                continue;
            }

            TrustLedgerAuditLogger::logForTable(
                'client_matters',
                'field_updated',
                (int) $matter->id,
                $field,
                $matter->getOriginal($field),
                $matter->{$field},
                'Rule 39 matter field change'
            );
        }
    }
}
