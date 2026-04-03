<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AnzscoOccupation extends Model
{
    protected $table = 'anzsco_occupations';

    protected $fillable = [
        'anzsco_code',
        'occupation_title',
        'occupation_title_normalized',
        'skill_level',
        'is_on_mltssl',
        'is_on_stsol',
        'is_on_rol',
        'is_on_csol',
        'assessing_authority',
        'assessment_validity_years',
        'additional_info',
        'alternate_titles',
        'is_active',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'is_on_mltssl' => 'boolean',
        'is_on_stsol' => 'boolean',
        'is_on_rol' => 'boolean',
        'is_on_csol' => 'boolean',
        'is_active' => 'boolean',
        'skill_level' => 'integer',
        'assessment_validity_years' => 'integer',
    ];

    /**
     * Boot method to auto-set normalized title and audit fields
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->occupation_title_normalized = strtolower($model->occupation_title);
            if (Auth::check()) {
                $model->created_by = Auth::id();
            }
        });

        static::updating(function ($model) {
            $model->occupation_title_normalized = strtolower($model->occupation_title);
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });
    }

    /**
     * Scope to search by code or title
     */
    public function scopeSearch($query, $searchTerm)
    {
        $searchLower = strtolower($searchTerm);
        
        return $query->where(function($q) use ($searchTerm, $searchLower) {
            $q->where('anzsco_code', 'LIKE', "%{$searchTerm}%")
              ->orWhere('occupation_title', 'LIKE', "%{$searchTerm}%")
              ->orWhere('occupation_title_normalized', 'LIKE', "%{$searchLower}%")
              ->orWhere('alternate_titles', 'LIKE', "%{$searchTerm}%");
        });
    }

    /**
     * Scope to get only active occupations
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get occupation lists as an array
     * MLTSSL = Medium and Long-term Strategic Skills List
     * STSOL = Short-term Skilled Occupation List
     * ROL = Regional Occupation List
     * CSOL = Core Skills Occupation List
     */
    public function getOccupationListsAttribute()
    {
        $lists = [];
        if ($this->is_on_mltssl) $lists[] = 'MLTSSL';
        if ($this->is_on_stsol) $lists[] = 'STSOL';
        if ($this->is_on_rol) $lists[] = 'ROL';
        if ($this->is_on_csol) $lists[] = 'CSOL';
        return $lists;
    }

    /**
     * Get occupation lists as a string
     */
    public function getOccupationListsStringAttribute()
    {
        return implode(', ', $this->occupation_lists);
    }

    /**
     * Creator relationship
     */
    public function creator()
    {
        return $this->belongsTo(Staff::class, 'created_by');
    }

    /**
     * Updater relationship
     */
    public function updater()
    {
        return $this->belongsTo(Staff::class, 'updated_by');
    }
}

