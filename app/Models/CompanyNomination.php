<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyNomination extends Model
{
    protected $table = 'company_nominations';

    protected $fillable = [
        'company_id',
        'position_title',
        'anzsco_code',
        'position_description',
        'salary',
        'duration',
        'nominated_client_id',
        'nominated_person_name',
        'trn',
        'status',
        'nomination_date',
        'expiry_date',
        'sort_order',
    ];

    protected $casts = [
        'salary' => 'decimal:2',
        'nomination_date' => 'date',
        'expiry_date' => 'date',
        'sort_order' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Nominated person when they exist in system (client/lead).
     * Search scope: type in ['client','lead'].
     */
    public function nominatedClient(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'nominated_client_id', 'id');
    }
}
