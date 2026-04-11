<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostAssignmentForm extends Model
{
    protected $table = 'cost_assignment_forms';

    protected $fillable = [
        'client_id',
        'client_matter_id',
        'agent_id',
        'Block_1_Ex_Tax',
        'Block_2_Ex_Tax',
        'Block_3_Ex_Tax',
        'additional_fee_1',
        'TotalBLOCKFEE',
        'TotalDisbursements',
    ];


    /**
     * Disbursement line items for this cost assignment.
     */
    public function disbursementLines(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DisbursementLine::class, 'cost_assignment_form_id')->orderBy('sort_order');
    }

    /**
     * Get the Admin that owns the form.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'client_id');
    }

    /**
     * Get the agent that owns the form.
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'agent_id');
    }

    /**
     * Get the client matter associated with the form.
     */
    public function clientMatter(): BelongsTo
    {
        return $this->belongsTo(ClientMatter::class, 'client_matter_id');
    }

}


