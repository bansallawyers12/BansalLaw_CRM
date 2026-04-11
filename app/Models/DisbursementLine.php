<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisbursementLine extends Model
{
    protected $table = 'disbursement_lines';

    protected $fillable = [
        'cost_assignment_form_id',
        'nature',
        'description',
        'amount',
        'sort_order',
    ];

    protected $casts = [
        'amount' => 'float',
        'sort_order' => 'integer',
    ];

    public static array $natures = [
        'court_fees'       => 'Court Fees',
        'barrister_fees'   => 'Barrister Fees',
        'expert_report'    => 'Expert Report',
        'travel'           => 'Travel',
        'postage'          => 'Postage / Courier',
        'filing_registry'  => 'Filing / Registry',
        'search_fees'      => 'Search Fees',
        'other'            => 'Other',
    ];

    public function costAssignmentForm(): BelongsTo
    {
        return $this->belongsTo(CostAssignmentForm::class, 'cost_assignment_form_id');
    }
}
