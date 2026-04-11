<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientCourtHearing extends Model
{
    protected $table = 'client_court_hearings';

    protected $fillable = [
        'client_id',
        'client_matter_id',
        'court_name',
        'case_number',
        'judge_name',
        'hearing_date',
        'hearing_time',
        'hearing_type',
        'notes',
        'status',
    ];

    protected $casts = [
        'hearing_date' => 'date',
    ];

    public function client()
    {
        return $this->belongsTo(Admin::class, 'client_id');
    }

    public function matter()
    {
        return $this->belongsTo(ClientMatter::class, 'client_matter_id');
    }
}
