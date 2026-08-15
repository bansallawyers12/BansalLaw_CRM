<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\ClientAddress;
use App\Models\ClientContact;
use App\Models\ClientEmail;
use App\Models\ActivitiesLog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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
        $passportNumber = null;
        if (Schema::hasColumn('admins', 'passport_number')) {
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

            // Passport fields on client record (no child passport collection)
            'country_passport' => $client->country_passport ?? null,
            'passport_number' => $passportNumber,

            // Visa summary fields on client record (no visa_countries collection)
            'visa_type' => Schema::hasColumn('admins', 'visa_type') ? ($client->visa_type ?? null) : null,
            'visa_opt' => Schema::hasColumn('admins', 'visa_opt') ? ($client->visa_opt ?? null) : null,
            'visaExpiry' => Schema::hasColumn('admins', 'visaExpiry')
                ? ($client->visaExpiry instanceof \DateTimeInterface
                    ? $client->visaExpiry->format('Y-m-d')
                    : ($client->visaExpiry ?? null))
                : null,

            // Email and Contact Type
            'email_type' => $client->email_type ?? null,
            'contact_type' => $client->contact_type ?? null,

            // Other
            'source' => $client->source,
            'type' => $client->type,
            'status' => $client->status,
            'agent_id' => $client->agent_id ?? null,
        ];

        // Schema-checked fields (bansalcrm2 may have; Bansal Law CRM may have dropped)
        $optionalFields = [
            'att_email', 'att_phone', 'att_country_code',
            'naati_py', 'total_points', 'office_id', 'verified', 'show_dashboard_per',
            'service', 'assignee', 'lead_quality', 'comments_note', 'married_partner',
            'tagname',
        ];
        foreach ($optionalFields as $field) {
            if (Schema::hasColumn('admins', $field)) {
                $data[$field] = $client->{$field} ?? null;
            } else {
                $data[$field] = null;
            }
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
