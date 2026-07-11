<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientEmail extends Model
{
    protected $table = 'client_emails';

    protected $fillable = [
        'admin_id',
		'client_id',
        'email_type',
        'is_shared_company_email',
        'email',
        'is_verified',
        'verified_at',
        'verified_by',
        'verification_token',
        'token_expires_at',
        'verification_sent_at',
    ];

    protected $casts = [
        'is_shared_company_email' => 'boolean',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'token_expires_at' => 'datetime',
        'verification_sent_at' => 'datetime',
    ];

    // Relationships
    public function verifier()
    {
        return $this->belongsTo(Staff::class, 'verified_by');
    }

    public function verifications()
    {
        return $this->hasMany(EmailVerification::class, 'client_email_id');
    }

    // Helper methods
    public function needsVerification()
    {
        return !$this->is_verified;
    }

    public function isTokenExpired()
    {
        return $this->token_expires_at && $this->token_expires_at->isPast();
    }
}


