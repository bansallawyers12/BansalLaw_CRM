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
    ];

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'client_id',
        'office_id',
        'sel_migration_agent',
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
        'updated_at_type',
        // Reference fields
        'department_reference',
        'other_reference'
    ];

    /**
     * Get the client that owns the matter.
     */
    public function client()
    {
        return $this->belongsTo(Admin::class, 'client_id');
    }

    /**
     * Get the migration agent assigned to the matter.
     */
    public function migrationAgent()
    {
        return $this->belongsTo(Staff::class, 'sel_migration_agent');
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

    /**
     * Check if this client matter is a TR (Temporary Residence) matter.
     * Used for TR sheet integration (e.g. when checklist is sent).
     */
    public function isTrMatter(): bool
    {
        return $this->getVisaSheetType() === 'tr';
    }

    /**
     * Get the visa sheet type for this matter (tr, visitor, student, pr, employer-sponsored).
     * Used for checklist-sent integration - updates the correct reference table per subclass.
     */
    public function getVisaSheetType(): ?string
    {
        $matter = $this->matter;
        if (!$matter) {
            return null;
        }
        $nick = strtolower(trim($matter->nick_name ?? ''));
        $title = strtolower(trim($matter->title ?? ''));

        $visaTypes = config('sheets.visa_types', []);
        foreach ($visaTypes as $sheetType => $config) {
            $nickNames = $config['matter_nick_names'] ?? [];
            $patterns = $config['matter_title_patterns'] ?? [];
            foreach ($nickNames as $n) {
                if ($nick === strtolower(trim((string) $n))) {
                    return $sheetType;
                }
            }
            foreach ($patterns as $p) {
                if (str_contains($title, strtolower(trim((string) $p)))) {
                    return $sheetType;
                }
            }
        }
        return null;
    }

    /**
     * When a checklist is sent for this matter, update the sheet reference table and log reminder.
     * Works for TR, Visitor, Student, PR, Employer Sponsored (by subclass).
     */
    public function recordChecklistSent(int $staffId = null): bool
    {
        $sheetType = $this->getVisaSheetType();
        if (!$sheetType) {
            return false;
        }
        $config = config("sheets.visa_types.{$sheetType}", []);
        $modelClass = $config['reference_model'] ?? null;
        $remindersTable = $config['reminders_table'] ?? null;
        $refType = $config['reference_type'] ?? $sheetType;

        if (!$modelClass || !class_exists($modelClass)) {
            return false;
        }

        $now = now();
        $lookup = [
            'client_id' => $this->client_id,
            'client_matter_id' => $this->id,
            'type' => $refType,
        ];
        $ref = $modelClass::firstOrCreate(
            $lookup,
            ['checklist_sent_at' => $now, 'created_by' => $staffId, 'updated_by' => $staffId]
        );
        if (!$ref->wasRecentlyCreated) {
            $ref->update(['checklist_sent_at' => $now, 'updated_by' => $staffId]);
        }

        if ($remindersTable && \Illuminate\Support\Facades\Schema::hasTable($remindersTable)) {
            \Illuminate\Support\Facades\DB::table($remindersTable)->insert([
                'visa_type' => $refType,
                'client_matter_id' => $this->id,
                'type' => 'email',
                'reminded_at' => $now,
                'reminded_by' => $staffId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Move matter to Checklist workflow stage so it appears in the Checklist tab
        $checklistStageId = \App\Services\VisaSheetService::getChecklistStageId();
        if ($checklistStageId) {
            $this->workflow_stage_id = $checklistStageId;
            $this->save();
        }

        return true;
    }

    /**
     * Boot method to add model events for debugging
     */
    protected static function boot()
    {
        parent::boot();

        // Log when department_reference or other_reference are being changed
        static::updating(function ($model) {
            if ($model->isDirty('department_reference') || $model->isDirty('other_reference')) {
                \Log::info('ClientMatter references being updated', [
                    'matter_id' => $model->id,
                    'client_id' => $model->client_id,
                    'old_department_reference' => $model->getOriginal('department_reference'),
                    'new_department_reference' => $model->department_reference,
                    'old_other_reference' => $model->getOriginal('other_reference'),
                    'new_other_reference' => $model->other_reference,
                    'changed_attributes' => $model->getDirty(),
                    'all_attributes' => $model->getAttributes(),
                    'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10)
                ]);
            }
        });

        // Log when model is saved
        static::saved(function ($model) {
            if ($model->wasChanged('department_reference') || $model->wasChanged('other_reference')) {
                \Log::info('ClientMatter references saved', [
                    'matter_id' => $model->id,
                    'client_id' => $model->client_id,
                    'department_reference' => $model->department_reference,
                    'other_reference' => $model->other_reference,
                ]);
            }
        });
    }
}
