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
use App\Models\ActivitiesLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

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
                'exported_from' => 'bansal_law_crm',
                'client' => $this->getClientBasicData($client, $clientId),
                'addresses' => $this->getClientAddresses($clientId),
                'contacts' => $this->getClientContacts($clientId),
                'emails' => $this->getClientEmails($clientId),
                'passport' => $this->getClientPassport($clientId),
                'travel' => $this->getClientTravel($clientId),
                'visa_countries' => $this->getClientVisaCountries($clientId),
                'character' => $this->getClientCharacter($clientId),
                'test_scores' => $this->getClientTestScores($clientId),
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
     * Get basic client data (unified format for Bansal Law CRM and bansalcrm2)
     * Exports all fields both systems may use; target import ignores unknown columns.
     */
    private function getClientBasicData($client, $clientId)
    {
        $passport = ClientPassportInformation::where('client_id', $clientId)->first();
        $passportNumber = $passport ? ($passport->passport ?? null) : null;
        if ($passportNumber === null && Schema::hasColumn('admins', 'passport_number')) {
            $passportNumber = $client->passport_number ?? null;
        }

        $data = [
            // Basic Identity
            'client_id' => $client->client_id ?? null,
            'first_name' => $client->first_name,
            'last_name' => $client->last_name,
            'email' => $client->email,
            'phone' => $client->phone,
            'country_code' => $client->country_code,

            // Personal Information
            'dob' => $client->dob,
            'age' => $client->age,
            'gender' => $client->gender,
            'marital_status' => $client->marital_status ?? null,

            // Address
            'address' => $client->address,
            'city' => $client->city,
            'state' => $client->state,
            'country' => $client->country,
            'zip' => $client->zip,

            // Passport (unified: passport_number in client for bansalcrm2)
            'country_passport' => $client->country_passport ?? null,
            'passport_number' => $passportNumber,

            // Visa (from client for bansalcrm2 compatibility; visa_countries has full detail)
            'visa_type' => null,
            'visa_opt' => null,
            'visaExpiry' => null,

            // Email and Contact Type
            'email_type' => $client->email_type ?? null,
            'contact_type' => $client->contact_type ?? null,

            // Other
            'source' => $client->source,
            'type' => $client->type,
            'status' => $client->status,
            'agent_id' => $client->agent_id ?? null,
        ];

        // Visa summary from last visa (bansalcrm2 stores visa name in admins; Bansal Law CRM uses Matter ID)
        $lastVisa = ClientVisaCountry::with('matter')->where('client_id', $clientId)->orderBy('id', 'desc')->first();
        if ($lastVisa) {
            $matter = $lastVisa->matter;
            $data['visa_type'] = $matter ? ($matter->nick_name ?? $matter->title) : (string) $lastVisa->visa_type;
            $data['visa_opt'] = $lastVisa->visa_description ?? null;
            $expiry = $lastVisa->visa_expiry_date;
            if ($expiry instanceof \DateTimeInterface) {
                $data['visaExpiry'] = $expiry->format('Y-m-d');
            } elseif (is_string($expiry) && $expiry !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry)) {
                $data['visaExpiry'] = $expiry;
            } elseif ($expiry) {
                try {
                    $data['visaExpiry'] = Carbon::parse($expiry)->format('Y-m-d');
                } catch (\Exception $e) {
                    $data['visaExpiry'] = null;
                }
            }
        }

        // Schema-checked fields (bansalcrm2 may have; Bansal Law CRM may have dropped)
        $optionalFields = [
            'att_email', 'att_phone', 'att_country_code',
            'nomi_occupation', 'skill_assessment', 'high_quali_aus', 'high_quali_overseas',
            'relevant_work_exp_aus', 'relevant_work_exp_over',
            'naati_py', 'total_points', 'office_id', 'verified', 'show_dashboard_per',
            'service', 'assignee', 'lead_quality', 'comments_note', 'married_partner',
            'tagname', 'related_files',
        ];
        foreach ($optionalFields as $field) {
            if (Schema::hasColumn('admins', $field)) {
                $data[$field] = $client->{$field} ?? null;
            } else {
                $data[$field] = null;
            }
        }

        // Bansal Law CRM-specific (bansalcrm2 import ignores)
        if (Schema::hasColumn('admins', 'naati_test')) {
            $data['naati_test'] = $client->naati_test ?? null;
        }
        if (Schema::hasColumn('admins', 'naati_date')) {
            $data['naati_date'] = $client->naati_date ? ($client->naati_date instanceof \DateTimeInterface ? $client->naati_date->format('Y-m-d') : $client->naati_date) : null;
        }
        if (Schema::hasColumn('admins', 'py_test')) {
            $data['py_test'] = $client->py_test ?? null;
        }
        if (Schema::hasColumn('admins', 'py_date')) {
            $data['py_date'] = $client->py_date ? ($client->py_date instanceof \DateTimeInterface ? $client->py_date->format('Y-m-d') : $client->py_date) : null;
        }
        // Verification metadata (Bansal Law CRM)
        if (Schema::hasColumn('admins', 'dob_verified_date')) {
            $data['dob_verified_date'] = $client->dob_verified_date ? ($client->dob_verified_date instanceof \DateTimeInterface ? $client->dob_verified_date->toIso8601String() : $client->dob_verified_date) : null;
        }
        if (Schema::hasColumn('admins', 'dob_verify_document')) {
            $data['dob_verify_document'] = $client->dob_verify_document ?? null;
        }
        if (Schema::hasColumn('admins', 'phone_verified_date')) {
            $data['phone_verified_date'] = $client->phone_verified_date ? ($client->phone_verified_date instanceof \DateTimeInterface ? $client->phone_verified_date->toIso8601String() : $client->phone_verified_date) : null;
        }
        if (Schema::hasColumn('admins', 'visa_expiry_verified_at')) {
            $data['visa_expiry_verified_at'] = $client->visa_expiry_verified_at ? ($client->visa_expiry_verified_at instanceof \DateTimeInterface ? $client->visa_expiry_verified_at->toIso8601String() : $client->visa_expiry_verified_at) : null;
        }

        return $data;
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
                    'address_line_1' => $address->address_line_1,
                    'address_line_2' => $address->address_line_2,
                    'suburb' => $address->suburb,
                    'city' => $address->suburb,
                    'state' => $address->state,
                    'country' => $address->country,
                    'zip' => $address->zip,
                    'regional_code' => $address->regional_code,
                    'start_date' => $address->start_date,
                    'end_date' => $address->end_date,
                    'is_current' => $address->is_current,
                ];
            })
            ->toArray();
    }

    /**
     * Get client contacts (phone numbers)
     */
    private function getClientContacts($clientId)
    {
        return ClientContact::where('client_id', $clientId)
            ->get()
            ->map(function ($contact) {
                return [
                    'contact_type' => $contact->contact_type,
                    'country_code' => $contact->country_code,
                    'phone' => $contact->phone,
                    'is_verified' => $contact->is_verified,
                    'verified_at' => $contact->verified_at,
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
                    'email_type' => $email->email_type,
                    'email' => $email->email,
                    'is_verified' => $email->is_verified,
                    'verified_at' => $email->verified_at,
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
            'passport_number' => $passport->passport, // Field is 'passport' in DB but represents passport_number
            'passport_country' => $passport->passport_country,
            'passport_issue_date' => $passport->passport_issue_date,
            'passport_expiry_date' => $passport->passport_expiry_date,
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
                    'travel_country_visited' => $travel->travel_country_visited,
                    'travel_arrival_date' => $travel->travel_arrival_date,
                    'travel_departure_date' => $travel->travel_departure_date,
                    'travel_purpose' => $travel->travel_purpose,
                ];
            })
            ->toArray();
    }

    /**
     * Get client visa countries.
     * Exports visa_type (Matter ID), plus portable visa_type_matter_title and visa_type_matter_nick_name
     * so import can resolve correct Matter in target system (e.g. bansalcrm2) when IDs differ.
     * visa_expiry_date is normalised to Y-m-d for consistent import.
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
                    try {
                        $expiry = Carbon::parse($expiry)->format('Y-m-d');
                    } catch (\Exception $e) {
                        $expiry = $visa->visa_expiry_date;
                    }
                }
                $grant = $visa->visa_grant_date;
                if ($grant instanceof \DateTimeInterface) {
                    $grant = $grant->format('Y-m-d');
                } elseif (is_string($grant) && $grant !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $grant)) {
                    try {
                        $grant = Carbon::parse($grant)->format('Y-m-d');
                    } catch (\Exception $e) {
                        $grant = $visa->visa_grant_date;
                    }
                }
                return [
                    'visa_type' => $visa->visa_type,
                    'visa_type_matter_title' => $matter ? $matter->title : null,
                    'visa_type_matter_nick_name' => $matter ? $matter->nick_name : null,
                    'visa_description' => $visa->visa_description,
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
                    'type_of_character' => $character->type_of_character,
                    'character_detail' => $character->character_detail,
                    'character_date' => $character->character_date,
                ];
            })
            ->toArray();
    }

    /**
     * Get client test scores (unified format for Bansal Law CRM and bansalcrm2)
     * Both use test_type, listening, reading, writing, speaking, overall_score, test_date
     */
    private function getClientTestScores($clientId)
    {
        return ClientTestScore::where('client_id', $clientId)
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(function ($test) {
                $testDate = $test->test_date;
                if ($testDate instanceof \DateTimeInterface) {
                    $testDate = $testDate->format('Y-m-d');
                } elseif (is_string($testDate) && $testDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $testDate)) {
                    try {
                        $testDate = Carbon::parse($testDate)->format('Y-m-d');
                    } catch (\Exception $e) {
                        $testDate = $test->test_date;
                    }
                }
                return [
                    'test_type' => $test->test_type ?? null,
                    'listening' => $test->listening ?? null,
                    'reading' => $test->reading ?? null,
                    'writing' => $test->writing ?? null,
                    'speaking' => $test->speaking ?? null,
                    'overall_score' => $test->overall_score ?? null,
                    'test_date' => $testDate ?: null,
                ];
            })
            ->toArray();
    }

    /**
     * Get client activities (unified format - both systems use subject, description, task_status)
     */
    private function getClientActivities($clientId)
    {
        return ActivitiesLog::where('client_id', $clientId)
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get()
            ->map(function ($activity) {
                $arr = [
                    'subject' => $activity->subject,
                    'description' => $activity->description,
                    'activity_type' => $activity->activity_type,
                    'followup_date' => $activity->followup_date,
                    'task_group' => $activity->task_group,
                    'task_status' => $activity->task_status ?? 0,
                    'created_at' => $activity->created_at,
                ];
                if (Schema::hasColumn('activities_logs', 'use_for')) {
                    $arr['use_for'] = $activity->use_for ?? null;
                }
                if (Schema::hasColumn('activities_logs', 'pin')) {
                    $arr['pin'] = $activity->pin ?? 0;
                }
                if (Schema::hasColumn('activities_logs', 'created_by')) {
                    $arr['created_by'] = $activity->created_by ?? null;
                }
                return $arr;
            })
            ->toArray();
    }
}
