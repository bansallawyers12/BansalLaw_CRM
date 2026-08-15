<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\ClientAddress;
use App\Models\ClientContact;
use App\Models\ClientEmail;
use App\Models\ActivitiesLog;
use App\Services\ClientReferenceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use App\Support\ClientTagStorage;

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
            }

            // Check for duplicate email/phone if skip_duplicates is enabled.
            if ($skipDuplicates) {
                $duplicateCheck = app(LeadDuplicateCheckService::class);
                $email = isset($clientData['email']) ? trim((string) $clientData['email']) : '';
                $phone = isset($clientData['phone']) ? trim((string) $clientData['phone']) : '';

                if ($email !== '' || $phone !== '') {
                    $duplicate = $duplicateCheck->findDuplicate($email, $phone);

                    if ($duplicate !== null) {
                        DB::rollBack();

                        return [
                            'success' => false,
                            'client_id' => null,
                            'message' => 'Lead with same ' . $duplicate['match'] . ' (' . $duplicate['value'] . ') already exists. Import skipped.',
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
                'naati_py', 'total_points',
                'service', 'assignee', 'lead_quality', 'comments_note', 'married_partner',
            ];
            foreach ($bansalOptional as $field) {
                if (Schema::hasColumn('admins', $field) && array_key_exists($field, $clientData)) {
                    $client->{$field} = $clientData[$field];
                }
            }
            if (Schema::hasColumn('admins', 'tagname') && array_key_exists('tagname', $clientData)) {
                [$n, $r] = ClientTagStorage::decode(trim((string) ($clientData['tagname'] ?? '')));
                $client->tagname = ClientTagStorage::encode($n, $r);
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
            $client->source = $clientData['source'] ?? null;
            $client->type = $clientData['type'] ?? 'lead';
            $leadStatus = isset($clientData['lead_status']) ? trim((string) $clientData['lead_status']) : null;
            if ($leadStatus !== null && $leadStatus !== '' && Schema::hasColumn('admins', 'lead_status')) {
                if (! in_array($leadStatus, LeadFollowUpNoteService::pipelineStatuses(), true)) {
                    $leadStatus = 'new';
                }
                $client->lead_status = $leadStatus;
                $client->status = LeadFollowUpNoteService::adminsStatusForLeadStatus($leadStatus);
            } else {
                $client->status = $clientData['status'] ?? 1;
            }
            if (Schema::hasColumn('admins', 'followup_date') && ! empty($clientData['followup_date'])) {
                $client->followup_date = $this->parseDateTime($clientData['followup_date']);
            }
            $client->agent_id = $clientData['agent_id'] ?? null;
            
            // Verification metadata (dates only, not staff IDs)
            $client->dob_verified_date = $this->parseDateTime($clientData['dob_verified_date'] ?? null);
            $client->dob_verify_document = $clientData['dob_verify_document'] ?? null;
            $client->phone_verified_date = $this->parseDateTime($clientData['phone_verified_date'] ?? null);
            
            // System fields
            $client->client_counter = $client_current_counter;
            $client->client_id = $client_id;
            $client->password = Hash::make('CLIENT_IMPORT_' . time()); // Temporary password
            $client->is_archived = 0;
            // Note: archived_by is not set during import - imported clients are not archived
            // archived_by will be null for imported clients
            
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

}
