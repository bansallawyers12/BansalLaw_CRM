<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Form956 extends Model
{
    protected $table = 'form956';

    protected $fillable = [
		'client_id',
		'client_matter_id',
		'agent_id',
		'form_type',
        'application_type',
        'date_lodged',
        'not_lodged',
        'business_address',
        'question_7',
        'question_10',
		'is_registered_migration_agent',
        'is_legal_practitioner',
        'is_exempt_person',
		'assistance_visa_application',
		'assistance_sponsorship',
        'assistance_nomination',
        'assistance_cancellation',
        'assistance_other',
		'assistance_other_details',
		'is_authorized_recipient',
		'agent_declared',
		'client_declared',
		'agent_declaration_date',
		'client_declaration_date'
	];


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


}


