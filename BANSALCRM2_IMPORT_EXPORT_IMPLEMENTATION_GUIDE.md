# Client Export/Import Implementation Guide for bansalcrm2

This guide provides step-by-step instructions to implement the client export/import feature in **bansalcrm2**, matching the functionality already implemented in **Bansal Law CRM**.

---

## Overview

You need to create:
1. **ClientExportService** - Exports client data to JSON
2. **ClientImportService** - Imports client data from JSON
3. **Export Button** - In Action dropdown on client list page
4. **Import Button & Modal** - In client list page header
5. **Routes** - Export and import routes
6. **Controller Methods** - Export and import methods in ClientController

**Visa type & expiry:** Export includes portable `visa_type_matter_title` and `visa_type_matter_nick_name` so import can resolve the correct Matter in the target system when IDs differ (e.g. Bansal Law CRM ↔ bansalcrm2). Import resolves visa type by nick_name or title, then syncs the last visa's type and expiry to the client (`visa_type`, `visaExpiry`) for sidebar display.

---

## Step 1: Create ClientExportService

**File:** `c:\xampp\htdocs\bansalcrm2\app\Services\ClientExportService.php`

```php
<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\ClientAddress;
use App\Models\ClientPhone; // Note: bansalcrm2 uses ClientPhone instead of ClientContact
use App\Models\ClientEmail;
use App\Models\ClientPassportInformation;
use App\Models\ClientTravelInformation;
use App\Models\ClientCharacter;
use App\Models\ClientVisaCountry;
use App\Models\ActivitiesLog;
use App\Models\TestScore; // bansalcrm2 has TestScore table
use Illuminate\Support\Facades\Log;

class ClientExportService
{
    /**
     * Export client data to JSON format
     * 
     * @param int $clientId
     * @return array
     */
    public function exportClient($clientId)
    {
        try {
            $client = Admin::where('id', $clientId)
                ->whereIn('type', ['client', 'lead'])
                ->first();

            if (!$client) {
                throw new \Exception('Client not found');
            }

            $exportData = [
                'version' => '1.0',
                'exported_at' => now()->toIso8601String(),
                'exported_from' => 'bansalcrm2',
                'client' => $this->getClientBasicData($client),
                'addresses' => $this->getClientAddresses($clientId),
                'contacts' => $this->getClientContacts($clientId),
                'emails' => $this->getClientEmails($clientId),
                'passport' => $this->getClientPassport($clientId),
                'travel' => $this->getClientTravel($clientId),
                'visa_countries' => $this->getClientVisaCountries($clientId),
                'character' => $this->getClientCharacter($clientId),
                'test_scores' => $this->getClientTestScores($clientId), // bansalcrm2 has TestScore table
                'activities' => $this->getClientActivities($clientId),
            ];

            return $exportData;
        } catch (\Exception $e) {
            Log::error('Client export error: ' . $e->getMessage(), [
                'client_id' => $clientId,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Get basic client data (fields that exist in both systems)
     */
    private function getClientBasicData($client)
    {
        return [
            // Basic Identity
            'first_name' => $client->first_name,
            'last_name' => $client->last_name,
            'email' => $client->email,
            'phone' => $client->phone,
            'country_code' => $client->country_code,
            
            // Personal Information
            'dob' => $client->dob,
            'age' => $client->age,
            'gender' => $client->gender,
            'marital_status' => $client->marital_status ?? $client->martial_status ?? null, // Note: bansalcrm2 might use martial_status
            
            // Address
            'address' => $client->address,
            'city' => $client->city,
            'state' => $client->state,
            'country' => $client->country,
            'zip' => $client->zip,
            
            // Passport
            'country_passport' => $client->country_passport ?? null,
            'passport_number' => $client->passport_number ?? null, // bansalcrm2 has passport_number in admins table
            
            // Professional Details (bansalcrm2 specific)
            'nomi_occupation' => $client->nomi_occupation ?? null,
            'skill_assessment' => $client->skill_assessment ?? null,
            'high_quali_aus' => $client->high_quali_aus ?? null,
            'high_quali_overseas' => $client->high_quali_overseas ?? null,
            'relevant_work_exp_aus' => $client->relevant_work_exp_aus ?? null,
            'relevant_work_exp_over' => $client->relevant_work_exp_over ?? null,
            
            // Additional Contact
            'att_email' => $client->att_email ?? null,
            'att_phone' => $client->att_phone ?? null,
            'att_country_code' => $client->att_country_code ?? null,
            
            // Other
            'naati_py' => $client->naati_py ?? null,
            'naati_test' => $client->naati_test ?? null,
            'naati_date' => $client->naati_date,
            'py_test' => $client->py_test ?? null,
            'py_date' => $client->py_date,
            'total_points' => $client->total_points ?? null,
            'start_process' => $client->start_process ?? null,
            'source' => $client->source,
            'type' => $client->type,
            'status' => $client->status,
            'agent_id' => $client->agent_id ?? null,
            
            // Verification metadata (dates only, not staff IDs)
            'dob_verified_date' => $client->dob_verified_date ?? null,
            'dob_verify_document' => $client->dob_verify_document ?? null,
            'phone_verified_date' => $client->phone_verified_date ?? null,
            'visa_expiry_verified_at' => $client->visa_expiry_verified_at ?? null,
        ];
    }

    /**
     * Get client addresses
     */
    private function getClientAddresses($clientId)
    {
        return ClientAddress::where('client_id', $clientId)
            ->get()
            ->map(function ($address) {
                return [
                    'address' => $address->address,
                    'address_line_1' => $address->address_line_1 ?? null,
                    'address_line_2' => $address->address_line_2 ?? null,
                    'suburb' => $address->suburb ?? null,
                    'city' => $address->city,
                    'state' => $address->state,
                    'country' => $address->country ?? null,
                    'zip' => $address->zip,
                    'regional_code' => $address->regional_code ?? null,
                    'start_date' => $address->start_date ?? null,
                    'end_date' => $address->end_date ?? null,
                    'is_current' => $address->is_current ?? 0,
                ];
            })
            ->toArray();
    }

    /**
     * Get client contacts (phone numbers)
     * Note: bansalcrm2 uses ClientPhone model
     */
    private function getClientContacts($clientId)
    {
        // Check if ClientPhone model exists, otherwise use ClientContact
        if (class_exists(\App\Models\ClientPhone::class)) {
            return \App\Models\ClientPhone::where('client_id', $clientId)
                ->get()
                ->map(function ($contact) {
                    return [
                        'contact_type' => $contact->contact_type ?? $contact->phone_type ?? null,
                        'country_code' => $contact->country_code ?? $contact->client_country_code ?? null,
                        'phone' => $contact->phone ?? $contact->client_phone ?? null,
                        'is_verified' => $contact->is_verified ?? false,
                        'verified_at' => $contact->verified_at ?? null,
                    ];
                })
                ->toArray();
        }
        
        // Fallback to ClientContact if ClientPhone doesn't exist
        return \App\Models\ClientContact::where('client_id', $clientId)
            ->get()
            ->map(function ($contact) {
                return [
                    'contact_type' => $contact->contact_type ?? null,
                    'country_code' => $contact->country_code ?? null,
                    'phone' => $contact->phone ?? null,
                    'is_verified' => $contact->is_verified ?? false,
                    'verified_at' => $contact->verified_at ?? null,
                ];
            })
            ->toArray();
    }

    /**
     * Get client emails
     */
    private function getClientEmails($clientId)
    {
        return ClientEmail::where('client_id', $clientId)
            ->get()
            ->map(function ($email) {
                return [
                    'email_type' => $email->email_type ?? null,
                    'email' => $email->email,
                    'is_verified' => $email->is_verified ?? false,
                    'verified_at' => $email->verified_at ?? null,
                ];
            })
            ->toArray();
    }

    /**
     * Get client passport information
     */
    private function getClientPassport($clientId)
    {
        $passport = ClientPassportInformation::where('client_id', $clientId)->first();
        
        if (!$passport) {
            return null;
        }

        return [
            'passport_number' => $passport->passport ?? $passport->passport_number ?? null,
            'passport_country' => $passport->passport_country ?? null,
            'passport_issue_date' => $passport->passport_issue_date ?? null,
            'passport_expiry_date' => $passport->passport_expiry_date ?? null,
        ];
    }

    /**
     * Get client travel information
     */
    private function getClientTravel($clientId)
    {
        return ClientTravelInformation::where('client_id', $clientId)
            ->get()
            ->map(function ($travel) {
                return [
                    'travel_country_visited' => $travel->travel_country_visited ?? null,
                    'travel_arrival_date' => $travel->travel_arrival_date ?? null,
                    'travel_departure_date' => $travel->travel_departure_date ?? null,
                    'travel_purpose' => $travel->travel_purpose ?? null,
                ];
            })
            ->toArray();
    }

    /**
     * Get client visa countries.
     * Export visa_type (Matter ID), plus portable visa_type_matter_title and visa_type_matter_nick_name
     * so import can resolve correct Matter in target system when IDs differ (e.g. Bansal Law CRM ↔ bansalcrm2).
     * visa_expiry_date / visa_grant_date are normalised to Y-m-d.
     */
    private function getClientVisaCountries($clientId)
    {
        return ClientVisaCountry::with('matter')
            ->where('client_id', $clientId)
            ->orderBy('id')
            ->get()
            ->map(function ($visa) {
                $matter = $visa->matter;
                $expiry = $visa->visa_expiry_date;
                if ($expiry instanceof \DateTimeInterface) {
                    $expiry = $expiry->format('Y-m-d');
                } elseif (is_string($expiry) && $expiry !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry)) {
                    try { $expiry = \Carbon\Carbon::parse($expiry)->format('Y-m-d'); } catch (\Exception $e) { /* keep as-is */ }
                }
                $grant = $visa->visa_grant_date;
                if ($grant instanceof \DateTimeInterface) {
                    $grant = $grant->format('Y-m-d');
                } elseif (is_string($grant) && $grant !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $grant)) {
                    try { $grant = \Carbon\Carbon::parse($grant)->format('Y-m-d'); } catch (\Exception $e) { /* keep as-is */ }
                }
                return [
                    'visa_type' => $visa->visa_type ?? null,
                    'visa_type_matter_title' => $matter ? $matter->title : null,
                    'visa_type_matter_nick_name' => $matter ? $matter->nick_name : null,
                    'visa_description' => $visa->visa_description ?? null,
                    'visa_expiry_date' => $expiry ?: null,
                    'visa_grant_date' => $grant ?: null,
                ];
            })
            ->toArray();
    }

    /**
     * Get client character information
     */
    private function getClientCharacter($clientId)
    {
        return ClientCharacter::where('client_id', $clientId)
            ->get()
            ->map(function ($character) {
                return [
                    'type_of_character' => $character->type_of_character ?? null,
                    'character_detail' => $character->character_detail ?? null,
                    'character_date' => $character->character_date ?? null,
                ];
            })
            ->toArray();
    }

    /**
     * Get client test scores (bansalcrm2 specific)
     */
    private function getClientTestScores($clientId)
    {
        if (!class_exists(\App\Models\TestScore::class)) {
            return [];
        }

        return \App\Models\TestScore::where('client_id', $clientId)
            ->where('type', 'client')
            ->get()
            ->map(function ($test) {
                return [
                    'type' => $test->type ?? null,
                    'toefl_Listening' => $test->toefl_Listening ?? null,
                    'toefl_Reading' => $test->toefl_Reading ?? null,
                    'toefl_Writing' => $test->toefl_Writing ?? null,
                    'toefl_Speaking' => $test->toefl_Speaking ?? null,
                    'toefl_Date' => $test->toefl_Date ?? null,
                    'ilets_Listening' => $test->ilets_Listening ?? null,
                    'ilets_Reading' => $test->ilets_Reading ?? null,
                    'ilets_Writing' => $test->ilets_Writing ?? null,
                    'ilets_Speaking' => $test->ilets_Speaking ?? null,
                    'ilets_Date' => $test->ilets_Date ?? null,
                    'pte_Listening' => $test->pte_Listening ?? null,
                    'pte_Reading' => $test->pte_Reading ?? null,
                    'pte_Writing' => $test->pte_Writing ?? null,
                    'pte_Speaking' => $test->pte_Speaking ?? null,
                    'pte_Date' => $test->pte_Date ?? null,
                    'score_1' => $test->score_1 ?? null,
                    'score_2' => $test->score_2 ?? null,
                    'score_3' => $test->score_3 ?? null,
                ];
            })
            ->toArray();
    }

    /**
     * Get client activities (if structure matches)
     */
    private function getClientActivities($clientId)
    {
        return ActivitiesLog::where('client_id', $clientId)
            ->orderBy('created_at', 'desc')
            ->limit(100) // Limit to recent 100 activities
            ->get()
            ->map(function ($activity) {
                return [
                    'subject' => $activity->subject ?? null,
                    'description' => $activity->description ?? null,
                    'activity_type' => $activity->activity_type ?? null,
                    'followup_date' => $activity->followup_date ?? null,
                    'task_group' => $activity->task_group ?? null,
                    'task_status' => $activity->task_status ?? 0,
                    'created_at' => $activity->created_at ?? null,
                ];
            })
            ->toArray();
    }
}
```

