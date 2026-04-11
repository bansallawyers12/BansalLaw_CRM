<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientLegalForm extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'client_matter_id',
        'created_by',
        'form_type',
        'matter_reference',
        'firm_name',
        'firm_contact',
        'firm_address',
        'firm_phone',
        'firm_mobile',
        'firm_email',
        'firm_state',
        'firm_postcode',
        'person_responsible',
        'person_responsible_email',
        'scope_of_work',
        'estimated_legal_fees',
        'estimated_disbursements',
        'estimated_barrister_fees',
        'gst_amount',
        'estimated_total',
        'fee_type',
        'fixed_fee_amount',
        'cost_estimate_breakdown',
        'variables_affecting_costs',
        'retainer_amount',
        'trust_account_name',
        'trust_account_institution',
        'trust_account_bsb',
        'trust_account_number',
        'payment_reference',
        'authority_scope',
        'pdf_path',
        'form_date',
        'signed_date',
    ];

    protected $casts = [
        'form_date' => 'date',
        'signed_date' => 'date',
        'estimated_legal_fees' => 'decimal:2',
        'estimated_disbursements' => 'decimal:2',
        'estimated_barrister_fees' => 'decimal:2',
        'gst_amount' => 'decimal:2',
        'estimated_total' => 'decimal:2',
        'fixed_fee_amount' => 'decimal:2',
        'retainer_amount' => 'decimal:2',
    ];

    public const FORM_TYPES = [
        'short_costs_disclosure' => 'Short Costs Disclosure',
        'cost_agreement' => 'Cost Agreement',
        'authority_to_act' => 'Authority to Act',
    ];

    public function client()
    {
        return $this->belongsTo(Admin::class, 'client_id');
    }

    public function matter()
    {
        return $this->belongsTo(ClientMatter::class, 'client_matter_id');
    }

    public function creator()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function getFormTypeLabelAttribute(): string
    {
        return self::FORM_TYPES[$this->form_type] ?? $this->form_type;
    }
}
