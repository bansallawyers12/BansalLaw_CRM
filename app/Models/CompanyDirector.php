<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyDirector extends Model
{
    protected $table = 'company_directors';

    protected $fillable = [
        'company_id',
        'director_client_id',
        'director_name',
        'director_dob',
        'director_role',
        'is_primary',
        'sort_order',
    ];

    protected $casts = [
        'director_dob' => 'date',
        'is_primary' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Director when they exist in system (client/lead).
     */
    public function directorClient(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'director_client_id', 'id');
    }

    /**
     * Display name: from linked client when available, else director_name.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->directorClient) {
            return trim($this->directorClient->first_name . ' ' . $this->directorClient->last_name);
        }
        return $this->director_name ?? '';
    }
}
