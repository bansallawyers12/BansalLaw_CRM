<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientMatterOpposingParty extends Model
{
    protected $table = 'client_matter_opposing_parties';

    protected $fillable = [
        'client_matter_id',
        'opposing_lead_id',
        'name',
        'party_role',
        'rep_firm',
        'rep_name',
        'rep_email',
        'rep_phone',
        'rep_notes',
        'sort_order',
    ];

    public function clientMatter(): BelongsTo
    {
        return $this->belongsTo(ClientMatter::class, 'client_matter_id');
    }

    public function opposingLead(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'opposing_lead_id');
    }
}
