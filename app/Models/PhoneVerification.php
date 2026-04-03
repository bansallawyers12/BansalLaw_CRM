<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PhoneVerification extends Model
{
    protected $fillable = [
        'client_contact_id',
        'client_id',
        'phone',
        'country_code',
        'otp_code',
        'is_verified',
        'verified_at',
        'verified_by',
        'otp_sent_at',
        'otp_expires_at',
        'attempts',
        'max_attempts'
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'otp_sent_at' => 'datetime',
        'otp_expires_at' => 'datetime',
    ];

    // Relationships
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

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_verified', false)
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

    // Helper methods
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
    }

    public static function generateOTP()
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}