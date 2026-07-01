<?php

namespace App\Models;

use App\Models\Document;
use App\Services\CrmAccess\CrmAccessService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Kyslik\ColumnSortable\Sortable;
use Laravel\Sanctum\HasApiTokens;

class Staff extends Authenticatable
{
    use HasFactory, Notifiable, Sortable, HasApiTokens;

    /**
     * The authentication guard for staff (CRM login uses 'admin' guard).
     */
    protected $guard = 'admin';

    /**
     * The table associated with the model.
     */
    protected $table = 'staff';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'email_signature',
        'password', 
        'country_code',
        'phone',
        'status',
        'role',
        'position',
        'team',
        'permission',
        'office_id',
        'show_dashboard_per',
        'time_zone',
        'is_solicitor',
        'marn_number',
        'legal_practitioner_number',
        'company_name',
        'company_website',
        'business_address',
        'business_phone',
        'business_mobile',
        'business_email',
        'tax_number',
        'quick_access_enabled',
        'grant_super_admin_access',
        'can_delete_email_with_attachments',
        'trust_rule42_supervisor',
    ];

    /**
     * The attributes that should be hidden for arrays.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'status' => 'integer',
        'show_dashboard_per' => 'integer',
        'is_solicitor' => 'integer',
        'quick_access_enabled' => 'boolean',
        'grant_super_admin_access' => 'boolean',
        'can_delete_email_with_attachments' => 'boolean',
        'trust_rule42_supervisor' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Legacy attribute name for solicitor flag; persists to column `is_solicitor`.
     */
    protected function isMigrationAgent(): Attribute
    {
        return Attribute::make(
            get: fn () => (int) ($this->attributes['is_solicitor'] ?? 0),
            set: fn ($value) => ['is_solicitor' => ((bool) $value) ? 1 : 0],
        );
    }

    /**
     * Sortable columns for listings.
     */
    public $sortable = [
        'id',
        'first_name',
        'last_name',
        'email',
        'status',
        'created_at',
        'updated_at',
    ];

    // ============================================================
    // RELATIONSHIPS
    // ============================================================

    /**
     * Get the office/branch this staff member belongs to.
     */
    public function office(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'office_id');
    }

    /**
     * Get the role/user type for this staff member.
     */
    public function usertype(): BelongsTo
    {
        return $this->belongsTo(UserRole::class, 'role', 'id');
    }

    /**
     * Get the clients assigned to this staff member (as Legal Practitioner on matters).
     * Clients are in admins table with agent_id = this staff's id.
     */
    public function assignedClients(): HasMany
    {
        return $this->hasMany(Admin::class, 'agent_id');
    }

    /**
     * Get documents created by this staff member.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'created_by');
    }

    // ============================================================
    // ATTRIBUTES
    // ============================================================

    /**
     * Get full name attribute.
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name) ?: $this->email;
    }

    /**
     * Name shown on activity feed / timeline for this staff member.
     * Native super-admins (role 1) and staff with grant_super_admin_access show as "Super Admin".
     */
    public function activityFeedDisplayName(): string
    {
        if (app(CrmAccessService::class)->hasPermanentSuperAdminCapability($this)) {
            return 'Super Admin';
        }
        $full = trim((string) ($this->first_name ?? '') . ' ' . (string) ($this->last_name ?? ''));
        if ($full !== '') {
            return $full;
        }
        $first = trim((string) ($this->first_name ?? ''));

        return $first !== '' ? $first : 'Staff';
    }

    /**
     * Get avatar URL (replaces profile_img - uses static avatar.png).
     */
    public function getProfileImgAttribute(): string
    {
        return asset('img/avatar.png');
    }

    /**
     * Alias for business_address (used by views expecting address).
     */
    public function getAddressAttribute(): ?string
    {
        return $this->business_address;
    }

    /**
     * Set address (maps to business_address).
     */
    public function setAddressAttribute(?string $value): void
    {
        $this->attributes['business_address'] = $value;
    }

    /**
     * State from office/branch if available.
     */
    public function getStateAttribute(): ?string
    {
        return $this->office?->state ?? null;
    }

    /**
     * City from office/branch if available.
     */
    public function getCityAttribute(): ?string
    {
        return $this->office?->city ?? null;
    }

    /**
     * Zip from office/branch if available.
     */
    public function getZipAttribute(): ?string
    {
        return $this->office?->zip ?? null;
    }

    /**
     * Scope for active staff (status = 1).
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Whether the staff role grants a CRM module key (e.g. "20" = clients).
     *
     * Supports JSON object keys as strings or integers, and a legacy JSON list of module ids (e.g. [20,"21"]).
     */
    public function hasCrmModule(string $moduleId = '20'): bool
    {
        $roleModel = UserRole::find($this->role);
        if (! $roleModel || $roleModel->module_access === null || $roleModel->module_access === '') {
            return false;
        }
        $decoded = json_decode(trim((string) $roleModel->module_access), true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            return false;
        }

        $strKey = (string) $moduleId;
        $intKey = ctype_digit($strKey) ? (int) $strKey : null;

        if (array_is_list($decoded)) {
            foreach ($decoded as $v) {
                if ((string) $v === $strKey || ($intKey !== null && (int) $v === $intKey)) {
                    return true;
                }
            }

            return false;
        }

        if (array_key_exists($strKey, $decoded)) {
            return true;
        }

        return $intKey !== null && array_key_exists($intKey, $decoded);
    }

    /**
     * Role IDs allowed to open the front-desk check-in wizard (aligned with FrontDeskCheckInController).
     *
     * @see config('crm.front_desk_checkin_role_ids') — default includes role 14 (Calling / Reception).
     */
    public static function frontDeskCheckInRoleIds(): array
    {
        return array_values(array_unique(array_merge(
            config('crm.front_desk_checkin_role_ids', [1, 12, 14, 17]),
            config('crm_access.exempt_role_ids', [])
        )));
    }

    /**
     * Role IDs allowed to open Admin Console routes and menu entry.
     *
     * @see config('crm.admin_console_role_ids')
     */
    public static function adminConsoleRoleIds(): array
    {
        return config('crm.admin_console_role_ids', [1, 12, 17]);
    }

    /**
     * Whether this staff member may access Admin Console (/adminconsole).
     */
    public function canAccessAdminConsole(): bool
    {
        if ($this->hasEffectiveSuperAdminPrivileges()) {
            return true;
        }

        return in_array((int) ($this->role ?? 0), self::adminConsoleRoleIds(), true);
    }

    /**
     * Whether this staff member may use the front-desk check-in feature.
     */
    public function canAccessFrontDeskCheckIn(): bool
    {
        $sid = (int) ($this->id ?? 0);
        if ($sid > 0 && in_array($sid, config('crm_access.exempt_staff_ids', []), true)) {
            return true;
        }

        return in_array((int) ($this->role ?? 0), self::frontDeskCheckInRoleIds(), true);
    }

    public function hasEffectiveSuperAdminPrivileges(): bool
    {
        return app(CrmAccessService::class)->hasEffectiveSuperAdminPrivileges($this);
    }

    public function canToggleSuperAdminElevation(): bool
    {
        return app(CrmAccessService::class)->canToggleSuperAdminElevation($this);
    }

    /**
     * Role IDs that may delete emails and grant {@see canDeleteEmailWithAttachments()} to others.
     * Default: Super Admin (1) and Admin (17) — not Person Responsible (12).
     */
    public static function emailDeleteGrantRoleIds(): array
    {
        $ids = config('crm.email_delete_grant_role_ids', [1, 17]);

        return is_array($ids) ? array_values(array_map('intval', $ids)) : [1, 17];
    }

    /**
     * Whether the actor may toggle email delete permission on staff records.
     */
    public static function canGrantEmailDeleteWithAttachmentsPermission(?self $actor): bool
    {
        if (! $actor instanceof self) {
            return false;
        }

        return in_array((int) ($actor->role ?? 0), self::emailDeleteGrantRoleIds(), true);
    }

    /**
     * Whether this staff member may delete CRM email logs (and their attachments).
     */
    public function canDeleteEmailWithAttachments(): bool
    {
        if (in_array((int) ($this->role ?? 0), self::emailDeleteGrantRoleIds(), true)) {
            return true;
        }

        if ($this->hasEffectiveSuperAdminPrivileges()) {
            return true;
        }

        return (bool) ($this->can_delete_email_with_attachments ?? false);
    }

}
