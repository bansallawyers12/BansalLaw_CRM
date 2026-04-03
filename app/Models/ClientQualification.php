<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientQualification extends Model
{
    protected $table = 'client_qualifications';

    protected $fillable = [
        'admin_id',
        'client_id',
        'level',
        'name',
        'qual_college_name',
        'qual_campus',
        'country',
        'qual_state',
        'start_date',
        'finish_date',
        'relevant_qualification',
    ];
}


