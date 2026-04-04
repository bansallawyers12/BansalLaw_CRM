<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\ClientAddress;
use App\Models\ClientContact;
use App\Models\ClientEmail;
use App\Models\ClientPassportInformation;
use App\Models\ClientTravelInformation;
use App\Models\ClientCharacter;
use App\Models\ClientVisaCountry;
use App\Models\ClientTestScore;
use App\Models\ClientOccupation;
use App\Models\ClientQualification;
use App\Models\ClientExperience;
use App\Models\ActivitiesLog;
use App\Models\Matter;
use App\Services\ClientReferenceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class ClientImportService
{
    protected $referenceService;

    public function __construct(ClientReferenceService $referenceService)
    {
        $this->referenceService = $referenceService;
    }

    /**
     * Import client data from JSON
     * 
     * @param array $importData
     * @param bool $skipDuplicates
     * @return array ['success' => bool, 'client_id' => int|null, 'message' => string]
     */
    public function importClient(array $importData, $skipDuplicates = true)
    {
        DB::beginTransaction();

        try {
            // Validate import data structure
            if (!isset($importData['client'])) {
                throw new \Exception('Invalid import file: missing client data');
            }

            $clientData = $importData['client'];

            // Normalize office_visit_form_v1 format for CRM compatibility
            if (isset($importData['format']) && $importData['format'] === 'office_visit_form_v1') {
                // visa_expiry -> visaExpiry alias
                if (!isset($clientData['visaExpiry']) && isset($clientData['visa_expiry'])) {
                    $clientData['visaExpiry'] = $clientData['visa_expiry'];
                }
                // maritalStatus -> marital_status alias
                if (!isset($clientData['marital_status']) && isset($clientData['maritalStatus'])) {
                    $clientData['marital_status'] = $clientData['maritalStatus'];
                }
                // assessing_authority alias:
                // - if value is Yes/No-like, treat as skill_assessment
                // - otherwise treat as assessing authority list value
                if (!isset($clientData['skill_assessment']) && isset($clientData['assessing_authority'])) {
                    $aaRaw = trim((string) $clientData['assessing_authority']);
                    $aaLower = mb_strtolower($aaRaw);
                    if (in_array($aaLower, ['yes', 'y', 'true', '1', 'no', 'n', 'false', '0'], true)) {
                        $clientData['skill_assessment'] = $clientData['assessing_authority'];
                    } elseif (!isset($clientData['list'])) {
                        $clientData['list'] = $clientData['assessing_authority'];
                    }
                }
                // test_scores: overall -> overall_score alias
                if (isset($importData['test_scores']) && is_array($importData['test_scores'])) {
                    foreach ($importData['test_scores'] as &$ts) {
                        if (isset($ts['overall']) && !isset($ts['overall_score'])) {
                            $ts['overall_score'] = $ts['overall'];
                        }
                    }
                    unset($ts);
                }
            }

            // Check for duplicate email/phone if skip_duplicates is enabled.
            // The OR conditions are grouped inside a closure so the whereIn('type') restriction
            // applies to both clauses (fixes SQL precedence bug with bare orWhere).
            if ($skipDuplicates) {
                $email = isset($clientData['email']) ? trim((string) $clientData['email']) : '';
                $phone = isset($clientData['phone']) ? trim((string) $clientData['phone']) : '';

                if ($email !== '' || $phone !== '') {
                    $existingClient = Admin::whereIn('type', ['client', 'lead'])
                        ->where(function ($q) use ($email, $phone) {
                            if ($email !== '') {
                                $q->where('email', $email);
                            }
                            if ($phone !== '') {
                                $q->orWhere('phone', $phone);
                            }
                        })
                        ->first();

                    if ($existingClient) {
                        DB::rollBack();
                        $match = $email !== '' && $existingClient->email === $email
                            ? 'email ' . $email
                            : 'phone ' . $phone;
                        return [
                            'success' => false,
                            'client_id' => null,
                            'message' => 'Lead with same ' . $match . ' already exists. Import skipped.'
                        ];
                    }
                }
            }

            // Generate new client reference
            $reference = $this->referenceService->generateClientReference($clientData['first_name']);
            $client_id = $reference['client_id'];
            $client_current_counter = $reference['client_counter'];

            // Create the client
            $client = new Admin();
            $client->first_name = $clientData['first_name']; // Required field
            $client->last_name = $clientData['last_name'] ?? null;
            $client->email = $clientData['email']; // Required field (unique, NOT NULL)
            $client->phone = $clientData['phone'] ?? null;
            $client->country_code = $clientData['country_code'] ?? null;
            
            // Personal Information
            $client->dob = $this->parseDate($clientData['dob'] ?? null);
            $client->age = $clientData['age'] ?? null;
            $client->gender = $clientData['gender'] ?? null;
            $client->marital_status = $clientData['marital_status'] ?? null;
            
            // Address
            $client->address = $clientData['address'] ?? null;
            $client->city = $clientData['city'] ?? null;
            $client->state = $this->mapState($clientData['state'] ?? null);
            $client->country = $this->mapCountry($clientData['country'] ?? null);
            $client->zip = $clientData['zip'] ?? null;
            
            // Passport
            $client->country_passport = $clientData['country_passport'] ?? null;
            if (Schema::hasColumn('admins', 'passport_number') && isset($clientData['passport_number'])) {
                $client->passport_number = $clientData['passport_number'];
            }
            
            // Additional Contact (if exists in both systems)
            
            // Email and Contact Type (stored in admins table)
            $client->email_type = $clientData['email_type'] ?? null;
            $client->contact_type = $clientData['contact_type'] ?? null;
            
            // Optional bansalcrm2-style fields (if columns exist)
            $bansalOptional = [
                'att_email', 'att_phone', 'att_country_code',
                'nomi_occupation', 'skill_assessment', 'occupation_code',
                'high_quali_aus', 'high_quali_overseas',
                'relevant_work_exp_aus', 'relevant_work_exp_over',
                'naati_py', 'total_points',
                'service', 'assignee', 'lead_quality', 'comments_note', 'married_partner',
                'tagname', 'related_files',
            ];
            foreach ($bansalOptional as $field) {
                if (Schema::hasColumn('admins', $field) && array_key_exists($field, $clientData)) {
                    $client->{$field} = $clientData[$field];
                }
            }
            if (Schema::hasColumn('admins', 'visa_type') && array_key_exists('visa_type', $clientData)) {
                $client->visa_type = $clientData['visa_type'];
            }
            if (Schema::hasColumn('admins', 'visa_opt') && array_key_exists('visa_opt', $clientData)) {
                $client->visa_opt = $clientData['visa_opt'];
            }
            if (Schema::hasColumn('admins', 'visaExpiry') && array_key_exists('visaExpiry', $clientData)) {
                $client->visaExpiry = $this->parseDate($clientData['visaExpiry']);
            }

            // Other
            $client->naati_test = $clientData['naati_test'] ?? null;
            $client->naati_date = $this->parseDate($clientData['naati_date'] ?? null);
            $client->py_test = $clientData['py_test'] ?? null;
            $client->py_date = $this->parseDate($clientData['py_date'] ?? null);
            $client->source = $clientData['source'] ?? null;
            $client->type = $clientData['type'] ?? 'lead';
            $client->status = $clientData['status'] ?? 1;
            $client->agent_id = $clientData['agent_id'] ?? null;
            
            // Verification metadata (dates only, not staff IDs)
            $client->dob_verified_date = $this->parseDateTime($clientData['dob_verified_date'] ?? null);
            $client->dob_verify_document = $clientData['dob_verify_document'] ?? null;
            $client->phone_verified_date = $this->parseDateTime($clientData['phone_verified_date'] ?? null);
            $client->visa_expiry_verified_at = $this->parseDateTime($clientData['visa_expiry_verified_at'] ?? null);
            
            // System fields
            $client->client_counter = $client_current_counter;
            $client->client_id = $client_id;
            $client->password = Hash::make('CLIENT_IMPORT_' . time()); // Temporary password
            $client->verified = 0;
            $client->cp_status = 0;
            $client->cp_code_verify = 0;
            $client->australian_study = 0;
            $client->specialist_education = 0;
            $client->regional_study = 0;
            $client->is_archived = 0;
            // Note: archived_by is not set during import - imported clients are not archived
            // archived_by will be null for imported clients
            
            $client->save();
            $newClientId = $client->id;

            // Import occupations. Prefer top-level "occupations" array for multiple entries.
            // Backwards-compatible fallback: single client-level occupation fields.
            $occupationsProvided = isset($importData['occupations']) && is_array($importData['occupations']) && count($importData['occupations']) > 0;
            if ($occupationsProvided) {
                foreach ($importData['occupations'] as $occupationData) {
                    if (!is_array($occupationData)) {
                        continue;
                    }

                    $nomiOccupation = $occupationData['nomi_occupation']
                        ?? $occupationData['nomination_occupation']
                        ?? $occupationData['nominated_occupation']
                        ?? null;
                    $occupationCode = $occupationData['occupation_code'] ?? null;
                    $skillAssessmentRaw = $occupationData['skill_assessment'] ?? $occupationData['skill_assessment_yes_no'] ?? null;
                    $skillAssessment = $this->normalizeYesNoValue($skillAssessmentRaw);
                    if ($skillAssessment === null && is_string($skillAssessmentRaw) && trim($skillAssessmentRaw) !== '') {
                        $skillAssessment = trim($skillAssessmentRaw);
                    }

                    $assessingAuthority = $occupationData['list'] ?? $occupationData['assessing_authority'] ?? null;
                    $visaSubclass = $occupationData['visa_subclass'] ?? null;
                    $assessmentDate = $this->parseDate($occupationData['dates'] ?? $occupationData['assessment_date'] ?? null);
                    $expiryDate = $this->parseDate($occupationData['expiry_dates'] ?? $occupationData['expiry_date'] ?? null);
                    $relevantOccupation = $this->parseBooleanFlag($occupationData['relevant_occupation'] ?? null, 0);
                    $referenceNo = $occupationData['occ_reference_no'] ?? $occupationData['reference_no'] ?? null;

                    $hasOccupationData =
                        !empty($nomiOccupation) ||
                        !empty($occupationCode) ||
                        !empty($skillAssessment) ||
                        !empty($assessingAuthority) ||
                        !empty($visaSubclass) ||
                        !empty($assessmentDate) ||
                        !empty($expiryDate) ||
                        $relevantOccupation === 1 ||
                        !empty($referenceNo);

                    if (!$hasOccupationData) {
                        continue;
                    }

                    ClientOccupation::create([
                        'client_id'           => $newClientId,
                        'admin_id'            => Auth::id(),
                        'skill_assessment'    => $skillAssessment,
                        'nomi_occupation'     => $nomiOccupation,
                        'occupation_code'     => $occupationCode,
                        'list'                => $assessingAuthority,
                        'visa_subclass'       => $visaSubclass,
                        'dates'               => $assessmentDate,
                        'expiry_dates'        => $expiryDate,
                        'relevant_occupation' => $relevantOccupation,
                        'occ_reference_no'    => $referenceNo,
                    ]);
                }
            } else {
                // Fallback: single occupation from client-level fields (legacy format).
                $nomiOccupation = $clientData['nomi_occupation']
                    ?? $clientData['nomination_occupation']
                    ?? $clientData['nominated_occupation']
                    ?? null;
                $occupationCode = $clientData['occupation_code'] ?? null;
                $skillAssessmentRaw = $clientData['skill_assessment'] ?? null;
                $skillAssessment = $this->normalizeYesNoValue($skillAssessmentRaw);
                if ($skillAssessment === null && is_string($skillAssessmentRaw) && trim($skillAssessmentRaw) !== '') {
                    $skillAssessment = trim($skillAssessmentRaw);
                }
                $assessingAuthority = $clientData['list'] ?? $clientData['assessing_authority'] ?? null;
                $assessmentDate = $this->parseDate($clientData['dates'] ?? null);
                $expiryDate = $this->parseDate($clientData['expiry_dates'] ?? null);
                $relevantOccupation = $this->parseBooleanFlag($clientData['relevant_occupation'] ?? null, 0);

                if (!empty($nomiOccupation) || !empty($occupationCode) || !empty($skillAssessment) || !empty($assessingAuthority) || !empty($assessmentDate) || !empty($expiryDate) || $relevantOccupation === 1) {
                    ClientOccupation::create([
                        'client_id'           => $newClientId,
                        'admin_id'            => Auth::id(),
                        'nomi_occupation'     => $nomiOccupation,
                        'occupation_code'     => $occupationCode,
                        'skill_assessment'    => $skillAssessment,
                        'list'                => $assessingAuthority,
                        'dates'               => $assessmentDate,
                        'expiry_dates'        => $expiryDate,
                        'relevant_occupation' => $relevantOccupation,
                    ]);
                }
            }

            // Import addresses
            if (isset($importData['addresses']) && is_array($importData['addresses'])) {
                foreach ($importData['addresses'] as $addressData) {
                    ClientAddress::create([
                        'client_id' => $newClientId,
                        'admin_id' => Auth::id(),
                        'address' => $addressData['address'] ?? null,
                        'address_line_1' => $addressData['address_line_1'] ?? null,
                        'address_line_2' => $addressData['address_line_2'] ?? null,
                        'suburb' => $addressData['suburb'] ?? $addressData['city'] ?? null,
                        'state' => $addressData['state'] ?? null,
                        'country' => $addressData['country'] ?? null,
                        'zip' => $addressData['zip'] ?? null,
                        'regional_code' => $addressData['regional_code'] ?? null,
                        'start_date' => $this->parseDate($addressData['start_date'] ?? null),
                        'end_date' => $this->parseDate($addressData['end_date'] ?? null),
                        'is_current' => $addressData['is_current'] ?? 0,
                    ]);
                }
            }

            // Import qualifications (education history)
            // Saved into client_qualifications so they appear in Skills & Education on edit page.
            if (isset($importData['qualifications']) && is_array($importData['qualifications'])) {
                foreach ($importData['qualifications'] as $qualificationData) {
                    if (!is_array($qualificationData)) {
                        continue;
                    }

                    $level = $qualificationData['level'] ?? $qualificationData['qualification_level'] ?? null;
                    $name = $qualificationData['name'] ?? $qualificationData['qualification_name'] ?? null;
                    $college = $qualificationData['qual_college_name'] ?? $qualificationData['college_name'] ?? null;
                    $campus = $qualificationData['qual_campus'] ?? $qualificationData['campus'] ?? null;
                    $country = $qualificationData['country'] ?? $qualificationData['qual_country'] ?? null;
                    $state = $qualificationData['qual_state'] ?? $qualificationData['state'] ?? null;
                    $startDate = $this->parseDate($qualificationData['start_date'] ?? $qualificationData['qualification_start_date'] ?? null);
                    $finishDate = $this->parseDate($qualificationData['finish_date'] ?? $qualificationData['qualification_finish_date'] ?? null);
                    $relevant = $this->parseBooleanFlag($qualificationData['relevant_qualification'] ?? null, 0);

                    // Avoid inserting blank qualification rows.
                    $hasQualificationData =
                        !empty($level) ||
                        !empty($name) ||
                        !empty($college) ||
                        !empty($campus) ||
                        !empty($country) ||
                        !empty($state) ||
                        !empty($startDate) ||
                        !empty($finishDate) ||
                        $relevant === 1;

                    if (!$hasQualificationData) {
                        continue;
                    }

                    ClientQualification::create([
                        'client_id' => $newClientId,
                        'admin_id' => Auth::id(),
                        'level' => $level,
                        'name' => $name,
                        'qual_college_name' => $college,
                        'qual_campus' => $campus,
                        'country' => $country,
                        'qual_state' => $state,
                        'start_date' => $startDate,
                        'finish_date' => $finishDate,
                        'relevant_qualification' => $relevant,
                    ]);
                }
            }

            // Import work experiences (employment history)
            // Saved into client_experiences so they appear in Work Experience on edit page.
            if (isset($importData['experiences']) && is_array($importData['experiences'])) {
                foreach ($importData['experiences'] as $experienceData) {
                    if (!is_array($experienceData)) {
                        continue;
                    }

                    $jobTitle = $experienceData['job_title'] ?? $experienceData['title'] ?? null;
                    $jobCode = $experienceData['job_code'] ?? $experienceData['anzsco_code'] ?? null;
                    $employerName = $experienceData['job_emp_name'] ?? $experienceData['employer_name'] ?? null;
                    $jobCountry = $experienceData['job_country'] ?? $experienceData['country'] ?? null;
                    $jobState = $experienceData['job_state'] ?? $experienceData['address'] ?? null;
                    $jobType = $experienceData['job_type'] ?? null;
                    $jobStartDate = $this->parseDate($experienceData['job_start_date'] ?? $experienceData['start_date'] ?? null);
                    $jobFinishDate = $this->parseDate($experienceData['job_finish_date'] ?? $experienceData['finish_date'] ?? $experienceData['end_date'] ?? null);
                    $relevantExperience = $this->parseBooleanFlag($experienceData['relevant_experience'] ?? null, 0);
                    $fteMultiplier = $experienceData['fte_multiplier'] ?? null;

                    $hasExperienceData =
                        !empty($jobTitle) ||
                        !empty($jobCode) ||
                        !empty($employerName) ||
                        !empty($jobCountry) ||
                        !empty($jobState) ||
                        !empty($jobType) ||
                        !empty($jobStartDate) ||
                        !empty($jobFinishDate) ||
                        $relevantExperience === 1 ||
                        !empty($fteMultiplier);

                    if (!$hasExperienceData) {
                        continue;
                    }

                    ClientExperience::create([
                        'client_id' => $newClientId,
                        'admin_id' => Auth::id(),
                        'job_title' => $jobTitle,
                        'job_code' => $jobCode,
                        'job_emp_name' => $employerName,
                        'job_country' => $jobCountry,
                        'job_state' => $jobState,
                        'job_type' => $jobType,
                        'job_start_date' => $jobStartDate,
                        'job_finish_date' => $jobFinishDate,
                        'relevant_experience' => $relevantExperience,
                        'fte_multiplier' => $fteMultiplier,
                    ]);
                }
            }

            // Import contacts (phone numbers)
            // The lead edit page reads phone numbers from client_contacts, NOT from admins.phone.
            // We always ensure client.phone appears in client_contacts so it shows on the edit page,
            // UNLESS the exact same number was explicitly included in the contacts array already.
            $contactPhonesFromArray = [];
            if (isset($importData['contacts']) && is_array($importData['contacts'])) {
                foreach ($importData['contacts'] as $contactData) {
                    $phone = $contactData['phone'] ?? null;
                    if (empty($phone)) {
                        continue;
                    }
                    ClientContact::create([
                        'client_id'    => $newClientId,
                        'admin_id'     => Auth::id(),
                        'contact_type' => $contactData['contact_type'] ?? 'Personal',
                        'country_code' => $contactData['country_code'] ?? $clientData['country_code'] ?? null,
                        'phone'        => $phone,
                        'is_verified'  => $contactData['is_verified'] ?? false,
                        'verified_at'  => $this->parseDateTime($contactData['verified_at'] ?? null),
                    ]);
                    $contactPhonesFromArray[] = $phone;
                }
            }
            // Always persist client.phone to client_contacts (mirrors LeadController::store),
            // unless that exact number was already added from the contacts array above.
            if (!empty($clientData['phone']) && !in_array($clientData['phone'], $contactPhonesFromArray, true)) {
                ClientContact::create([
                    'client_id'    => $newClientId,
                    'admin_id'     => Auth::id(),
                    'contact_type' => $clientData['contact_type'] ?? 'Personal',
                    'country_code' => $clientData['country_code'] ?? null,
                    'phone'        => $clientData['phone'],
                    'is_verified'  => false,
                    'verified_at'  => null,
                ]);
            }

            // Import emails
            // The lead edit page reads emails from client_emails, NOT from admins.email.
            // We always ensure client.email appears in client_emails so it shows on the edit page,
            // UNLESS the exact same address was explicitly included in the emails array already.
            $emailAddrsFromArray = [];
            if (isset($importData['emails']) && is_array($importData['emails'])) {
                foreach ($importData['emails'] as $emailData) {
                    $emailAddr = $emailData['email'] ?? null;
                    if (empty($emailAddr)) {
                        continue;
                    }
                    ClientEmail::create([
                        'client_id'   => $newClientId,
                        'admin_id'    => Auth::id(),
                        'email_type'  => $emailData['email_type'] ?? 'Personal',
                        'email'       => $emailAddr,
                        'is_verified' => $emailData['is_verified'] ?? false,
                        'verified_at' => $this->parseDateTime($emailData['verified_at'] ?? null),
                    ]);
                    $emailAddrsFromArray[] = $emailAddr;
                }
            }
            // Always persist client.email to client_emails (mirrors LeadController::store),
            // unless that exact address was already added from the emails array above.
            if (!empty($clientData['email']) && !in_array($clientData['email'], $emailAddrsFromArray, true)) {
                ClientEmail::create([
                    'client_id'   => $newClientId,
                    'admin_id'    => Auth::id(),
                    'email_type'  => $clientData['email_type'] ?? 'Personal',
                    'email'       => $clientData['email'],
                    'is_verified' => false,
                    'verified_at' => null,
                ]);
            }

            // Import passport
            if (isset($importData['passport']) && is_array($importData['passport'])) {
                ClientPassportInformation::create([
                    'client_id' => $newClientId,
                    'admin_id' => Auth::id(),
                    'passport' => $importData['passport']['passport_number'] ?? $importData['passport']['passport'] ?? null, // Support both field names
                    'passport_country' => $importData['passport']['passport_country'] ?? null,
                    'passport_issue_date' => $this->parseDate($importData['passport']['passport_issue_date'] ?? null),
                    'passport_expiry_date' => $this->parseDate($importData['passport']['passport_expiry_date'] ?? null),
                ]);
            }

            // Import travel information
            if (isset($importData['travel']) && is_array($importData['travel'])) {
                foreach ($importData['travel'] as $travelData) {
                    ClientTravelInformation::create([
                        'client_id' => $newClientId,
                        'admin_id' => Auth::id(),
                        'travel_country_visited' => $travelData['travel_country_visited'] ?? null,
                        'travel_arrival_date' => $this->parseDate($travelData['travel_arrival_date'] ?? null),
                        'travel_departure_date' => $this->parseDate($travelData['travel_departure_date'] ?? null),
                        'travel_purpose' => $travelData['travel_purpose'] ?? null,
                    ]);
                }
            }

            // Import visa countries; resolve visa_type by matter title/nick_name when provided (cross-system portability)
            $lastVisaType = null;
            $lastVisaExpiry = null;
            $visaCountriesProvided = isset($importData['visa_countries']) && is_array($importData['visa_countries']) && count($importData['visa_countries']) > 0;

            if ($visaCountriesProvided) {
                foreach ($importData['visa_countries'] as $visaData) {
                    if (!is_array($visaData)) {
                        continue;
                    }
                    $resolvedType = $this->resolveVisaType($visaData);
                    $expiry = $this->parseDate($visaData['visa_expiry_date'] ?? null);
                    $grant = $this->parseDate($visaData['visa_grant_date'] ?? null);
                    ClientVisaCountry::create([
                        'client_id' => $newClientId,
                        'admin_id' => Auth::id(),
                        'visa_type' => $resolvedType,
                        'visa_description' => $visaData['visa_description'] ?? null,
                        'visa_expiry_date' => $expiry,
                        'visa_grant_date' => $grant,
                    ]);
                    $lastVisaType = $resolvedType;
                    $lastVisaExpiry = $expiry;
                }
            } else {
                // Fallback: build one visa record from client-level summary fields when visa_countries not provided
                $clientVisaType   = $clientData['visa_type'] ?? null;
                $clientVisaExpiry = $this->parseDate($clientData['visa_expiry'] ?? $clientData['visaExpiry'] ?? null);
                if (!empty($clientVisaType) || !empty($clientVisaExpiry)) {
                    $resolvedType = $this->resolveVisaType([
                        'visa_type'                  => $clientVisaType,
                        'visa_type_matter_title'     => $clientVisaType,
                        'visa_type_matter_nick_name' => $clientVisaType,
                    ]);
                    ClientVisaCountry::create([
                        'client_id'       => $newClientId,
                        'admin_id'        => Auth::id(),
                        'visa_type'       => $resolvedType,
                        'visa_description'=> $clientData['visa_opt'] ?? null,
                        'visa_expiry_date'=> $clientVisaExpiry,
                        'visa_grant_date' => null,
                    ]);
                    $lastVisaType   = $resolvedType;
                    $lastVisaExpiry = $clientVisaExpiry;
                }
            }

            // Sync last visa to client summary columns for sidebar/summary display
            if (($lastVisaType !== null || $lastVisaExpiry !== null) &&
                Schema::hasColumn('admins', 'visa_type') && Schema::hasColumn('admins', 'visaExpiry')) {
                $client->visa_type  = $lastVisaType ?? $client->visa_type;
                $client->visaExpiry = $lastVisaExpiry ?? $client->visaExpiry;
                $client->save();
            }

            // Import character information
            if (isset($importData['character']) && is_array($importData['character'])) {
                foreach ($importData['character'] as $characterData) {
                    ClientCharacter::create([
                        'client_id' => $newClientId,
                        'admin_id' => Auth::id(),
                        'type_of_character' => $characterData['type_of_character'] ?? null,
                        'character_detail' => $characterData['character_detail'] ?? null,
                        'character_date' => $this->parseDate($characterData['character_date'] ?? null),
                    ]);
                }
            }

            // Import test scores (unified format: test_type, listening, reading, writing, speaking, overall_score, test_date)
            if (isset($importData['test_scores']) && is_array($importData['test_scores'])) {
                foreach ($importData['test_scores'] as $testData) {
                    ClientTestScore::create([
                        'client_id' => $newClientId,
                        'admin_id' => Auth::id(),
                        'test_type' => $testData['test_type'] ?? null,
                        'listening' => $testData['listening'] ?? null,
                        'reading' => $testData['reading'] ?? null,
                        'writing' => $testData['writing'] ?? null,
                        'speaking' => $testData['speaking'] ?? null,
                        'overall_score' => $testData['overall_score'] ?? null,
                        'test_date' => $this->parseDate($testData['test_date'] ?? null),
                        'relevant_test' => $testData['relevant_test'] ?? 1,
                    ]);
                }
            }

            // Import activities (supports both Bansal Law CRM and bansalcrm2 export formats)
            $activitiesImported = false;
            if (isset($importData['activities']) && is_array($importData['activities'])) {
                foreach ($importData['activities'] as $activityData) {
                    $activityAttrs = [
                        'client_id'     => $newClientId,
                        'created_by'    => $activityData['created_by'] ?? Auth::id(),
                        'subject'       => $activityData['subject'] ?? 'Imported Activity',
                        'description'   => $activityData['description'] ?? null,
                        'activity_type' => $activityData['activity_type'] ?? 'activity',
                        'followup_date' => $this->parseDateTime($activityData['followup_date'] ?? null),
                        'task_group'    => $activityData['task_group'] ?? null,
                        'task_status'   => $activityData['task_status'] ?? 0,
                        'pin'           => $activityData['pin'] ?? 0,
                    ];
                    if (Schema::hasColumn('activities_logs', 'use_for') && array_key_exists('use_for', $activityData)) {
                        $activityAttrs['use_for'] = $activityData['use_for'];
                    }
                    ActivitiesLog::create($activityAttrs);
                    $activitiesImported = true;
                }
            }

            // Fallback: auto-generate activity from client.comments_note when no activities provided
            if (!$activitiesImported && !empty($clientData['comments_note'])) {
                $fallbackAttrs = [
                    'client_id'     => $newClientId,
                    'created_by'    => Auth::id(),
                    'subject'       => 'Office Visit Check-in Notes',
                    'description'   => $clientData['comments_note'],
                    'activity_type' => 'office_visit_checkin',
                    'task_status'   => 0,
                    'pin'           => 0,
                ];
                if (Schema::hasColumn('activities_logs', 'use_for')) {
                    $fallbackAttrs['use_for'] = null;
                }
                ActivitiesLog::create($fallbackAttrs);
                $activitiesImported = true;
            }

            // Root-level "notes" from lead form → always create an activity note
            // (independent of $activitiesImported — this field is specifically from the lead form)
            if (isset($importData['notes']) && trim((string) $importData['notes']) !== '') {
                $notesContent = trim((string) $importData['notes']);
                $notesAttrs = [
                    'client_id'     => $newClientId,
                    'created_by'    => Auth::id(),
                    'subject'       => 'Lead intake – additional information',
                    'description'   => '<p>' . nl2br(e($notesContent)) . '</p>',
                    'activity_type' => 'note',
                    'task_status'   => 0,
                    'pin'           => 0,
                ];
                if (Schema::hasColumn('activities_logs', 'use_for')) {
                    $notesAttrs['use_for'] = null;
                }
                ActivitiesLog::create($notesAttrs);
            }

            // Root-level "additional_fields" — extra form fields that don't map to CRM columns.
            // Accepted as either:
            //   • an object: { "Label": "value", ... }
            //   • an array:  [ { "label": "Label", "value": "value" }, ... ]
            // Creates one formatted activity note so staff can see all extra intake data at a glance.
            if (isset($importData['additional_fields'])) {
                $extraFields = $importData['additional_fields'];

                // Normalise array-of-objects format to simple associative array
                if (isset($extraFields[0]) && is_array($extraFields[0])) {
                    $normalised = [];
                    foreach ($extraFields as $item) {
                        $label = $item['label'] ?? $item['name'] ?? $item['key'] ?? null;
                        $val   = $item['value'] ?? $item['val'] ?? null;
                        if ($label !== null && $label !== '') {
                            $normalised[trim((string) $label)] = $val;
                        }
                    }
                    $extraFields = $normalised;
                }

                if (is_array($extraFields) && count($extraFields) > 0) {
                    // Build a cleanly formatted HTML table for the activity feed
                    $rows = '';
                    $isLast = false;
                    $keys   = array_keys($extraFields);
                    foreach ($keys as $idx => $label) {
                        $isLast     = $idx === count($keys) - 1;
                        $rowBorder  = $isLast ? '' : 'border-bottom: 1px solid #e9ecef;';
                        $labelHtml  = e(trim((string) $label));
                        $rawVal     = $extraFields[$label];
                        $valueHtml  = ($rawVal === null || $rawVal === '')
                            ? '<em style="color:#adb5bd;">—</em>'
                            : nl2br(e(trim((string) $rawVal)));
                        $rows .= '<tr>';
                        $rows .= '<td style="padding:6px 12px 6px 0;font-weight:600;color:#495057;'
                               . 'width:42%;vertical-align:top;' . $rowBorder . '">'
                               . $labelHtml . '</td>';
                        $rows .= '<td style="padding:6px 0;color:#212529;'
                               . 'vertical-align:top;' . $rowBorder . '">'
                               . $valueHtml . '</td>';
                        $rows .= '</tr>';
                    }

                    $description = '<div style="margin-top:4px;">'
                        . '<table style="width:100%;border-collapse:collapse;font-size:13px;line-height:1.5;">'
                        . '<tbody>' . $rows . '</tbody>'
                        . '</table></div>';

                    $extraAttrs = [
                        'client_id'     => $newClientId,
                        'created_by'    => Auth::id(),
                        'subject'       => 'Lead intake – form details',
                        'description'   => $description,
                        'activity_type' => 'note',
                        'task_status'   => 0,
                        'pin'           => 0,
                    ];
                    if (Schema::hasColumn('activities_logs', 'use_for')) {
                        $extraAttrs['use_for'] = null;
                    }
                    ActivitiesLog::create($extraAttrs);
                }
            }

            DB::commit();

            return [
                'success' => true,
                'client_id' => $newClientId,
                'client_id_reference' => $client_id,
                'message' => 'Lead imported successfully. Lead ID: ' . $client_id
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lead import error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'client_id' => null,
                'message' => 'Failed to import lead: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Parse date string to Y-m-d format
     * Handles multiple date formats: Y-m-d, d/m/Y, ISO8601, etc.
     */
    private function parseDate($date)
    {
        if (empty($date)) {
            return null;
        }

        try {
            // If already in Y-m-d format, return as is
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                return $date;
            }
            
            // Try to parse with Carbon (handles most formats)
            return Carbon::parse($date)->format('Y-m-d');
        } catch (\Exception $e) {
            Log::warning('Failed to parse date: ' . $date, ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Parse datetime string
     */
    private function parseDateTime($datetime)
    {
        if (empty($datetime)) {
            return null;
        }

        try {
            return Carbon::parse($datetime);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Parse boolean-like value to 1/0 integer.
     */
    private function parseBooleanFlag($value, $default = 0)
    {
        if ($value === null || $value === '') {
            return (int) $default;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            return ((int) $value) === 1 ? 1 : 0;
        }

        if (is_string($value)) {
            $normalized = mb_strtolower(trim($value));
            if (in_array($normalized, ['true', 'yes', 'y', 'on'], true)) {
                return 1;
            }
            if (in_array($normalized, ['false', 'no', 'n', 'off'], true)) {
                return 0;
            }
        }

        return (int) $default;
    }

    /**
     * Normalize yes/no values for fields stored as strings.
     */
    private function normalizeYesNoValue($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            return ((int) $value) === 1 ? 'Yes' : 'No';
        }

        if (is_string($value)) {
            $normalized = mb_strtolower(trim($value));
            if (in_array($normalized, ['yes', 'y', 'true', 'on'], true)) {
                return 'Yes';
            }
            if (in_array($normalized, ['no', 'n', 'false', 'off'], true)) {
                return 'No';
            }
        }

        return null;
    }

    /**
     * Map state (may need conversion from string to ID or vice versa)
     */
    private function mapState($state)
    {
        // If state is already an integer ID, return as is
        if (is_numeric($state)) {
            return $state;
        }

        // If state is a string (like "New South Wales"), try to find ID
        // For now, return as string - may need to implement state mapping
        return $state;
    }

    /**
     * Map country (may need conversion from string sortname to ID)
     */
    private function mapCountry($country)
    {
        // If country is already an integer ID, return as is
        if (is_numeric($country)) {
            return $country;
        }

        // If country is a string sortname (like "AU"), try to find ID
        if (is_string($country) && strlen($country) <= 3) {
            $countryModel = \App\Models\Country::where('sortname', $country)->first();
            if ($countryModel) {
                return $countryModel->id;
            }
        }

        // Return as is if no mapping found
        return $country;
    }

    /**
     * Resolve visa_type (Matter ID) for import.
     * Prefer portable identifiers so target system (e.g. bansalcrm2) maps correctly when matter IDs differ:
     * 1. visa_type_matter_nick_name -> lookup Matter by nick_name
     * 2. visa_type_matter_title -> lookup Matter by title
     * 3. Fall back to numeric visa_type (backwards compat with older exports)
     *
     * @param array $visaData
     * @return int|string|null
     */
    private function resolveVisaType(array $visaData)
    {
        $nick = isset($visaData['visa_type_matter_nick_name']) ? trim((string) $visaData['visa_type_matter_nick_name']) : null;
        if ($nick !== null && $nick !== '') {
            $matter = Matter::where('nick_name', $nick)->first();
            if ($matter) {
                return $matter->id;
            }
        }

        $title = isset($visaData['visa_type_matter_title']) ? trim((string) $visaData['visa_type_matter_title']) : null;
        if ($title !== null && $title !== '') {
            $matter = Matter::where('title', $title)->first();
            if ($matter) {
                return $matter->id;
            }
        }

        $id = $visaData['visa_type'] ?? null;
        if ($id !== null && $id !== '' && (is_int($id) || (is_string($id) && is_numeric($id)))) {
            return is_numeric($id) ? (int) $id : $id;
        }

        $label = $visaData['visa_type'] ?? null;
        if (is_string($label)) {
            $label = trim($label);
            if ($label !== '') {
                $labelLower = mb_strtolower($label);
                $matter = Matter::whereRaw('LOWER(title) = ?', [$labelLower])
                    ->orWhereRaw('LOWER(nick_name) = ?', [$labelLower])
                    ->first();
                if ($matter) {
                    return $matter->id;
                }
            }
        }

        return null;
    }
}
