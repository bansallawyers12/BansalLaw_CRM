<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientExperience extends Model
{
    protected $table = 'client_experiences'; // The name of the table

    protected $fillable = [
        'client_id',
        'admin_id',
        'job_title',
        'job_code',
        'job_country',
        'job_start_date',
        'job_finish_date',
        'relevant_experience',
        'job_emp_name',
        'job_state',
        'job_type',
        // Points calculation field
        'fte_multiplier'
    ];
}


