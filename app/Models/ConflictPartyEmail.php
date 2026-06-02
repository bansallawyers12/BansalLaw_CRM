<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConflictPartyEmail extends Model
{
    protected $table = 'conflict_party_emails';

    protected $fillable = [
        'conflict_party_id', 'email_type', 'email',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(ClientConflictParty::class, 'conflict_party_id');
    }
}
