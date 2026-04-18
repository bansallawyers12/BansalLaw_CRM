<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientMatterTask extends Model
{
    protected $table = 'client_matter_tasks';

    protected $fillable = [
        'client_matter_id',
        'client_id',
        'title',
        'is_done',
        'sort_order',
        'created_by',
    ];

    protected $casts = [
        'is_done' => 'boolean',
    ];

    public function clientMatter(): BelongsTo
    {
        return $this->belongsTo(ClientMatter::class, 'client_matter_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'client_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }
}
