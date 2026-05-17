<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Kyslik\ColumnSortable\Sortable;

/**
 * Trust / client accounting row in account_client_receipts.
 * Financial columns must only be changed through ClientAccountsController trust workflows (void + reversal).
 */
class AccountClientReceipt extends Model
{
    use Sortable;

    protected $table = 'account_client_receipts';

    public $timestamps = true;

    protected $guarded = ['id'];

    protected $casts = [
        'hubdoc_sent' => 'boolean',
        'trust_voided_at' => 'datetime',
    ];

    /** Trust ledger receipt_type */
    public const RECEIPT_TYPE_TRUST_LEDGER = 1;

    public function isExcludedFromTrustBalance(): bool
    {
        if ($this->trust_voided_at !== null) {
            return true;
        }
        if ((int) ($this->void_fee_transfer ?? 0) === 1) {
            return true;
        }

        return false;
    }
}
