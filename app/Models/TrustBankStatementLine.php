<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrustBankStatementLine extends Model
{
    protected $table = 'trust_bank_statement_lines';

    protected $guarded = ['id'];

    protected $casts = [
        'value_date' => 'date',
        'amount' => 'decimal:2',
        'matched_at' => 'datetime',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(TrustBankAccount::class, 'trust_bank_account_id');
    }

    public function matchedReceipt(): BelongsTo
    {
        return $this->belongsTo(AccountClientReceipt::class, 'matched_account_client_receipt_id');
    }

    public function matchedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'matched_by_staff_id');
    }

    public function isMatched(): bool
    {
        return $this->matched_account_client_receipt_id !== null;
    }
}
