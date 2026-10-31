<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\ClientContact;
use App\Models\ClientEmail;
use App\Models\ClientAddress;
use App\Models\ClientMatter;
use App\Models\ClientCourtHearing;
use App\Models\Matter;
use App\Models\Country;
use App\Models\Staff;
use App\Models\Branch;
use App\Support\EnsureDummyMatterStaff;
use App\Support\MatterScopedAccess;
use Illuminate\Support\Facades\Schema;

/**
 * ClientEditService
 * 
 * Handles data preparation for client edit page with optimized queries.
 * Eliminates N+1 query problems by eager loading relationships and
 * loading dropdown data once.
 * 
 * Used by:
 * - ClientsController@edit
 */
class ClientEditService
{
    /**
     * Get all data needed for client edit page with optimized queries
     * 
     * @param int $clientId
     * @return array
     */
    public function getClientEditData(int $clientId): array
    {
        // Get client data with company relationships eager loaded
        $clientData = $this->getClientData($clientId);

        // Always load matter dropdowns when we have an admin row (same screen as clients.edit).
        // Rely on storeLeadMatterFromEdit to enforce lead/client (and visibility).
        $matterFormForLead = null;
        if ($clientData) {
            $matterFormForLead = $this->getMatterFormOptionsForLead((bool) ($clientData->is_company ?? false));
        }

        return [
            'fetchedData' => $clientData,
            'clientContacts' => $this->getClientContacts($clientId),
            'emails' => $this->getClientEmails($clientId),
            'clientAddresses' => $this->getClientAddresses($clientId),
            'clientMatters' => $this->getClientMatters($clientId),
            'courtHearings' => $this->getCourtHearings($clientId),

            // Dropdown data - loaded ONCE to prevent N+1 queries
            'countries' => $this->getCountries(),
            'allMatters' => $this->getAllMattersForMatterTypeSelector((bool) ($clientData->is_company ?? false)),
            'matterFormForLead' => $matterFormForLead,
        ];
    }

    public const DEFAULT_PERSON_RESPONSIBLE_EMAIL = 'Ajay@bansallawyers.com.au';

    /** Fallback “Person assisting” row when no staff have that role (case-insensitive email match). */
    public const DEFAULT_PERSON_ASSISTING_EMAIL = 'Admin@bansallawyers.com.au';

    /** CRM `staff.role` for “Person Responsible”. */
    public const PERSON_RESPONSIBLE_STAFF_ROLE = 12;

    /** CRM `staff.role` for legacy “Person Assisting”. Kept so existing staff still appear. */
    public const PERSON_ASSISTING_STAFF_ROLE = 13;

    /** CRM `user_roles` id 18 = Paralegal (Australia). */
    public const PARALEGAL_AUSTRALIA_STAFF_ROLE = 18;

    /** CRM `user_roles` id 19 = Paralegal (India). */
    public const PARALEGAL_INDIA_STAFF_ROLE = 19;

    /** CRM `user_roles` id 16 = Solicitor (legal practitioner). */
    public const SOLICITOR_STAFF_ROLE = 16;

    /**
     * Legal practitioner dropdown: active Solicitor role (16), plus any active staff
     * flagged as a legal practitioner (is_solicitor), even when their CRM role is Admin.
     * If none, single fallback row for {@see DEFAULT_PERSON_RESPONSIBLE_EMAIL}.
     */
    public static function staffSelectableForSolicitorRole()
    {
        $staff = self::activeNamedStaffQuery()
            ->where(function ($q) {
                $q->where('role', self::SOLICITOR_STAFF_ROLE);
                if (Schema::hasColumn('staff', 'is_solicitor')) {
                    $q->orWhere('is_solicitor', 1);
                }
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'email']);

        if ($staff->isNotEmpty()) {
            return $staff->values();
        }

        $fallback = self::staffFallbackSingleByEmail(self::DEFAULT_PERSON_RESPONSIBLE_EMAIL);

        return $fallback ? collect([$fallback]) : collect();
    }