---

## Step 2: Create ClientImportService

**File:** `c:\xampp\htdocs\bansalcrm2\app\Services\ClientImportService.php`

```php
<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\ClientAddress;
use App\Models\ClientPhone; // bansalcrm2 uses ClientPhone
use App\Models\ClientEmail;
use App\Models\ClientPassportInformation;
use App\Models\ClientTravelInformation;
use App\Models\ClientCharacter;
use App\Models\ClientVisaCountry;
use App\Models\ActivitiesLog;
use App\Models\Matter;
use App\Models\TestScore; // bansalcrm2 has TestScore table
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class ClientImportService
{
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

            // Check for duplicate email if skip_duplicates is enabled
            if ($skipDuplicates && !empty($clientData['email'])) {
                $existingClient = Admin::where('email', $clientData['email'])
                    ->whereIn('type', ['client', 'lead'])
                    ->first();

                if ($existingClient) {
                    DB::rollBack();
                    return [
                        'success' => false,
                        'client_id' => null,
                        'message' => 'Client with email ' . $clientData['email'] . ' already exists. Import skipped.'
                    ];
                }
            }

            // Generate new client reference (you may need to create ClientReferenceService or use existing logic)
            // For now, using a simple approach - adjust based on your system
            $client_id = $this->generateClientId($clientData['first_name']);

            // Create the client
            $client = new Admin();
            $client->first_name = $clientData['first_name'];
            $client->last_name = $clientData['last_name'] ?? null;
            $client->email = $clientData['email'];
            $client->phone = $clientData['phone'] ?? null;
            $client->country_code = $clientData['country_code'] ?? null;
            
            // Personal Information
            $client->dob = $this->parseDate($clientData['dob'] ?? null);
            $client->age = $clientData['age'] ?? null;
            $client->gender = $clientData['gender'] ?? null;
            $client->martial_status = $clientData['marital_status'] ?? null; // Note: bansalcrm2 uses martial_status
            
            // Address
            $client->address = $clientData['address'] ?? null;
            $client->city = $clientData['city'] ?? null;
            $client->state = $this->mapState($clientData['state'] ?? null);
            $client->country = $this->mapCountry($clientData['country'] ?? null);
            $client->zip = $clientData['zip'] ?? null;
            
            // Passport
            $client->country_passport = $clientData['country_passport'] ?? null;
            $client->passport_number = $clientData['passport_number'] ?? null; // bansalcrm2 has this in admins table
            
            // Professional Details (bansalcrm2 specific)
            $client->nomi_occupation = $clientData['nomi_occupation'] ?? null;
            $client->skill_assessment = $clientData['skill_assessment'] ?? null;
            $client->high_quali_aus = $clientData['high_quali_aus'] ?? null;
            $client->high_quali_overseas = $clientData['high_quali_overseas'] ?? null;
            $client->relevant_work_exp_aus = $clientData['relevant_work_exp_aus'] ?? null;
            $client->relevant_work_exp_over = $clientData['relevant_work_exp_over'] ?? null;
            
            // Additional Contact
            $client->att_email = $clientData['att_email'] ?? null;
            $client->att_phone = $clientData['att_phone'] ?? null;
            $client->att_country_code = $clientData['att_country_code'] ?? null;
            
            // Other
            $client->naati_py = $clientData['naati_py'] ?? null;
            $client->naati_test = $clientData['naati_test'] ?? null;
            $client->naati_date = $this->parseDate($clientData['naati_date'] ?? null);
            $client->py_test = $clientData['py_test'] ?? null;
            $client->py_date = $this->parseDate($clientData['py_date'] ?? null);
            $client->total_points = $clientData['total_points'] ?? null;
            $client->start_process = $clientData['start_process'] ?? null;
            $client->source = $clientData['source'] ?? null;
            $client->type = $clientData['type'] ?? 'client';
            $client->status = $clientData['status'] ?? 1;
            $client->agent_id = $clientData['agent_id'] ?? null;
            
            // Verification metadata (dates only, not staff IDs)
            $client->dob_verified_date = $this->parseDateTime($clientData['dob_verified_date'] ?? null);
            $client->dob_verify_document = $clientData['dob_verify_document'] ?? null;
            $client->phone_verified_date = $this->parseDateTime($clientData['phone_verified_date'] ?? null);
            $client->visa_expiry_verified_at = $this->parseDateTime($clientData['visa_expiry_verified_at'] ?? null);
            
            // System fields
            $client->client_id = $client_id;
            $client->password = Hash::make('CLIENT_IMPORT_' . time()); // Temporary password
            $client->decrypt_password = null;
            $client->status = $clientData['status'] ?? 1;
            
            $client->save();
            $newClientId = $client->id;

            // Import addresses
            if (isset($importData['addresses']) && is_array($importData['addresses'])) {
                foreach ($importData['addresses'] as $addressData) {
                    ClientAddress::create([
                        'client_id' => $newClientId,
                        'admin_id' => Auth::id(),
                        'address' => $addressData['address'] ?? null,
                        'address_line_1' => $addressData['address_line_1'] ?? null,
                        'address_line_2' => $addressData['address_line_2'] ?? null,
                        'suburb' => $addressData['suburb'] ?? null,
                        'city' => $addressData['city'] ?? null,
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

            // Import contacts (phone numbers) - Use ClientPhone for bansalcrm2
            if (isset($importData['contacts']) && is_array($importData['contacts'])) {
                foreach ($importData['contacts'] as $contactData) {
                    if (class_exists(\App\Models\ClientPhone::class)) {
                        \App\Models\ClientPhone::create([
                            'client_id' => $newClientId,
                            'admin_id' => Auth::id(),
                            'contact_type' => $contactData['contact_type'] ?? null,
                            'client_country_code' => $contactData['country_code'] ?? null,
                            'client_phone' => $contactData['phone'] ?? null,
                            'is_verified' => $contactData['is_verified'] ?? false,
                            'verified_at' => $this->parseDateTime($contactData['verified_at'] ?? null),
                        ]);
                    }
                }
            }

            // Import emails
            if (isset($importData['emails']) && is_array($importData['emails'])) {
                foreach ($importData['emails'] as $emailData) {
                    ClientEmail::create([
                        'client_id' => $newClientId,
                        'admin_id' => Auth::id(),
                        'email_type' => $emailData['email_type'] ?? null,
                        'email' => $emailData['email'] ?? null,
                        'is_verified' => $emailData['is_verified'] ?? false,
                        'verified_at' => $this->parseDateTime($emailData['verified_at'] ?? null),
                    ]);
                }
            }

            // Import passport
            if (isset($importData['passport']) && is_array($importData['passport'])) {
                ClientPassportInformation::create([
                    'client_id' => $newClientId,
                    'admin_id' => Auth::id(),
                    'passport' => $importData['passport']['passport_number'] ?? $importData['passport']['passport'] ?? null,
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
            if (isset($importData['visa_countries']) && is_array($importData['visa_countries'])) {
                foreach ($importData['visa_countries'] as $visaData) {
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
                // Sync last visa to client (visa_type, visaExpiry) for sidebar/summary display
                if (Schema::hasColumn('admins', 'visa_type') && Schema::hasColumn('admins', 'visaExpiry')) {
                    $client->visa_type = $lastVisaType ?? '';
                    $client->visaExpiry = $lastVisaExpiry;
                    $client->save();
                }
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

            // Import test scores (bansalcrm2 specific)
            if (isset($importData['test_scores']) && is_array($importData['test_scores']) && class_exists(\App\Models\TestScore::class)) {
                foreach ($importData['test_scores'] as $testData) {
                    \App\Models\TestScore::create([
                        'client_id' => $newClientId,
                        'user_id' => null,
                        'type' => 'client',
                        'toefl_Listening' => $testData['toefl_Listening'] ?? null,
                        'toefl_Reading' => $testData['toefl_Reading'] ?? null,
                        'toefl_Writing' => $testData['toefl_Writing'] ?? null,
                        'toefl_Speaking' => $testData['toefl_Speaking'] ?? null,
                        'toefl_Date' => $this->parseDate($testData['toefl_Date'] ?? null),
                        'ilets_Listening' => $testData['ilets_Listening'] ?? null,
                        'ilets_Reading' => $testData['ilets_Reading'] ?? null,
                        'ilets_Writing' => $testData['ilets_Writing'] ?? null,
                        'ilets_Speaking' => $testData['ilets_Speaking'] ?? null,
                        'ilets_Date' => $this->parseDate($testData['ilets_Date'] ?? null),
                        'pte_Listening' => $testData['pte_Listening'] ?? null,
                        'pte_Reading' => $testData['pte_Reading'] ?? null,
                        'pte_Writing' => $testData['pte_Writing'] ?? null,
                        'pte_Speaking' => $testData['pte_Speaking'] ?? null,
                        'pte_Date' => $this->parseDate($testData['pte_Date'] ?? null),
                        'score_1' => $testData['score_1'] ?? null,
                        'score_2' => $testData['score_2'] ?? null,
                        'score_3' => $testData['score_3'] ?? null,
                    ]);
                }
            }

            // Import activities (if structure matches)
            if (isset($importData['activities']) && is_array($importData['activities'])) {
                foreach ($importData['activities'] as $activityData) {
                    ActivitiesLog::create([
                        'client_id' => $newClientId,
                        'created_by' => Auth::id(),
                        'subject' => $activityData['subject'] ?? 'Imported Activity',
                        'description' => $activityData['description'] ?? null,
                        'activity_type' => $activityData['activity_type'] ?? 'activity',
                        'followup_date' => $this->parseDateTime($activityData['followup_date'] ?? null),
                        'task_group' => $activityData['task_group'] ?? null,
                        'task_status' => $activityData['task_status'] ?? 0,
                    ]);
                }
            }

            DB::commit();

            return [
                'success' => true,
                'client_id' => $newClientId,
                'client_id_reference' => $client_id,
                'message' => 'Client imported successfully. Client ID: ' . $client_id
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Client import error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'client_id' => null,
                'message' => 'Failed to import client: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Generate client ID (adjust based on your system's logic)
     */
    private function generateClientId($firstName)
    {
        // You may need to implement your own client ID generation logic
        // This is a placeholder - check how bansalcrm2 generates client_id
        $initial = strtoupper(substr($firstName, 0, 1));
        $counter = DB::table('admins')
            ->where('client_id', 'LIKE', $initial . '%')
            ->count() + 1;
        return $initial . str_pad($counter, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Parse date string to Y-m-d format
     */
    private function parseDate($date)
    {
        if (empty($date)) {
            return null;
        }

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                return $date;
            }
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
     * Map state
     */
    private function mapState($state)
    {
        if (is_numeric($state)) {
            return $state;
        }
        return $state;
    }

    /**
     * Map country
     */
    private function mapCountry($country)
    {
        if (is_numeric($country)) {
            return $country;
        }

        if (is_string($country) && strlen($country) <= 3) {
            $countryModel = \App\Models\Country::where('sortname', $country)->first();
            if ($countryModel) {
                return $countryModel->id;
            }
        }

        return $country;
    }

    /**
     * Resolve visa_type (Matter ID) for import.
     * Prefer portable identifiers so target system maps correctly when matter IDs differ:
     * 1. visa_type_matter_nick_name -> lookup Matter by nick_name
     * 2. visa_type_matter_title -> lookup Matter by title
     * 3. Fall back to numeric visa_type (backwards compat with older exports)
     */
    private function resolveVisaType(array $visaData)
    {
        $nick = $visaData['visa_type_matter_nick_name'] ?? null;
        if (is_string($nick) && $nick !== '') {
            $matter = Matter::where('nick_name', $nick)->first();
            if ($matter) {
                return $matter->id;
            }
        }

        $title = $visaData['visa_type_matter_title'] ?? null;
        if (is_string($title) && $title !== '') {
            $matter = Matter::where('title', $title)->first();
            if ($matter) {
                return $matter->id;
            }
        }

        $id = $visaData['visa_type'] ?? null;
        if ($id !== null && $id !== '') {
            return is_numeric($id) ? (int) $id : $id;
        }

        return null;
    }
}
```

