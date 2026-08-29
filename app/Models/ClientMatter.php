<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use App\Traits\SortableTrait;

class ClientMatter extends Model
{
    use Notifiable;
    use SortableTrait;

    public $sortable = ['id', 'created_at', 'updated_at', 'deadline'];

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
        'matter_completion_checklist' => 'array',
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
        'our_party_role',
        'updated_at_type',
        'closed_by',
        'discontinue_reason',
        'discontinue_notes',
        'matter_completion_checklist',
        'reopen_requested_by',
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
     * Per-matter checklist items (Actions tab — staff-defined tasks).
     */
    public function tasks()
    {
        return $this->hasMany(ClientMatterTask::class, 'client_matter_id');
    }

    /**
     * Other parties (opposing side) for this matter.
     */
    public function opposingParties()
    {
        return $this->hasMany(ClientMatterOpposingParty::class, 'client_matter_id')->orderBy('sort_order');
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

    /**
     * Workflow stage names that indicate a closed matter (mirrors clientsmatterslist / closedmatterslist).
     */
    public const CLOSED_WORKFLOW_STAGE_NAMES = ['file closed', 'withdrawn', 'refund', 'discontinued'];

    public static function closedWorkflowStageNames(): array
    {
        return self::CLOSED_WORKFLOW_STAGE_NAMES;
    }

    /**
     * Whether a matter is closed (discontinued or in a closed workflow stage).
     */
    public static function isClosed(?self $matter = null, ?int $matterStatus = null, ?string $workflowStageName = null): bool
    {
        if ($matter instanceof self) {
            $matterStatus = (int) $matter->matter_status;
            if ($matter->relationLoaded('workflowStage') && $matter->workflowStage) {
                $workflowStageName = $matter->workflowStage->name;
            }
        }

        if ((int) ($matterStatus ?? 1) === 0) {
            return true;
        }

        $stage = strtolower(trim((string) ($workflowStageName ?? '')));

        return $stage !== '' && in_array($stage, self::closedWorkflowStageNames(), true);
    }

    /**
     * @param  object|null  $row  stdClass/Model with matter_status and optional workflow_stage_name
     */
    public static function isClosedRow(?object $row): bool
    {
        if (! $row) {
            return false;
        }

        return self::isClosed(
            null,
            isset($row->matter_status) ? (int) $row->matter_status : null,
            $row->workflow_stage_name ?? null
        );
    }

    /**
     * Whether this client has at least one active matter with a matter type assigned.
     * Matches client detail / matter switcher logic (active + sel_matter_id set).
     */
    public static function clientHasActiveAssignedMatter(int $clientId): bool
    {
        return self::query()
            ->where('client_id', $clientId)
            ->where('matter_status', 1)
            ->whereNotNull('sel_matter_id')
            ->exists();
    }

    /**
     * Generate a unique client matter reference number (client_unique_matter_no)
     * avoiding duplicate reference race conditions.
     */
    public static function generateUniqueMatterNumber(int $clientId, int $matterId): string
    {
        $prefix = Matter::clientUniqueMatterNoPrefix($matterId);
        
        $count = self::query()
            ->where('sel_matter_id', $matterId)
            ->where('client_id', $clientId)
            ->count();

        $seqNo = $count + 1;
        $candidateRef = $prefix . '_' . $seqNo;

        while (self::query()->where('client_id', $clientId)->where('client_unique_matter_no', $candidateRef)->exists()) {
            $seqNo++;
            $candidateRef = $prefix . '_' . $seqNo;
        }

        return $candidateRef;
    }

    /**
     * Record matter-scoped activity for dashboard recent-activity feeds.
     */
    public static function touchRecentActivity(int $matterId, string $type): void
    {
        if ($matterId <= 0) {
            return;
        }

        static::query()
            ->where('id', $matterId)
            ->update([
                'updated_at' => now(),
                'updated_at_type' => $type,
            ]);
    }

}