    /**
     * Person Responsible dropdown: active legal practitioners (Solicitor role 16 or
     * is_solicitor), plus any staff still on the legacy Person Responsible role (12).
     * If none, Ajay fallback email.
     */
    public static function staffSelectableForPersonResponsibleRole()
    {
        $staff = self::activeNamedStaffQuery()
            ->where(function ($q) {
                $q->whereIn('role', [self::SOLICITOR_STAFF_ROLE, self::PERSON_RESPONSIBLE_STAFF_ROLE]);
                if (Schema::hasColumn('staff', 'is_solicitor')) {
                    $q->orWhere('is_solicitor', 1);
                }
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'email']);

        if ($staff->isNotEmpty()) {
            return $staff->values();
        }

        $fallback = self::staffFallbackSingleByEmail(self::DEFAULT_PERSON_RESPONSIBLE_EMAIL);

        return $fallback ? collect([$fallback]) : collect();
    }

    /**
     * Roles that can be chosen in the matter Paralegal field.
     * Includes legacy Person Assisting so current staff still appear.
     *
     * @return list<int>
     */
    public static function paralegalMatterStaffRoleIds(): array
    {
        $ids = array_map('intval', (array) config('crm_roles.paralegal_role_ids', [
            self::PARALEGAL_AUSTRALIA_STAFF_ROLE,
            self::PARALEGAL_INDIA_STAFF_ROLE,
        ]));
        $ids[] = self::PERSON_ASSISTING_STAFF_ROLE;

        return array_values(array_unique(array_filter($ids, static fn (int $id) => $id > 0)));
    }

