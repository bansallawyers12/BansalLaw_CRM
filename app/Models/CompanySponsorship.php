<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanySponsorship extends Model
{
    protected $table = 'company_sponsorships';

    protected $fillable = [
        'company_id',
        'sponsorship_type',
        'sponsorship_status',
        'sponsorship_start_date',
        'sponsorship_end_date',
        'trn',
        'regional_sponsorship',
        'adverse_information',
        'previous_sponsorship_notes',
        'sort_order',
    ];

    protected $casts = [
        'regional_sponsorship' => 'boolean',
        'adverse_information' => 'boolean',
        'sponsorship_start_date' => 'date',
        'sponsorship_end_date' => 'date',
        'sort_order' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
