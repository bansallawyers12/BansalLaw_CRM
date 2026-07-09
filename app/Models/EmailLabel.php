<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EmailLabel extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'color',
        'type',
        'icon',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the user that owns the label.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'user_id');
    }

    /**
     * Get the email logs that have this label.
     */
    public function emailLogs(): BelongsToMany
    {
        return $this->belongsToMany(EmailLog::class, 'email_label_email_log', 'email_label_id', 'email_log_id')
                    ->withTimestamps();
    }

    /**
     * Check if this is a system label.
     */
    public function isSystem(): bool
    {
        return $this->type === 'system';
    }

    /**
     * Check if this is a custom label.
     */
    public function isCustom(): bool
    {
        return $this->type === 'custom';
    }

    /**
     * Get the display icon for the label.
     */
    public function getDisplayIconAttribute(): string
    {
        if ($this->icon) {
            return $this->icon;
        }

        // Default icons based on label name
        $defaultIcons = [
            'inbox' => 'fa-solid fa-inbox',
            'sent' => 'fa-solid fa-paper-plane',
            'draft' => 'fa-solid fa-edit',
            'trash' => 'fa-solid fa-trash',
            'spam' => 'fa-solid fa-ban',
            'archive' => 'fa-solid fa-archive',
            'work' => 'fa-solid fa-briefcase',
            'personal' => 'fa-solid fa-user',
            'important' => 'fa-solid fa-star',
            'urgent' => 'fa-solid fa-exclamation-triangle',
        ];

        $labelName = strtolower($this->name);
        return $defaultIcons[$labelName] ?? 'fa-solid fa-tag';
    }

    /**
     * Get the formatted color with fallback.
     */
    public function getFormattedColorAttribute(): string
    {
        return $this->color ?: '#3B82F6';
    }

    /**
     * Scope to filter active labels.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter system labels.
     */
    public function scopeSystem($query)
    {
        return $query->where('type', 'system');
    }

    /**
     * Scope to filter custom labels.
     */
    public function scopeCustom($query)
    {
        return $query->where('type', 'custom');
    }

    /**
     * Scope to filter by user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where(function($q) use ($userId) {
            $q->where('user_id', $userId)
              ->orWhereNull('user_id'); // Include system labels
        });
    }
}
