<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZohoCalendarUnlinkedEvent extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_LINKED = 'linked';

    public const STATUS_DISMISSED = 'dismissed';

    protected $table = 'zoho_calendar_unlinked_events';

    protected $fillable = [
        'zoho_event_uid',
        'zoho_calendar_uid',
        'title',
        'description',
        'location',
        'starts_at',
        'ends_at',
        'is_all_day',
        'etag',
        'raw_payload',
        'parsed_file_ref',
        'parsed_matter_ref',
        'status',
        'linked_local_type',
        'linked_local_id',
        'resolved_by_staff_id',
        'resolved_at',
        'last_seen_at',
        'last_error',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_all_day' => 'boolean',
        'raw_payload' => 'array',
        'resolved_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'resolved_by_staff_id');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }
}
