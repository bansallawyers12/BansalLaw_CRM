<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientMatterReference extends Model
{
    protected $table = 'client_matter_references';

    protected $fillable = [
        'type',
        'client_id',
        'client_matter_id',
        'current_status',
        'payment_display_note',
        'institute_override',
        'visa_category_override',
        'comments',
        'checklist_sent_at',
        'is_pinned',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'checklist_sent_at' => 'date',
        'is_pinned' => 'boolean',
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

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }
}
