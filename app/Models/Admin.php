<?php
namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Kyslik\ColumnSortable\Sortable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Admin extends Authenticatable
{
    use Notifiable, Sortable, HasFactory, HasApiTokens; // Add HasApiTokens

    /** Staff reminder on client/lead detail — never show modal again */
    public const GOOGLE_REVIEW_REMINDER_NOT_INTERESTED = 'not_interested';

    /** Client has left a review — never show modal again */
    public const GOOGLE_REVIEW_REMINDER_REVIEW_RECEIVED = 'review_received';

	// The authentication guard for admin
    protected $guard = 'admin';

	/**
      * The attributes that are mass assignable.
      *
      * @var array
	*/
	protected $fillable = [
        'id',
        // Core Identity
        'first_name', 'last_name', 'email', 'email_type', 'password',
        // Role: deprecated for clients/leads (use type). Column kept for backward compat.
        'role',
        // Type: 'client' or 'lead' for CRM (replaces role=7 for clients/leads)
        'type',
        // CRM reference (unique per client/lead — format: PREFIX+YEAR+COUNTER)
        'client_id', 'client_counter',
        // Contact Information
        'phone', 'country_code', 'contact_type',
        // Address
        'country', 'state', 'city', 'address', 'zip',
        // Profile (profile_img removed - use avatar.png)
        'status',
        // Lead pipeline (admins.type = lead); clients may ignore
        'lead_status', 'followup_date', 'user_id',
        // Company Lead/Client Flag (company data stored in companies table)
        'is_company',
        // API/Service Tokens
        'service_token', 'token_generated_at',
        // Study / additional qualification flags (admins table)
        'australian_study', 'australian_study_date', 'specialist_education', 'specialist_education_date', 'regional_study', 'regional_study_date',
        // Verification (staff can verify documents)
        'visa_expiry_verified_at', 'visa_expiry_verified_by',
        'dob_verified_date', 'dob_verified_by',
        // Archive / soft-delete (Lead::softDelete sets timestamp; null = active)
        'is_archived', 'archived_by', 'archived_on',
        'is_deleted',
        // Personal
        'dob', 'age', 'gender', 'marital_status',
        // Client/Lead Tags
        'tagname',
        'refer_by',
        // Google review staff reminder (client/lead detail)
        'google_review_reminder_status',
        'google_review_reminder_snooze_until',
        // Timestamps
        'created_at', 'updated_at'
    ];

	/**
      * The attributes that should be hidden for arrays.
      *
      * @var array
	*/
    protected $hidden = [
        'password', 'remember_token'
    ];

    protected $casts = [
        'dob' => 'date',
        'dob_verified_date' => 'datetime',
        'followup_date' => 'datetime',
        'google_review_reminder_snooze_until' => 'datetime',
        'is_deleted' => 'datetime',
    ];

	public $sortable = [
        'id',
        'client_id',
        'first_name',
        'last_name',
        'email',
        'status',
        'created_at',
        'updated_at'
    ];

	public function countryData()
    {
        return $this->belongsTo('App\\Models\\Country','country');
    }

	// REMOVED: State model has been deleted
	// public function stateData()
    // {
    //     return $this->belongsTo('App\\Models\\State','state');
    // }
	public function usertype()
    {
        return $this->belongsTo('App\\Models\\UserRole', 'role', 'id');
    }


	/**
     * Get full name attribute
    */
	public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Get avatar URL (replaces profile_img - uses static avatar.png).
     */
    public function getProfileImgAttribute(): string
    {
        return asset('img/avatar.png');
    }

    /**
     * Get age attribute - calculates from DOB on-the-fly
     * Falls back to stored age if DOB is not available
     * Always returns accurate age when DOB exists
     * 
     * @return string|null
     */
    public function getAgeAttribute($value)
    {
        // If DOB exists, calculate age on-the-fly (always accurate)
        if ($this->dob && $this->dob !== null) {
            try {
                $dobDate = \Carbon\Carbon::parse($this->dob);
                $now = \Carbon\Carbon::now();
                
                // Don't calculate for future dates
                if ($dobDate->isFuture()) {
                    return $value; // Return stored value or null
                }
                
                $diff = $now->diff($dobDate);
                return $diff->y . ' years ' . $diff->m . ' months';
            } catch (\Exception $e) {
                // If calculation fails, return stored value
                return $value;
            }
        }
        
        // If no DOB, return stored age value (or null)
        return $value;
    }

    // ============================================================
    // STAFF RELATIONSHIPS (agent_id, created_by, verified_by reference admins or staff)
    // ============================================================

    /**
     * Get the clients assigned to this staff member (as agent)
     */
    public function assignedClients(): HasMany
    {
        return $this->hasMany(\App\Models\Admin::class, 'agent_id');
    }

    /**
     * Get the documents created by this staff member
     */
    public function createdDocuments(): HasMany
    {
        return $this->hasMany(\App\Models\Document::class, 'created_by');
    }

    /**
     * Alias for createdDocuments() - for backward compatibility
     */
    public function documents(): HasMany
    {
        return $this->hasMany(\App\Models\Document::class, 'created_by');
    }

    /**
     * Get DOB verifications done by this staff member
     */
    public function dobVerifications(): HasMany
    {
        return $this->hasMany(\App\Models\Admin::class, 'dob_verified_by');
    }

    /**
     * Get phone verifications done by this staff member
     */
    public function phoneVerifications(): HasMany
    {
        return $this->hasMany(\App\Models\Admin::class, 'phone_verified_by');
    }

    /**
     * Get visa expiry verifications done by this staff member
     */
    public function visaExpiryVerifications(): HasMany
    {
        return $this->hasMany(\App\Models\Admin::class, 'visa_expiry_verified_by');
    }

    // ============================================================
    // CLIENT RELATIONSHIPS
    // ============================================================

    /**
     * Get the partner/spouse details for this client
     */
    public function partner()
    {
        return $this->hasOne(\App\Models\ClientSpouseDetail::class, 'client_id');
    }

    /**
     * Get the test scores (IELTS, PTE, TOEFL, etc.) for this client
     * Used for English proficiency points calculation
     */
    public function testScores(): HasMany
    {
        return $this->hasMany(\App\Models\ClientTestScore::class, 'client_id');
    }

    /**
     * Get the occupations/skills assessments for this client
     * Used for occupation and work experience points calculation
     */
    public function occupations(): HasMany
    {
        return $this->hasMany(\App\Models\ClientOccupation::class, 'client_id');
    }

    /**
     * Get the qualifications for this client
     * Used for education points calculation
     */
    public function qualifications(): HasMany
    {
        return $this->hasMany(\App\Models\ClientQualification::class, 'client_id');
    }

    /**
     * Get the work experiences for this client
     * Used for employment points calculation
     */
    public function experiences(): HasMany
    {
        return $this->hasMany(\App\Models\ClientExperience::class, 'client_id');
    }

    /**
     * Get the relationships (partner, children, parents, etc.) for this client
     * Used for family member information
     */
    public function relationships(): HasMany
    {
        return $this->hasMany(\App\Models\ClientRelationship::class, 'client_id');
    }

    // ============================================================
    // COMPANY LEAD/CLIENT RELATIONSHIPS
    // ============================================================

    /**
     * Get the client matters for this client.
     */
    public function clientMatters(): HasMany
    {
        return $this->hasMany(\App\Models\ClientMatter::class, 'client_id');
    }

    /**
     * Get the company data for this admin (if it's a company)
     */
    public function company()
    {
        return $this->hasOne(Company::class, 'admin_id', 'id');
    }

    /**
     * Get companies where this person is the contact person
     */
    public function companiesAsContactPerson()
    {
        return $this->hasMany(Company::class, 'contact_person_id', 'id');
    }

    /**
     * Employer nominations listing this client/lead as the nominated person.
     */
    public function companyNominationsAsNominee(): HasMany
    {
        return $this->hasMany(\App\Models\CompanyNomination::class, 'nominated_client_id', 'id')
            ->orderBy('sort_order');
    }

    /**
     * Check if this is a company
     */
    public function isCompany(): bool
    {
        return (bool) $this->is_company;
    }

    /**
     * True if this admins row represents a CRM client/lead subject (can own matters, etc.).
     * Handles legacy rows: empty type with client_id, lead pipeline / converted, or old role=7.
     */
    public function isCrmClientOrLeadSubject(): bool
    {
        $rawType = trim((string) ($this->type ?? ''));
        // trim() does not remove ZWSP/BOM; those break strict equality with 'client'/'lead'
        $rawType = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{00A0}]/u', '', $rawType);
        $rawType = trim($rawType);
        $t = mb_strtolower($rawType, 'UTF-8');
        if (in_array($t, ['client', 'lead'], true)) {
            return true;
        }

        $crmRef = trim((string) ($this->client_id ?? ''));
        $crmRef = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{00A0}]/u', '', $crmRef);
        $crmRef = trim($crmRef);

        // Legacy: wrong/stale type string but CRM reference already assigned (e.g. imports)
        if ($crmRef !== '' && $t !== '') {
            return true;
        }

        if ($rawType !== '') {
            return false;
        }

        $ls = mb_strtolower(trim((string) ($this->lead_status ?? '')), 'UTF-8');
        if (in_array($ls, ['new', 'follow_up', 'not_qualified', 'hostile', 'converted'], true)) {
            return true;
        }
        if ($crmRef !== '') {
            return true;
        }
        if ((int) ($this->role ?? 0) === 7) {
            return true;
        }

        return false;
    }

    /**
     * Get display name (company name or personal name)
     * For companies: "Company Name (Contact: Person Name)"
     * For personal: "First Name Last Name"
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->is_company && $this->company) {
            $companyName = $this->company->company_name ?? 'Unnamed Company';
            if ($this->company->contactPerson) {
                $contactName = trim($this->company->contactPerson->first_name . ' ' . $this->company->contactPerson->last_name);
                return "{$companyName} (Contact: {$contactName})";
            }
            return $companyName;
        }
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Get company name or fallback to personal name
     */
    public function getCompanyNameOrPersonalNameAttribute(): string
    {
        if ($this->is_company && $this->company) {
            return $this->company->company_name ?? 'Unnamed Company';
        }
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Get company name from companies table (for clients with is_company=1).
     */
    public function getCompanyNameAttribute(): ?string
    {
        return $this->company?->company_name;
    }

    /**
     * Get company website from companies table.
     */
    public function getCompanyWebsiteAttribute(): ?string
    {
        return $this->company?->company_website;
    }
}
