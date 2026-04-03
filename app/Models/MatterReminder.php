<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatterReminder extends Model
{
    protected $table = 'matter_reminders';

    protected $fillable = [
        'visa_type',
        'client_matter_id',
        'type',
        'reminded_at',
        'reminded_by',
    ];

    protected $casts = [
        'reminded_at' => 'datetime',
    ];

    public function clientMatter(): BelongsTo
    {
        return $this->belongsTo(ClientMatter::class, 'client_matter_id');
    }

    public function remindedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'reminded_by');
    }

    public function scopeOfVisaType($query, string $visaType)
    {
        return $query->where('visa_type', $visaType);
    }
}
