<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

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

    protected $hidden = [
        'otp_code',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'otp_sent_at' => 'datetime',
        'otp_expires_at' => 'datetime',
    ];

    /**
     * Hash plain OTP codes before saving to the database.
     */
    public function setOtpCodeAttribute($value): void
    {
        $stringValue = (string) $value;
        if (str_starts_with($stringValue, '$2y$') || str_starts_with($stringValue, '$2a$')) {
            $this->attributes['otp_code'] = $stringValue;
        } else {
            $this->attributes['otp_code'] = Hash::make($stringValue);
        }
    }

    /**
     * Check if a supplied OTP code matches the stored code.
     * Uses Hash::check for bcrypt-hashed OTPs and constant-time hash_equals for legacy plain OTPs.
     */
    public function isValidOtp(string $inputCode): bool
    {
        $stored = (string) ($this->otp_code ?? '');
        if ($stored === '' || $inputCode === '') {
            return false;
        }

        if (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$')) {
            return Hash::check($inputCode, $stored);
        }

        return hash_equals($stored, $inputCode);
    }

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
        $this->attempts = (int) $this->attempts + 1;
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