    /**
     * Paralegal dropdown on a matter.
     */
    public static function staffSelectableForPersonAssistingRole()
    {
        $staff = self::activeNamedStaffQuery()
            ->whereIn('role', self::paralegalMatterStaffRoleIds())
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'email']);

        if ($staff->isNotEmpty()) {
            return $staff->values();
        }

        $fallback = self::staffFallbackSingleByEmail(self::DEFAULT_PERSON_ASSISTING_EMAIL);

        return $fallback ? collect([$fallback]) : collect();
    }

    private static function activeNamedStaffQuery()
    {
        return Staff::query()
            ->where('status', 1)
            ->whereNotNull('first_name')
            ->where('first_name', '!=', '');
    }

    private static function staffFallbackSingleByEmail(string $email): ?Staff
    {
        $emailNorm = strtolower(trim($email));
        if ($emailNorm === '') {
            return null;
        }

        return Staff::query()
            ->where('status', 1)
            ->whereRaw('LOWER(TRIM(email)) = ?', [$emailNorm])
            ->first(['id', 'first_name', 'last_name', 'email']);
    }

    /**
     * Add matter: solicitors (16), person responsible (solicitors + legacy role 12), person assisting (13).
     */
    protected function getMatterFormOptionsForLead(bool $isCompany): array
    {
        return [
            'mattersForAdd'            => $this->getMattersForSubject($isCompany),
            'legalPractitioners'       => static::staffSelectableForSolicitorRole(),
            'personResponsibleOptions' => static::staffSelectableForPersonResponsibleRole(),
            'personAssistingOptions'   => static::staffSelectableForPersonAssistingRole(),
            'branchOffices'            => Schema::hasTable('branches')
                ? Branch::query()->orderBy('office_name')->get(['id', 'office_name'])
                : collect(),
        ];
    }

    /**
     * Dropdown data for "Add matter" on client detail (same options as client edit).
     */
    public function getMatterFormForAddMatter(int $clientId): ?array
    {
        $clientData = Admin::query()->where('id', $clientId)->first(['id', 'is_company']);
        if (! $clientData) {
            return null;
        }

        return $this->getMatterFormOptionsForLead((bool) ($clientData->is_company ?? false));
    }

    protected function getMattersForSubject(bool $isCompany)
    {
        return Matter::query()
            ->select('id', 'title', 'nick_name', 'stream')
            ->where('status', 1)
            ->forClientType($isCompany)
            ->orderBy('title')
            ->get();
    }

    /**
     * Get client basic data with company relationships eager loaded.
     */
    protected function getClientData(int $clientId)
    {
        return Admin::with(['company.contactPerson', 'company.tradingNames', 'company.directors.directorClient'])->find($clientId);
    }

    /**
     * Get client contact numbers
     * Falls back to admins table if no records in client_contacts
     * Always returns ClientContact models for consistency
     */
    protected function getClientContacts(int $clientId)
    {
        // Check if records exist in client_contacts table
        if (ClientContact::where('client_id', $clientId)->exists()) {
            return ClientContact::where('client_id', $clientId)->get();
        }
        
        // Fallback: Convert Admin data to ClientContact models (without saving to DB)
        // This ensures the blade template always receives ClientContact instances
        $admin = Admin::where('id', $clientId)->first();
        if ($admin && !empty($admin->phone)) {
            // Create a ClientContact instance from Admin data (temporary, not persisted)
            $clientContact = new ClientContact();
            $clientContact->id = null; // No DB record yet - will be created on save
            $clientContact->client_id = $clientId;
            $clientContact->contact_type = $admin->contact_type ?? 'Personal';
            $clientContact->country_code = $admin->country_code ?? '';
            $clientContact->phone = $admin->phone;
            $clientContact->is_verified = false; // Default to unverified
            $clientContact->verified_at = null;
            $clientContact->verified_by = null;
            
            // Mark as temporary so form knows to create new record on save
            $clientContact->exists = false; // Tell Eloquent this is a new model
            
            return collect([$clientContact]); // Return as collection
        }
        
        return collect(); // Return empty collection
    }

    /**
     * Get client email addresses
     * Falls back to admins table if no records in client_emails
     */
    protected function getClientEmails(int $clientId)
    {
        // Check if records exist in client_emails table
        if (ClientEmail::where('client_id', $clientId)->exists()) {
            return ClientEmail::where('client_id', $clientId)->get();
        }
        
        // Fallback to admins table
        if (Admin::where('id', $clientId)->exists()) {
            return Admin::select('email', 'email_type')
                ->where('id', $clientId)
                ->get();
        }
        
        return collect(); // Return empty collection
    }

    /**
     * Get client addresses
     */
    protected function getClientAddresses(int $clientId)
    {
        $currentAddress = ClientAddress::where('client_id', $clientId)
            ->orderByRaw('start_date DESC NULLS LAST, created_at DESC')
            ->first();

        return $currentAddress ? collect([$currentAddress]) : collect();
    }

    /**
     * Matters linked to this admin row (client or lead). client_matters.client_id stores admins.id.
     */
    protected function getClientMatters(int $clientId)
    {
        return MatterScopedAccess::filterMattersForViewer(
            ClientMatter::query()
                ->where('client_id', $clientId)
                ->with([
                    'matter:id,title,nick_name',
                    'workflowStage:id,name',
                ])
                ->orderByDesc('id')
                ->get()
        );
    }

    /**
     * Get court hearings for client, ordered by hearing date descending
     */
    protected function getCourtHearings(int $clientId)
    {
        $hearings = ClientCourtHearing::where('client_id', $clientId)
            ->with(['matter:id,client_unique_matter_no,sel_matter_id', 'matter.matter:id,title'])
            ->orderByDesc('hearing_date')
            ->orderByDesc('id');

        $allowedMatterIds = MatterScopedAccess::accessibleMatterIdsForClient($clientId);
        $hearings->where(function ($q) use ($allowedMatterIds) {
            $q->whereNull('client_matter_id')
                ->orWhere('client_matter_id', '<=', 0)
                ->orWhereIn('client_matter_id', $allowedMatterIds === [] ? [0] : $allowedMatterIds);
        });

        return $hearings->get();
    }

    /**
     * Matter types for the edit-page "Law Matter Type" dropdown.
     * Scoped with forClientType so options match Matter::allowedForClientIsCompany on save.
     */
    protected function getAllMattersForMatterTypeSelector(bool $isCompany)
    {
        return Matter::select('id', 'title', 'nick_name', 'stream')
            ->where('status', 1)
            ->forClientType($isCompany)
            ->orderBy('title')
            ->get();
    }

    /**
     * Get countries for dropdown
     * Kept for edit-page dropdowns (e.g. matter-related country fields).
     */
    protected function getCountries()
    {
        return Country::select('id', 'name', 'sortname', 'phonecode')
            ->orderBy('name', 'ASC')
            ->get();
    }
}
