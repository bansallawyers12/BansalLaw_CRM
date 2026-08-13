<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Model;

/**
 * Note Model
 * 
 * Represents both regular notes and actions (formerly called tasks/followups) in the system.
 * 
 * Database field clarifications for the Action feature:
 * - is_action: When set to 1, this note is an Action item. 0 = regular note
 * - task_group (field name preserved): The action category (Call, Checklist, Review, Query, Urgent, Personal Action)
 * - action_date: The scheduled date for the action
 * - task_status (in ActivitiesLog): Action completion status (0 = incomplete, 1 = completed)
 * - assigned_to: The staff member assigned to complete this action
 * - status: '0' = active/incomplete, '1' = completed
 * 
 * Note: Field names contain "task" and "followup" for database compatibility but refer to Actions in the UI
 */
class Note extends Model
{
    use Notifiable;

    protected $fillable = [
        'id','user_id','client_id','lead_id','unique_group_id','title','description','note_deadline','mail_id','type','pin','action_date','is_action','assigned_to','status','task_group','matter_id','mobile_number','created_at', 'updated_at'
    ];

	public $sortable = ['id', 'created_at', 'updated_at','task_group','action_date'];


    /**
     * Get the client that owns the note.
     */
    public function client()
    {
        return $this->belongsTo(Admin::class, 'client_id');
    }

    /**
     * Get the staff member who created the note.
     */
    public function user()
    {
        return $this->belongsTo(Staff::class, 'user_id');
    }

    /**
     * Alias for user() - Staff/Client/Lead terminology.
     */
    public function createdByStaff()
    {
        return $this->user();
    }

    /**
     * Get the staff member assigned to the note/action.
     */
    public function assignedUser()
    {
        return $this->belongsTo(Staff::class, 'assigned_to');
    }

    /**
     * Alias for assignedUser() - Staff/Client/Lead terminology.
     */
    public function assignedStaff()
    {
        return $this->assignedUser();
    }

    /**
     * Legacy method for backward compatibility
     */
    public function noteClient()
    {
        return $this->client();
    }

    /**
     * Legacy method for backward compatibility
     */
    public function noteUser()
    {
        return $this->user();
    }

    /**
     * Legacy method for backward compatibility
     */
    public function noteStaff()
    {
        return $this->user();
    }

    /**
     * Legacy method for backward compatibility
     */
    public function assigned_user()
    {
        return $this->assignedUser();
    }

    /**
     * Legacy alias - Staff terminology
     */
    public function assigned_staff()
    {
        return $this->assignedUser();
    }

    /**
     * Matter this action/note belongs to (client_matters.id).
     */
    public function clientMatter()
    {
        return $this->belongsTo(ClientMatter::class, 'matter_id');
    }

    /**
     * Prefer notes.matter_id; fall back to linked checklist task matter for legacy rows.
     */
    public function resolvedClientMatter(): ?ClientMatter
    {
        if ($this->relationLoaded('clientMatter') && $this->clientMatter) {
            return $this->clientMatter;
        }

        if (! empty($this->matter_id)) {
            $matter = $this->clientMatter;
            if ($matter instanceof ClientMatter) {
                return $matter;
            }
        }

        $taskMatterId = ClientMatterTask::where('note_id', $this->id)->value('client_matter_id');
        if ($taskMatterId) {
            return ClientMatter::find($taskMatterId);
        }

        return null;
    }

    /**
     * Matter reference for display (e.g. CRM_1), when linked.
     */
    public function matterReference(): ?string
    {
        $ref = $this->resolvedClientMatter()->client_unique_matter_no ?? null;

        return $ref !== null && $ref !== '' ? (string) $ref : null;
    }

    /**
     * Deep-link to client detail, preferring the linked matter when present.
     */
    public function clientDetailUrl(): ?string
    {
        if (! $this->client_id) {
            return null;
        }

        $encoded = base64_encode(convert_uuencode($this->client_id));
        $matterRef = $this->matterReference();
        if ($matterRef) {
            return url('/clients/detail/' . $encoded . '/' . $matterRef);
        }

        return url('/clients/detail/' . $encoded);
    }

    /**
     * Legacy relationship - Appointment model has been removed
     * This relationship is kept for backward compatibility but will return null
     * 
     * @deprecated Appointment system has been removed
     */
    public function lead()
    {
        // Legacy relationship returning empty relation object for eager-loading safety
        return $this->belongsTo(Admin::class, 'lead_id')->whereRaw('1=0');
    }

}