---

## Step 3: Add Export/Import Methods to ClientController

**File:** `c:\xampp\htdocs\bansalcrm2\app\Http\Controllers\Admin\Client\ClientController.php`

Add these methods at the end of the class:

```php
use App\Services\ClientExportService;
use App\Services\ClientImportService;

// ... existing code ...

/**
 * Export client data to JSON file
 * 
 * @param int $id Client ID
 * @return \Illuminate\Http\Response
 */
public function export($id)
{
    try {
        $client = Admin::where('id', $id)
            ->whereIn('type', ['client', 'lead'])
            ->first();

        if (!$client) {
            return redirect()->route('clients.index')
                ->with('error', 'Client not found.');
        }

        $exportService = app(ClientExportService::class);
        $exportData = $exportService->exportClient($id);

        $filename = 'client_export_' . ($client->client_id ?? $id) . '_' . date('Y-m-d_His') . '.json';

        return response()->json($exportData, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    } catch (\Exception $e) {
        \Log::error('Client export error: ' . $e->getMessage(), [
            'client_id' => $id,
            'trace' => $e->getTraceAsString()
        ]);

        return redirect()->route('clients.index')
            ->with('error', 'Failed to export client data: ' . $e->getMessage());
    }
}

/**
 * Import client data from JSON file
 * 
 * @param Request $request
 * @return \Illuminate\Http\RedirectResponse
 */
public function import(Request $request)
{
    try {
        $request->validate([
            'import_file' => 'required|file|mimes:json|max:10240',
        ]);

        $file = $request->file('import_file');
        $jsonContent = file_get_contents($file->getRealPath());
        $importData = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return redirect()->back()
                ->withErrors(['import_file' => 'Invalid JSON file: ' . json_last_error_msg()])
                ->withInput();
        }

        if (!isset($importData['client'])) {
            return redirect()->back()
                ->withErrors(['import_file' => 'Invalid import file format: missing client data'])
                ->withInput();
        }

        if (empty($importData['client']['email'])) {
            return redirect()->back()
                ->withErrors(['import_file' => 'Client email is required and cannot be empty'])
                ->withInput();
        }

        if (empty($importData['client']['first_name'])) {
            return redirect()->back()
                ->withErrors(['import_file' => 'Client first name is required'])
                ->withInput();
        }

        $skipDuplicates = $request->has('skip_duplicates');
        $importService = app(ClientImportService::class);
        $result = $importService->importClient($importData, $skipDuplicates);

        if ($result['success']) {
            return redirect()->route('clients.index')
                ->with('success', $result['message']);
        } else {
            return redirect()->back()
                ->withErrors(['import_file' => $result['message']])
                ->withInput();
        }

    } catch (\Illuminate\Validation\ValidationException $e) {
        return redirect()->back()
            ->withErrors($e->validator)
            ->withInput();
    } catch (\Exception $e) {
        \Log::error('Client import error: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);

        return redirect()->back()
            ->withErrors(['import_file' => 'Failed to import client: ' . $e->getMessage()])
            ->withInput();
    }
}
```

