<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientConflictCheck extends Model
{
    protected $table = 'client_conflict_checks';

    protected $fillable = [
        'client_id', 'checked_by', 'checked_at',
        'search_terms', 'matches',
        'outcome', 'outcome_notes',
        'consent_obtained', 'consent_notes',
    ];

    protected $casts = [
        'search_terms'     => 'array',
        'matches'          => 'array',
        'consent_obtained' => 'boolean',
        'checked_at'       => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'client_id');
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'checked_by');
    }
}
