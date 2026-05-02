<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\ClientContact;
use App\Models\ClientEmail;
use App\Models\ClientVisaCountry;
use App\Models\ClientAddress;
use App\Models\ClientQualification;
use App\Models\ClientExperience;
use App\Models\ClientOccupation;
use App\Models\ClientTestScore;
use App\Models\ClientSpouseDetail;
use App\Models\ClientPassportInformation;
use App\Models\ClientTravelInformation;
use App\Models\ClientCharacter;
use App\Models\ClientRelationship;
use App\Models\ClientMatter;
use App\Models\ClientCourtHearing;
use App\Models\Matter;
use App\Models\Country;
use App\Models\Staff;
use App\Models\Branch;
use App\Support\EnsureDummyMatterStaff;

/**
 * ClientEditService
 * 
 * Handles data preparation for client edit page with optimized queries.
 * Eliminates N+1 query problems by eager loading relationships and
 * loading dropdown data once.
 * 
 * Used by:
 * - ClientsController@edit
 * - ClientPersonalDetailsController@clientdetailsinfo
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
        // Get client data with partner relationship eager loaded
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
            'visaCountries' => $this->getVisaCountries($clientId),
            'clientAddresses' => $this->getClientAddresses($clientId),
            'qualifications' => $this->getQualifications($clientId),
            'experiences' => $this->getExperiences($clientId),
            'clientOccupations' => $this->getOccupations($clientId),
            'testScores' => $this->getTestScores($clientId),
            'ClientSpouseDetail' => $this->getSpouseDetail($clientId), // Keep for backward compatibility
            'clientPassports' => $this->getPassports($clientId),
            'clientTravels' => $this->getTravels($clientId),
            'clientCharacters' => $this->getCharacters($clientId),
            'clientPartners' => $this->getRelationships($clientId),
            'clientMatters' => $this->getClientMatters($clientId),
            'courtHearings' => $this->getCourtHearings($clientId),

            // Dropdown data - loaded ONCE to prevent N+1 queries
            'visaTypes' => $this->getVisaTypes(),
            'countries' => $this->getCountries(),
            'allMatters' => $this->getAllMattersForMatterTypeSelector((bool) ($clientData->is_company ?? false)),
            'matterFormForLead' => $matterFormForLead,
        ];
    }

    /**
     * Dropdown data for "add matter" on lead/client edit.
     * Loads ALL active real staff (excludes internal dummy/placeholder accounts).
     */
    protected function getMatterFormOptionsForLead(bool $isCompany): array
    {
        // All active non-dummy staff for flexible assignment
        $allActiveStaff = Staff::query()
            ->where('status', 1)
            ->where(function ($q) {
                $q->whereNull('email')
                  ->orWhere('email', 'not like', '%.internal');
            })
            ->whereNotNull('first_name')
            ->where('first_name', '!=', '')
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get(['id', 'first_name', 'last_name', 'email']);

        return [
            'mattersForAdd'          => $this->getMattersForSubject($isCompany),
            'legalPractitioners'     => $allActiveStaff,
            'personResponsibleOptions' => $allActiveStaff,
            'personAssistingOptions' => $allActiveStaff,
            'branchOffices'          => Branch::query()->orderBy('office_name')->get(['id', 'office_name']),
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
     * Get client basic data with partner and company relationships eager loaded
     * This prevents N+1 query when accessing $fetchedData->partner or $fetchedData->company in blade
     */
    protected function getClientData(int $clientId)
    {
        return Admin::with(['partner', 'company.contactPerson', 'company.tradingNames', 'company.directors.directorClient', 'company.nominations.nominatedClient', 'company.sponsorships'])->find($clientId);
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
     * Get visa countries with eager loaded matter relationship
     * Prevents N+1 query when accessing visa->matter in blade
     */
    protected function getVisaCountries(int $clientId)
    {
        return ClientVisaCountry::where('client_id', $clientId)
            ->with(['matter:id,title,nick_name'])  // Eager load to prevent N+1
            ->orderBy('visa_expiry_date', 'desc')
            ->get() ?? [];
    }

    /**
     * Get client addresses
     */
    protected function getClientAddresses(int $clientId)
    {
        return ClientAddress::where('client_id', $clientId)
            ->orderByRaw('start_date DESC NULLS LAST, created_at DESC')
            ->get() ?? [];
    }

    /**
     * Get educational qualifications
     */
    protected function getQualifications(int $clientId)
    {
        return ClientQualification::where('client_id', $clientId)->orderByRaw('finish_date DESC NULLS LAST')->get() ?? [];
    }

    /**
     * Get work experiences
     */
    protected function getExperiences(int $clientId)
    {
        return ClientExperience::where('client_id', $clientId)->orderByRaw('job_finish_date DESC NULLS LAST')->get() ?? [];
    }

    /**
     * Get occupations
     */
    protected function getOccupations(int $clientId)
    {
        return ClientOccupation::where('client_id', $clientId)->get() ?? [];
    }

    /**
     * Get test scores
     */
    protected function getTestScores(int $clientId)
    {
        return ClientTestScore::where('client_id', $clientId)->get() ?? [];
    }

    /**
     * Get spouse details
     */
    protected function getSpouseDetail(int $clientId)
    {
        return ClientSpouseDetail::where('client_id', $clientId)->first() ?? [];
    }

    /**
     * Get passport information
     */
    protected function getPassports(int $clientId)
    {
        return ClientPassportInformation::where('client_id', $clientId)->get() ?? [];
    }

    /**
     * Get travel information ordered by arrival date (oldest first)
     * NULL dates are placed at the end
     */
    protected function getTravels(int $clientId)
    {
        return ClientTravelInformation::where('client_id', $clientId)
            ->orderByRaw('travel_arrival_date DESC NULLS LAST, created_at DESC')
            ->get() ?? [];
    }

    /**
     * Get character information
     */
    protected function getCharacters(int $clientId)
    {
        return ClientCharacter::where('client_id', $clientId)->get() ?? [];
    }

    /**
     * Get family relationships with eager loaded related client
     * Prevents N+1 query when accessing partner->relatedClient in blade
     */
    protected function getRelationships(int $clientId)
    {
        return ClientRelationship::where('client_id', $clientId)
            ->with(['relatedClient:id,first_name,last_name,email,phone,client_id'])  // Eager load to prevent N+1
            ->get() ?? [];
    }

    /**
     * Matters linked to this admin row (client or lead). client_matters.client_id stores admins.id.
     */
    protected function getClientMatters(int $clientId)
    {
        return ClientMatter::query()
            ->where('client_id', $clientId)
            ->with([
                'matter:id,title,nick_name',
                'workflowStage:id,name',
            ])
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Get court hearings for client, ordered by hearing date descending
     */
    protected function getCourtHearings(int $clientId)
    {
        return ClientCourtHearing::where('client_id', $clientId)
            ->with(['matter:id,client_unique_matter_no,sel_matter_id', 'matter.matter:id,title'])
            ->orderByDesc('hearing_date')
            ->orderByDesc('id')
            ->get();
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
     * Get visa types for dropdown
     * Loaded once and passed to view to prevent multiple queries
     */
    protected function getVisaTypes()
    {
        return Matter::select('id', 'title', 'nick_name')
            ->where('title', 'not like', '%skill assessment%')
            ->where('status', 1)
            ->orderBy('title', 'ASC')
            ->get();
    }

    /**
     * Get countries for dropdown
     * Loaded once and passed to view to prevent N+1 query in passport loop
     */
    protected function getCountries()
    {
        return Country::select('id', 'name', 'sortname', 'phonecode')
            ->orderBy('name', 'ASC')
            ->get();
    }
}