---

## Step 4: Add Routes

**File:** `c:\xampp\htdocs\bansalcrm2\routes\clients.php`

Add these routes (find where other client routes are defined):

```php
Route::get('/clients/export/{id}', [ClientController::class, 'export'])->name('clients.export');
Route::post('/clients/import', [ClientController::class, 'import'])->name('clients.import');
```

---

## Step 5: Add Export Button to Client List Page

**File:** `c:\xampp\htdocs\bansalcrm2\resources\views\Admin\clients\index.blade.php`

Find the Action dropdown menu (similar to Bansal Law CRM) and add Export option:

```php
<div class="dropdown-menu">
    <a class="dropdown-item has-icon clientemail" ...>Email</a>
    <a class="dropdown-item has-icon" href="...">Edit</a>
    <a class="dropdown-item has-icon" href="{{URL::to('/clients/export/'.$list->id)}}" title="Export Client Data">
        <i class="fas fa-download"></i> Export
    </a>
    <a class="dropdown-item has-icon" ...>Archived</a>
</div>
```

---

## Step 6: Add Import Button and Modal

**File:** `c:\xampp\htdocs\bansalcrm2\resources\views\Admin\clients\index.blade.php`

### A. Add Import Button in Header

Find the card-header section (around line 68-92) and add Import button:

