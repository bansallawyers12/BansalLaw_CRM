<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class ZohoCalendarConnection extends Model
{
    protected $table = 'zoho_calendar_connections';

    protected $fillable = [
        'staff_id',
        'access_token',
        'refresh_token',
        'expires_at',
        'zoho_email',
        'accounts_server',
        'api_domain',
        'default_calendar_uid',
        'scopes',
        'connected_at',
        'last_error',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'connected_at' => 'datetime',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function staffMap(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ZohoCalendarStaffMap::class, 'staff_id', 'staff_id');
    }

    public function isConnected(): bool
    {
        return filled($this->access_token);
    }

    public function setAccessTokenAttribute(?string $value): void
    {
        $this->attributes['access_token'] = $value === null || $value === ''
            ? null
            : Crypt::encryptString($value);
    }

    public function getAccessTokenAttribute(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value;
        }
    }

    public function setRefreshTokenAttribute(?string $value): void
    {
        $this->attributes['refresh_token'] = $value === null || $value === ''
            ? null
            : Crypt::encryptString($value);
    }

    public function getRefreshTokenAttribute(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value;
        }
    }

    public function isExpired(int $skewSeconds = 120): bool
    {
        if (! $this->expires_at) {
            return true;
        }

        return $this->expires_at->lte(now()->addSeconds($skewSeconds));
    }
}
