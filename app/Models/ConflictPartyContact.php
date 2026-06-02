<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConflictPartyContact extends Model
{
    protected $table = 'conflict_party_contacts';

    protected $fillable = [
        'conflict_party_id', 'contact_type', 'country_code', 'phone',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(ClientConflictParty::class, 'conflict_party_id');
    }
}
