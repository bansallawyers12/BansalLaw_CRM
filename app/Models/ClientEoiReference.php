<?php
namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Admin;
use App\Models\AnzscoOccupation;
use App\Models\Staff;

class ClientEoiReference extends Model
{
    protected $table = 'client_eoi_references';

    protected $fillable = [
        'client_id',
        'admin_id',
        'EOI_number',
        'EOI_subclass',
        'EOI_occupation',
        'anzsco_occupation_id',
        'EOI_point',
        'EOI_state',
        'EOI_submission_date',
        'EOI_ROI',
        'EOI_password',
        // New fields
        'eoi_subclasses',
        'eoi_states',
        'eoi_invitation_date',
        'eoi_nomination_date',
        'eoi_status',
        'created_by',
        'updated_by',
        // Confirmation workflow fields
        'staff_verified',
        'confirmation_date',
        'checked_by',
        'client_confirmation_status',
        'client_last_confirmation',
        'client_confirmation_notes',
        'client_confirmation_token',
        'confirmation_email_sent_at'
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'eoi_subclasses' => 'array',
        'eoi_states' => 'array',
        'EOI_submission_date' => 'date',
        'eoi_invitation_date' => 'date',
        'eoi_nomination_date' => 'date',
        'staff_verified' => 'boolean',
        'confirmation_date' => 'datetime',
        'client_last_confirmation' => 'datetime',
        'confirmation_email_sent_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = ['formatted_subclasses', 'formatted_states'];

    /**
     * Store password when setting (plain text, no encryption)
     */
    public function setEOIPasswordAttribute($value)
    {
        $this->attributes['EOI_password'] = $value ?: null;
    }

    /**
     * Get password for authorized viewing.
     * Returns plain value. For backward compatibility with previously encrypted records,
     * attempts decrypt first; if that fails, returns the value as-is.
     */
    public function getEOIPasswordDecrypted(): ?string
    {
        if (empty($this->EOI_password)) {
            return null;
        }
        try {
            return decrypt($this->EOI_password);
        } catch (DecryptException) {
            return $this->EOI_password;
        }
    }

    /**
     * Automatically sync scalar fields from arrays before saving
     * Maintains backward compatibility by setting first array value to scalar field
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            // Sync EOI_subclass from eoi_subclasses array (first value)
            if ($model->eoi_subclasses && is_array($model->eoi_subclasses) && count($model->eoi_subclasses) > 0) {
                $model->EOI_subclass = $model->eoi_subclasses[0];
            }

            // Sync EOI_state from eoi_states array (first value)
            if ($model->eoi_states && is_array($model->eoi_states) && count($model->eoi_states) > 0) {
                $model->EOI_state = $model->eoi_states[0];
            }
        });

        // Separate events for audit fields to prevent recursion
        static::creating(function ($model) {
            if (auth()->guard('admin')->check()) {
                $model->created_by = auth()->guard('admin')->id();
            }
        });

        static::updating(function ($model) {
            if (auth()->guard('admin')->check()) {
                $model->updated_by = auth()->guard('admin')->id();
            }
        });
    }

    /**
     * Relationship to client
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'client_id');
    }

    /**
     * Relationship to staff who created
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }

    /**
     * Relationship to staff who last updated
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'updated_by');
    }

    /**
     * Relationship to staff who verified EOI
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'checked_by');
    }

    /**
     * Relationship to ANZSCO occupation (for occupation autocomplete)
     */
    public function anzscoOccupation(): BelongsTo
    {
        return $this->belongsTo(AnzscoOccupation::class, 'anzsco_occupation_id');
    }

    /**
     * Get formatted subclasses as comma-separated string
     */
    public function getFormattedSubclassesAttribute(): string
    {
        return $this->eoi_subclasses ? implode(', ', $this->eoi_subclasses) : ($this->EOI_subclass ?? '');
    }

    /**
     * Get formatted states as comma-separated string
     */
    public function getFormattedStatesAttribute(): string
    {
        return $this->eoi_states ? implode(', ', $this->eoi_states) : ($this->EOI_state ?? '');
    }
}


