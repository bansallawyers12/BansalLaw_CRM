<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhoneVerification extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_SUPERSEDED = 'superseded';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_MAX_ATTEMPTS = 'max_attempts';

    protected $fillable = [
        'client_contact_id',
        'client_id',
        'phone',
        'country_code',
        'otp_code',
        'status',
        'is_verified',
        'verified_at',
        'verified_by',
        'otp_sent_at',
        'otp_expires_at',
        'attempts',
        'max_attempts',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'otp_sent_at' => 'datetime',
        'otp_expires_at' => 'datetime',
    ];

    public function clientContact()
    {
        return $this->belongsTo(ClientContact::class);
    }

    public function client()
    {
        return $this->belongsTo(Admin::class, 'client_id');
    }

    public function verifier()
    {
        return $this->belongsTo(Staff::class, 'verified_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeActive($query)
    {
        return $query->pending()
            ->where('otp_expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('otp_expires_at', '<=', now());
    }

    public function scopeForPhone($query, $phone, $countryCode)
    {
        return $query->where('phone', $phone)
            ->where('country_code', $countryCode);
    }

    public function isExpired()
    {
        return $this->otp_expires_at && $this->otp_expires_at->isPast();
    }

    public function canAttempt()
    {
        return $this->attempts < $this->max_attempts;
    }

    public function incrementAttempts()
    {
        $this->increment('attempts');

        if ($this->attempts >= $this->max_attempts) {
            $this->update(['status' => self::STATUS_MAX_ATTEMPTS]);
        }
    }

    public static function generateOTP()
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
