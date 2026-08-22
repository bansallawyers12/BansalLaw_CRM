<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\SortableTrait;

/**
 * Trust / client accounting row in account_client_receipts.
 * Financial columns must only be changed through ClientAccountsController trust workflows (void + reversal).
 */
class AccountClientReceipt extends Model
{
    use SortableTrait;

    public $sortable = ['id', 'created_at', 'updated_at'];

    protected $table = 'account_client_receipts';

    public $timestamps = true;

    protected $guarded = ['id'];

    protected $casts = [
        'hubdoc_sent' => 'boolean',
    ];

    /** Trust ledger receipt_type */
    public const RECEIPT_TYPE_TRUST_LEDGER = 1;

    public function isExcludedFromTrustBalance(): bool
    {
        return (int) ($this->void_fee_transfer ?? 0) === 1;
    }
}
