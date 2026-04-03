<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadReminder extends Model
{
    protected $table = 'lead_reminders';

    protected $fillable = [
        'visa_type',
        'lead_id',
        'type',
        'reminded_at',
        'reminded_by',
    ];

    protected $casts = [
        'reminded_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'lead_id');
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
