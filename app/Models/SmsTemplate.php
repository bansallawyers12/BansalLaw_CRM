<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Kyslik\ColumnSortable\Sortable;

class SmsTemplate extends Model
{
    use Sortable;

    protected static function booted(): void
    {
        static::creating(function (SmsTemplate $model) {
            if ($model->usage_count === null) {
                $model->usage_count = 0;
            }
        });
    }

    protected $fillable = [
        'title',
        'message',
        'description',
        'variables',
        'category',
        'alias',
        'is_active',
        'usage_count',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'usage_count' => 'integer',
    ];

    public $sortable = [
        'id',
        'title',
        'category',
        'is_active',
        'usage_count',
        'created_at',
    ];

    /**
     * Get the admin user who created the template
     */
    public function creator()
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }

    /**
     * Get all SMS logs that used this template
     */
    public function smsLogs()
    {
        return $this->hasMany(SmsLog::class, 'template_id');
    }

    /**
     * Scope: Active templates only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Filter by category
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope: Find by alias
     */
    public function scopeByAlias($query, $alias)
    {
        return $query->where('alias', $alias);
    }

    /**
     * Set variables attribute - handles JSON string, array, or comma-separated string
     */
    public function setVariablesAttribute($value)
    {
        if (is_array($value)) {
            // If it's already an array, convert to comma-separated
            $this->attributes['variables'] = implode(',', array_map('trim', $value));
        } elseif (is_string($value)) {
            // Check if it's a JSON string
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                // It's a JSON string, convert to comma-separated
                $this->attributes['variables'] = implode(',', array_map('trim', $decoded));
            } else {
                // It's already a comma-separated string or empty, store as-is
                $this->attributes['variables'] = $value;
            }
        } else {
            $this->attributes['variables'] = null;
        }
    }

    /**
     * Get template variables as array
     */
    public function getVariablesArrayAttribute()
    {
        if (empty($this->variables)) {
            return [];
        }

        return array_map('trim', explode(',', $this->variables));
    }

    /**
     * Replace variables in message
     */
    public function replaceVariables($variableValues)
    {
        $message = $this->message;

        foreach ($variableValues as $key => $value) {
            $message = str_replace('{' . $key . '}', $value, $message);
        }

        return $message;
    }

    /**
     * Get category badge color
     */
    public function getCategoryBadgeAttribute()
    {
        return match($this->category) {
            'verification' => 'primary',
            'reminder' => 'warning',
            'notification' => 'info',
            'manual' => 'secondary',
            default => 'secondary',
        };
    }

    /**
     * Get formatted usage count
     */
    public function getFormattedUsageCountAttribute()
    {
        if ($this->usage_count >= 1000) {
            return number_format($this->usage_count / 1000, 1) . 'k';
        }

        return number_format($this->usage_count);
    }

    /**
     * Increment usage count
     */
    public function incrementUsage()
    {
        $this->increment('usage_count');
    }

    /**
     * Get missing variables from provided values
     */
    public function getMissingVariables($variableValues)
    {
        $templateVars = $this->variables_array;
        $providedVars = array_keys($variableValues);

        return array_diff($templateVars, $providedVars);
    }

    /**
     * Validate that all required variables are provided
     */
    public function hasAllVariables($variableValues)
    {
        $missing = $this->getMissingVariables($variableValues);
        return empty($missing);
    }

    /**
     * Get preview with sample data
     */
    public function getPreviewAttribute()
    {
        $sampleData = [
            'client_name'       => 'John Smith',
            'first_name'        => 'John',
            'matter_number'     => 'M2024001',
            'appointment_date'  => '15 Oct 2025',
            'appointment_time'  => '10:00 AM',
            'timeslot_full'     => '10:00 AM – 10:30 AM',
            'location'          => 'Melbourne',
            'staff_name'        => 'Sarah',
            'office_phone'      => '03 9602 1330',
            'verification_code' => '123456',
            'expiry_minutes'    => '5',
            'invoice_number'    => 'INV-2024-001',
        ];

        return $this->replaceVariables($sampleData);
    }

    /**
     * Get character count
     */
    public function getCharacterCountAttribute()
    {
        return mb_strlen($this->message);
    }

    /**
     * Estimate SMS segments (160 chars per segment)
     */
    public function getEstimatedSegmentsAttribute()
    {
        $length = $this->character_count;
        
        if ($length <= 160) {
            return 1;
        }
        
        // Multi-part SMS uses 153 chars per segment
        return (int) ceil($length / 153);
    }
}

