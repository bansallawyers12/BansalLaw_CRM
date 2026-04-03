<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadMatterReference extends Model
{
    protected $table = 'lead_matter_references';

    protected $fillable = [
        'type',
        'lead_id',
        'matter_id',
        'checklist_sent_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'checklist_sent_at' => 'date',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'lead_id');
    }

    public function matter(): BelongsTo
    {
        return $this->belongsTo(Matter::class, 'matter_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'updated_by');
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