```php
<div class="card-header">
    <h4>All Clients</h4>
    <div class="card-header-action">
        <a href="javascript:;" class="btn btn-theme btn-theme-sm" data-toggle="modal" data-target="#importClientModal" title="Import Client">
            <i class="fas fa-upload"></i> Import Client
        </a>
        <a href="javascript:;" class="btn btn-theme btn-theme-sm filter_btn">
            <i class="fas fa-filter"></i> Filter
        </a>
    </div>
</div>
```

### B. Add Import Modal

Add this modal before the closing `@endsection`:

```php
<!-- Import Client Modal -->
<div id="importClientModal" data-backdrop="static" data-keyboard="false" class="modal fade custom_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-upload"></i> Import Client from File
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form method="post" name="importClientForm" action="{{URL::to('/clients/import')}}" autocomplete="off" enctype="multipart/form-data">
                    @csrf
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Instructions:</strong> Upload a JSON file exported from Bansal Law CRM or bansalcrm2 to import client data.
                    </div>
                    
                    <div class="form-group">
                        <label for="import_file">Select JSON File <span class="span_req">*</span></label>
                        <div class="custom-file">
                            <input type="file" class="custom-file-input" id="import_file" name="import_file" accept=".json" required>
                            <label class="custom-file-label" for="import_file">Choose file...</label>
                        </div>
                        <small class="form-text text-muted">Only JSON files exported from CRM systems are supported.</small>
                        @if ($errors->has('import_file'))
                            <span class="custom-error" role="alert">
                                <strong>{{ @$errors->first('import_file') }}</strong>
                            </span>
                        @endif
                    </div>

                    <div class="form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="skip_duplicates" name="skip_duplicates" value="1" checked>
                            <label class="form-check-label" for="skip_duplicates">
                                Skip if client with same email already exists
                            </label>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Import Client
                        </button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
```

