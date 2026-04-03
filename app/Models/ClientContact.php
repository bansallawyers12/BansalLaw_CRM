<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientContact extends Model
{
    protected $table = 'client_contacts';

    protected $fillable = [
        'admin_id',
        'client_id',
        'contact_type',
        'country_code',
        'phone',
        'is_verified',
        'verified_at',
        'verified_by'
    ];

    protected $casts = [
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    // Relationships
    public function verifications()
    {
        return $this->hasMany(PhoneVerification::class);
    }

    public function latestVerification()
    {
        return $this->hasOne(PhoneVerification::class)->latest();
    }

    public function verifier()
    {
        return $this->belongsTo(Staff::class, 'verified_by');
    }

    // Helper methods
    public function isAustralianNumber()
    {
        return $this->country_code === '+61';
    }

    public function needsVerification()
    {
        return $this->isAustralianNumber() && !$this->is_verified;
    }

    public function isPlaceholderNumber()
    {
        // Remove any non-digit characters
        $cleaned = preg_replace('/[^\d]/', '', $this->phone);
        
        // Check if it starts with 4444444444 (placeholder pattern)
        return strpos($cleaned, '4444444444') === 0;
    }

    public function canVerify()
    {
        return $this->isAustralianNumber() && !$this->isPlaceholderNumber();
    }
}
