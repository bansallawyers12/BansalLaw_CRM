<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Admin;
use App\Models\Staff;

class ClientArtReference extends Model
{
    protected $table = 'client_art_references';

    protected $fillable = [
        'client_id',
        'client_matter_id',
        'submission_last_date',
        'status_of_file',
        'hearing_time',
        'member_name',
        'outcome',
        'comments',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'submission_last_date' => 'date',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'client_id');
    }

    public function clientMatter(): BelongsTo
    {
        return $this->belongsTo(ClientMatter::class, 'client_matter_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'updated_by');
    }
}
