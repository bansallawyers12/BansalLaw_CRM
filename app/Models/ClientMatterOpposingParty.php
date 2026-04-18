<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientMatterOpposingParty extends Model
{
    protected $table = 'client_matter_opposing_parties';

    protected $fillable = [
        'client_matter_id',
        'name',
        'party_role',
        'sort_order',
    ];

    public function clientMatter(): BelongsTo
    {
        return $this->belongsTo(ClientMatter::class, 'client_matter_id');
    }
}
