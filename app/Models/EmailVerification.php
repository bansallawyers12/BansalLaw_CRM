<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EmailVerification extends Model
{
    protected $fillable = [
        'client_email_id',
        'client_id',
        'email',
        'verification_token',
        'is_verified',
        'verified_at',
        'verified_by',
        'token_sent_at',
        'token_expires_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'token_sent_at' => 'datetime',
        'token_expires_at' => 'datetime',
    ];

    // Relationships
    public function clientEmail()
    {
        return $this->belongsTo(ClientEmail::class, 'client_email_id');
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
                     ->where('token_expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('token_expires_at', '<=', now());
    }

    public function scopeForEmail($query, $email)
    {
        return $query->where('email', $email);
    }

    // Helper methods
    public function isExpired()
    {
        return $this->token_expires_at && $this->token_expires_at->isPast();
    }

    public static function generateToken()
    {
        return Str::random(64);
    }
}
