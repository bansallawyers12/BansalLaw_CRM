<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZohoCalendarEventLink extends Model
{
    public const TYPE_STAFF_EVENT = 'staff_event';

    public const TYPE_HEARING = 'hearing';

    public const TYPE_BOOKING = 'booking';

    public const STATUS_PENDING = 'pending';

    public const STATUS_LINKED = 'linked';

    public const STATUS_FAILED = 'failed';

    public const STATUS_UNLINKED = 'unlinked';

    public const DIRECTION_CRM_TO_ZOHO = 'crm_to_zoho';

    public const DIRECTION_ZOHO_TO_CRM = 'zoho_to_crm';

    protected $table = 'zoho_calendar_event_links';

    protected $fillable = [
        'local_type',
        'local_id',
        'staff_id',
        'zoho_event_uid',
        'zoho_calendar_uid',
        'client_id',
        'client_matter_id',
        'file_ref',
        'matter_ref',
        'sync_status',
        'direction',
        'etag',
        'last_error',
        'last_synced_at',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'client_id');
    }
}
