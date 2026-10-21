<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffCalendarViewGrant extends Model
{
    protected $table = 'staff_calendar_view_grants';

    protected $fillable = [
        'staff_id',
        'visible_staff_id',
        'booking_calendar_type',
    ];

    public function viewer(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function visibleStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'visible_staff_id');
    }
}
