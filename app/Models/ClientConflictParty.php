<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientConflictParty extends Model
{
    protected $table = 'client_conflict_parties';

    protected $fillable = [
        'client_id', 'party_type', 'party_role',
        'first_name', 'last_name', 'aliases', 'dob',
        'company_name', 'trading_name', 'abn', 'acn',
        'address', 'suburb', 'state', 'postcode', 'country',
        'rep_firm_name', 'rep_name', 'rep_email', 'rep_phone', 'rep_country_code',
        'notes', 'sort_order', 'created_by', 'client_matter_id',
    ];

    protected $casts = [
        'aliases' => 'array',
        'dob'     => 'date',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'client_id');
    }

    public function phones(): HasMany
    {
        return $this->hasMany(ConflictPartyContact::class, 'conflict_party_id');
    }

    public function emails(): HasMany
    {
        return $this->hasMany(ConflictPartyEmail::class, 'conflict_party_id');
    }
}