### C. Add JavaScript for File Input Label

Add this script in the `@push('scripts')` section:

```javascript
$('#import_file').on('change', function() {
    var fileName = $(this).val().split('\\').pop();
    $(this).next('.custom-file-label').html(fileName || 'Choose file...');
});
```

---

## Step 7: Important Notes & Adjustments

### Field Differences to Handle:

1. **ClientPhone vs ClientContact**: bansalcrm2 uses `ClientPhone` model. Adjust field names:
   - `client_country_code` instead of `country_code`
   - `client_phone` instead of `phone`

2. **TestScore Table**: bansalcrm2 has a `TestScore` table. Export/import test scores if needed.

3. **Client ID Generation**: Check how bansalcrm2 generates `client_id` and adjust `generateClientId()` method accordingly.

4. **Marital Status**: bansalcrm2 might use `martial_status` instead of `marital_status` - handle both.

5. **Passport Number**: bansalcrm2 stores `passport_number` in `admins` table, not just in `ClientPassportInformation`.

### Testing Checklist:

- [ ] Export client from bansalcrm2
- [ ] Import into Bansal Law CRM
- [ ] Export client from Bansal Law CRM
- [ ] Import into bansalcrm2
- [ ] Test duplicate email handling
- [ ] Test with missing fields
- [ ] Test date parsing
- [ ] Test country/state mapping

---

## Summary

You need to create:
1. ✅ `ClientExportService.php` in `app/Services/`
2. ✅ `ClientImportService.php` in `app/Services/`
3. ✅ Export/Import methods in `ClientController.php`
4. ✅ Routes in `routes/clients.php`
5. ✅ Export button in client list Action dropdown
6. ✅ Import button and modal in client list page

The implementation should mirror Bansal Law CRM but account for bansalcrm2-specific differences (ClientPhone model, TestScore table, etc.).
