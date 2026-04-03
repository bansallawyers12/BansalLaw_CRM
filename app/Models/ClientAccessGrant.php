<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientAccessGrant extends Model
{
    protected $table = 'client_access_grants';

    protected $fillable = [
        'staff_id',
        'admin_id',
        'record_type',
        'grant_type',
        'access_type',
        'status',
        'quick_reason_code',
        'requester_note',
        'office_id',
        'office_label_snapshot',
        'team_id',
        'team_label_snapshot',
        'requested_at',
        'approved_at',
        'approved_by_staff_id',
        'starts_at',
        'ends_at',
        'revoked_at',
        'revoke_reason',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'approved_by_staff_id');
    }
}
