<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ZohoCalendarStaffMap extends Model
{
    protected $table = 'zoho_calendar_staff_maps';

    protected $fillable = [
        'staff_id',
        'zoho_email',
        'display_name',
        'zoho_calendar_uid',
        'sync_enabled',
        'is_org_default',
        'last_synced_at',
        'last_error',
        'notes',
    ];

    protected $casts = [
        'sync_enabled' => 'boolean',
        'is_org_default' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function connection(): HasOne
    {
        return $this->hasOne(ZohoCalendarConnection::class, 'staff_id', 'staff_id');
    }

    public function isConnected(): bool
    {
        $connection = $this->relationLoaded('connection')
            ? $this->connection
            : $this->connection()->first();

        return $connection instanceof ZohoCalendarConnection
            && filled($connection->access_token);
    }

    public function effectiveCalendarUid(): ?string
    {
        if (filled($this->zoho_calendar_uid)) {
            return (string) $this->zoho_calendar_uid;
        }

        $connection = $this->relationLoaded('connection')
            ? $this->connection
            : $this->connection()->first();

        if ($connection && filled($connection->default_calendar_uid)) {
            return (string) $connection->default_calendar_uid;
        }

        return null;
    }
}
