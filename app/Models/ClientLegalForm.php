<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class ClientLegalForm extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'client_matter_id',
        'created_by',
        'source_document_id',
        'form_type',
        'is_uploaded',
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
        'attachment_path',
        'attachment_original_name',
        'form_date',
        'signed_date',
    ];

    protected $casts = [
        'is_uploaded' => 'boolean',
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
        'cost_agreement' => 'Long Costs Disclosure',
        'authority_to_act' => 'Authority to Act',
    ];

    /**
     * Columns returned by the Legal Forms tab list API (tolerates pre-migration DBs).
     */
    public static function apiListSelectColumns(): array
    {
        $columns = [
            'id',
            'client_id',
            'client_matter_id',
            'created_by',
            'form_type',
            'is_uploaded',
            'matter_reference',
            'estimated_legal_fees',
            'estimated_disbursements',
            'estimated_barrister_fees',
            'gst_amount',
            'estimated_total',
            'retainer_amount',
            'attachment_path',
            'attachment_original_name',
            'form_date',
            'created_at',
        ];

        if (Schema::hasColumn((new static)->getTable(), 'source_document_id')) {
            array_splice($columns, 4, 0, ['source_document_id']);
        }

        return $columns;
    }

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
        // created_by is Auth::guard('admin') → staff.id
        return $this->belongsTo(Staff::class, 'created_by');
    }

    public function sourceDocument()
    {
        return $this->belongsTo(Document::class, 'source_document_id');
    }

    public function getFormTypeLabelAttribute(): string
    {
        return self::FORM_TYPES[$this->form_type] ?? $this->form_type;
    }

    /**
     * Latest generated costs disclosure for billing comparison (not uploads).
     */
    public static function latestCostsDisclosureForMatter(int $clientId, ?int $clientMatterId): ?self
    {
        $query = self::query()
            ->where('client_id', $clientId)
            ->whereIn('form_type', ['short_costs_disclosure', 'cost_agreement'])
            ->where(function ($q) {
                $q->where('is_uploaded', false)->orWhereNull('is_uploaded');
            })
            ->where('estimated_total', '>', 0);

        if ($clientMatterId !== null) {
            $query->where('client_matter_id', $clientMatterId);
        } else {
            $query->whereNull('client_matter_id');
        }

        return $query
            ->orderByRaw('CASE WHEN form_date IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('form_date')
            ->orderByDesc('id')
            ->first();
    }
}
