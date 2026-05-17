<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrustAccountingPeriod extends Model
{
    protected $table = 'trust_accounting_periods';

    protected $guarded = ['id'];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'locked_at' => 'datetime',
        'unlocked_at' => 'datetime',
    ];

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'locked_by_staff_id');
    }

    public function unlockedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'unlocked_by_staff_id');
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }
}
