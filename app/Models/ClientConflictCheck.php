<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientConflictCheck extends Model
{
    protected $table = 'client_conflict_checks';

    protected $fillable = [
        'client_id',
        'client_matter_id',
        'checked_by',
        'checked_at',
        'search_terms',
        'matches',
        'match_count',
        'informational_count',
        'informational_matches',
        'parties_snapshot_at',
        'search_hash',
        'outcome',
        'outcome_notes',
        'consent_obtained',
        'consent_notes',
    ];

    protected $casts = [
        'search_terms'          => 'array',
        'matches'               => 'array',
        'informational_matches' => 'array',
        'consent_obtained'      => 'boolean',
        'checked_at'            => 'datetime',
        'parties_snapshot_at'   => 'datetime',
        'match_count'           => 'integer',
        'informational_count'   => 'integer',
    ];

    /**
     * Matter-scoped checks, with optional legacy client-wide (null matter) fallback.
     */
    public function scopeForActiveMatter(Builder $query, ?int $clientMatterId): Builder
    {
        if (! $clientMatterId) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($clientMatterId) {
            $q->where('client_matter_id', $clientMatterId)
                ->orWhereNull('client_matter_id');
        });
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'client_id');
    }

    public function clientMatter(): BelongsTo
    {
        return $this->belongsTo(ClientMatter::class, 'client_matter_id');
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'checked_by');
    }
}
