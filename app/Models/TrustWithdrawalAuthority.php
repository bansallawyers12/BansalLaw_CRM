<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrustWithdrawalAuthority extends Model
{
    protected $table = 'trust_withdrawal_authorities';

    protected $guarded = ['id'];

    protected $casts = [
        'withdrawal_amount' => 'decimal:2',
        'notice_given_date' => 'date',
        'supervisor_override' => 'boolean',
    ];

    public function feeTransferRow(): BelongsTo
    {
        return $this->belongsTo(AccountClientReceipt::class, 'account_client_receipt_id');
    }

    public function authorityType(): BelongsTo
    {
        return $this->belongsTo(TrustWithdrawalAuthorityType::class, 'authority_type_id');
    }

    public function authorisedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'authorised_by_staff_id');
    }
}
