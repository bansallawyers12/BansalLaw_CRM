<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Kyslik\ColumnSortable\Sortable;

class ClientMatter extends Model
{
    use Notifiable;
    use Sortable;

    /**
     * The table associated with the model.
     */
    protected $table = 'client_matters';

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'deadline' => 'date',
        'date_of_incidence' => 'date',
    ];

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'client_id',
        'office_id',
        'sel_legal_practitioner',
        'sel_person_responsible',
        'sel_person_assisting',
        'workflow_stage_id',
        'workflow_id',
        'decision_outcome',
        'decision_note',
        'matter_status',
        'deadline',
        'client_unique_matter_no',
        'sel_matter_id',
        'case_detail',
        'date_of_incidence',
        'incidence_type',
        'updated_at_type',
    ];

    /**
     * Get the client that owns the matter.
     */
    public function client()
    {
        return $this->belongsTo(Admin::class, 'client_id');
    }

    /**
     * Get the Legal Practitioner (matter lead) assigned to the matter.
     */
    public function legalPractitioner()
    {
        return $this->belongsTo(Staff::class, 'sel_legal_practitioner');
    }

    /**
     * Get the person responsible for the matter.
     */
    public function personResponsible()
    {
        return $this->belongsTo(Staff::class, 'sel_person_responsible');
    }

    /**
     * Get the person assisting with the matter.
     */
    public function personAssisting()
    {
        return $this->belongsTo(Staff::class, 'sel_person_assisting');
    }

    /**
     * Get the workflow stage for the matter.
     */
    public function workflowStage()
    {
        return $this->belongsTo(WorkflowStage::class, 'workflow_stage_id');
    }

    /**
     * Get the workflow for this matter (per-matter workflow template).
     */
    public function workflow()
    {
        return $this->belongsTo(Workflow::class, 'workflow_id');
    }

    /**
     * Get the matter type.
     */
    public function matter()
    {
        return $this->belongsTo(Matter::class, 'sel_matter_id');
    }

    /**
     * Alias for matter() - for consistency in views
     */
    public function matterType()
    {
        return $this->belongsTo(Matter::class, 'sel_matter_id');
    }

    /**
     * Get the office that handles this matter.
     */
    public function office()
    {
        return $this->belongsTo(Branch::class, 'office_id');
    }

    /**
     * Get the notes for the matter.
     */
    public function notes()
    {
        return $this->hasMany(Note::class, 'client_id', 'client_id');
    }

    /**
     * Get the mail reports for the matter.
     */
    public function mailReports()
    {
        return $this->hasMany(EmailLog::class, 'client_matter_id');
    }

    /**
     * Get the documents for this matter.
     */
    public function documents()
    {
        return $this->hasMany(Document::class, 'client_matter_id');
    }

    /**
     * Get the receipts/financial transactions for this matter.
     */
    public function receipts()
    {
        return $this->hasMany(AccountClientReceipt::class, 'client_matter_id');
    }

    // ============================================
    // SCOPES FOR QUERYING
    // ============================================

    /**
     * Scope to filter matters by office.
     */
    public function scopeByOffice($query, $officeId)
    {
        return $query->where('office_id', $officeId);
    }

    /**
     * Scope to get active matters only.
     */
    public function scopeActive($query)
    {
        return $query->where('matter_status', 1);
    }

    /**
     * Scope to get inactive matters only.
     */
    public function scopeInactive($query)
    {
        return $query->where('matter_status', '!=', 1);
    }

    /**
     * Scope to get matters without office assigned.
     */
    public function scopeWithoutOffice($query)
    {
        return $query->whereNull('office_id');
    }

    /**
     * Scope to get matters with office assigned.
     */
    public function scopeWithOffice($query)
    {
        return $query->whereNotNull('office_id');
    }

    // ============================================
    // ACCESSORS & HELPERS
    // ============================================

    /**
     * Get the office name for this matter.
     */
    public function getOfficeNameAttribute()
    {
        return $this->office ? $this->office->office_name : 'No Office';
    }

    /**
     * Check if matter has office assigned.
     */
    public function hasOffice()
    {
        return !is_null($this->office_id);
    }

}
