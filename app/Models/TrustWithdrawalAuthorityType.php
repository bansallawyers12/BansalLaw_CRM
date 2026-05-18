<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrustWithdrawalAuthorityType extends Model
{
    protected $table = 'trust_withdrawal_authority_types';

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function authorities(): HasMany
    {
        return $this->hasMany(TrustWithdrawalAuthority::class, 'authority_type_id');
    }
}
