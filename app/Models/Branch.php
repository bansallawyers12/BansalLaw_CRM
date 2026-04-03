<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Kyslik\ColumnSortable\Sortable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Staff;

class Branch extends Authenticatable
{
    use Notifiable;
	use Sortable;
	
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'office_name',
        'address',
        'city',
        'state',
        'zip',
        'country',
        'email',
        'phone',
        'mobile',
        'contact_person',
        'choose_admin',
    ];

    /** 
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */ 
    protected $hidden = [
        'password', 'remember_token',
    ];
	
    // ============================================
    // RELATIONSHIPS
    // ============================================

    /**
     * Get matters handled by this office.
     */
    public function matters(): HasMany
    {
        return $this->hasMany(ClientMatter::class, 'office_id');
    }

    /**
     * Get active matters for this office.
     */
    public function activeMatters(): HasMany
    {
        return $this->hasMany(ClientMatter::class, 'office_id')
                    ->where('matter_status', 1);
    }

    /**
     * Get staff members assigned to this office.
     */
    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class, 'office_id');
    }

    /**
     * Get active staff for this office.
     */
    public function activeStaff(): HasMany
    {
        return $this->hasMany(Staff::class, 'office_id')->where('status', 1);
    }

    /**
     * Get documents directly assigned to this office (ad-hoc).
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'office_id');
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    /**
     * Get count of active matters.
     */
    public function getActiveMatterCountAttribute()
    {
        return $this->activeMatters()->count();
    }

    /**
     * Get count of active staff.
     */
    public function getActiveStaffCountAttribute()
    {
        return $this->activeStaff()->count();
    }

    /**
     * Get unique client count for this office (via matters).
     */
    public function getClientCountAttribute()
    {
        return $this->matters()->distinct('client_id')->count('client_id');
    }
}
