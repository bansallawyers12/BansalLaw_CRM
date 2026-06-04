<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffCalendarEvent extends Model
{
    public const TYPES = ['court', 'meeting', 'deadline', 'reminder', 'other'];

    protected $fillable = [
        'title',
        'event_type',
        'starts_at',
        'ends_at',
        'is_all_day',
        'calendar_type',
        'client_id',
        'client_matter_id',
        'location',
        'notes',
        'reminder_minutes',
        'reminder_sent_at',
        'created_by_staff_id',
    ];

    protected $casts = [
        'starts_at'        => 'datetime',
        'ends_at'          => 'datetime',
        'is_all_day'       => 'boolean',
        'reminder_minutes' => 'integer',
        'reminder_sent_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'client_id');
    }

    public function matter(): BelongsTo
    {
        return $this->belongsTo(ClientMatter::class, 'client_matter_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by_staff_id');
    }
}
