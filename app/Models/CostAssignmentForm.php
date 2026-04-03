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
		'surcharge',
        'Dept_Base_Application_Charge',
        'Dept_Base_Application_Charge_no_of_person',
        'Dept_Base_Application_Charge_after_person',
        'Dept_Base_Application_Charge_after_person_surcharge',

		'Dept_Non_Internet_Application_Charge',
        'Dept_Non_Internet_Application_Charge_no_of_person',
        'Dept_Non_Internet_Application_Charge_after_person',
        'Dept_Non_Internet_Application_Charge_after_person_surcharge',

        'Dept_Additional_Applicant_Charge_18_Plus',
        'Dept_Additional_Applicant_Charge_18_Plus_no_of_person',
        'Dept_Additional_Applicant_Charge_18_Plus_after_person',
        'Dept_Additional_Applicant_Charge_18_Plus_after_person_surcharge',

        'Dept_Additional_Applicant_Charge_Under_18',
        'Dept_Additional_Applicant_Charge_Under_18_no_of_person',
        'Dept_Additional_Applicant_Charge_Under_18_after_person',
        'Dept_Additional_Applicant_Charge_Under_18_after_person_surcharge',

		'Dept_Subsequent_Temp_Application_Charge',
        'Dept_Subsequent_Temp_Application_Charge_no_of_person',
        'Dept_Subsequent_Temp_Application_Charge_after_person',
        'Dept_Subsequent_Temp_Application_Charge_after_person_surcharge',

		'Dept_Second_VAC_Instalment_Charge_18_Plus',
        'Dept_Second_VAC_Instalment_Charge_18_Plus_no_of_person',
        'Dept_Second_VAC_Instalment_Charge_18_Plus_after_person',
        'Dept_Second_VAC_Instalment_Charge_18_Plus_after_person_surcharge',

        'Dept_Second_VAC_Instalment_Under_18',
        'Dept_Second_VAC_Instalment_Under_18_no_of_person',
        'Dept_Second_VAC_Instalment_Under_18_after_person',
        'Dept_Second_VAC_Instalment_Under_18_after_person_surcharge',

        'Dept_Nomination_Application_Charge',
        'Dept_Sponsorship_Application_Charge',
		'Block_1_Ex_Tax',
		'Block_2_Ex_Tax',
		'Block_3_Ex_Tax',
		'additional_fee_1',
        'TotalDoHACharges',
        'TotalDoHASurcharges',
        'TotalBLOCKFEE'
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

    /**
     * Get the client matter associated with the form.
     */
    public function clientMatter(): BelongsTo
    {
        return $this->belongsTo(ClientMatter::class, 'client_matter_id');
    }

}


