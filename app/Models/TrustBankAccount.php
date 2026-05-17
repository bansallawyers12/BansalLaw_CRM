<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrustBankAccount extends Model
{
    protected $table = 'trust_bank_accounts';

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function statementLines(): HasMany
    {
        return $this->hasMany(TrustBankStatementLine::class, 'trust_bank_account_id');
    }
}
