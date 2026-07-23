<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailCalendarLink extends Model
{
    public const TYPE_STAFF_EVENT = 'staff_event';

    public const TYPE_COURT_HEARING = 'court_hearing';

    public const STATUS_MERGED = 'merged';

    public const STATUS_PENDING = 'pending';

    protected $fillable = [
        'email_log_id',
        'calendar_type',
        'calendar_id',
        'event_type',
        'event_title',
        'starts_at',
        'ends_at',
        'location',
        'source',
        'status',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function emailLog(): BelongsTo
    {
        return $this->belongsTo(EmailLog::class, 'email_log_id');
    }
}
