<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffAccessAudit extends Model
{
    public $timestamps = false;

    protected $table = 'staff_access_audit';

    protected $fillable = [
        'actor_staff_id',
        'target_staff_id',
        'action',
        'resource_type',
        'resource_id',
        'before',
        'after',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'created_at' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'actor_staff_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'target_staff_id');
    }
}
