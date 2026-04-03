<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin;
use App\Models\ClientPortalDetailAudit;
use App\Models\ClientMatter;
use App\Services\ClientPortalActionNoteService;
use App\Models\Notification;
use App\Models\Staff;
use App\Events\NotificationCountUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ClientPortalPersonalDetailsController extends Controller
{
    /**
     * Get client personal details
     * 
     * This API fetches basic information from admins table and overrides with latest audit values.
     * It does not insert records into clientportal_details or clientportal_details_audit tables.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getClientPersonalDetail(Request $request)
    {
        try {
            // Get authenticated client ID from token
            $admin = $request->user();
            $clientId = (int) $admin->id;

            // Verify the authenticated client (Admin model; Staff use Staff model)
            if (!$admin instanceof Admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. This endpoint is only available for clients.'
                ], 403);
            }

            // Get the tab parameter from query string (default to 'all')
            $tab = $request->query('tab', 'all');
            $tab = strtolower(trim($tab));

            // Define valid tabs
            $validTabs = [
                'all',
                'basic_information',
                'phones',
                'emails',
                'passports',
                'visas',
                'addresses',
                'travels',
                'qualifications',
                'experiences',
                'occupations',
                'test_scores'
            ];

            // Validate tab parameter
            if (!in_array($tab, $validTabs)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid tab parameter. Valid values are: ' . implode(', ', $validTabs)
                ], 422);
            }

            // Initialize response data array
            $responseData = [];

            // Fetch data only for requested tab(s)
            if ($tab === 'all' || $tab === 'basic_information') {
                // Fetch basic details from admins table
                $basicInfo = [
                    'first_name' => $admin->first_name ?? null,
                    'last_name' => $admin->last_name ?? null,
                    'client_id' => $admin->client_id ?? null,
                    'date_of_birth' => $admin->dob ? $this->formatDate($admin->dob) : null,
                    'age' => $admin->age ?? null,
                    'gender' => $admin->gender ?? null,
                    'marital_status' => $admin->marital_status ?? null,
                ];

                // Get latest audit values for basic fields and override admins table values
                $basicFields = ['first_name', 'last_name', 'client_id', 'dob', 'age', 'gender', 'marital_status'];
                
                foreach ($basicFields as $field) {
                    // Get the latest audit entry for this field
                    $latestAudit = ClientPortalDetailAudit::where('client_id', $clientId)
                        ->where('meta_key', $field)
                        ->orderBy('updated_at', 'desc')
                        ->first();
                    
                    if ($latestAudit && $latestAudit->new_value !== null) {
                        // Override with audit value
                        if ($field === 'dob') {
                            $basicInfo['date_of_birth'] = $latestAudit->new_value ? $this->formatDate($latestAudit->new_value) : null;
                        } else {
                            $basicInfo[$field] = $latestAudit->new_value;
                        }
                    }
                }

                // Build full name
                $fullName = trim(($basicInfo['first_name'] ?? '') . ' ' . ($basicInfo['last_name'] ?? ''));
                $basicInfo['name'] = $fullName;
                $basicInfo['full_name'] = $fullName;
                
                // Add internal client ID
                $basicInfo['internal_client_id'] = $clientId;

                if ($tab === 'basic_information') {
                    // Return only basic information
                    return response()->json([
                        'success' => true,
                        'message' => 'Client personal details retrieved successfully',
                        'data' => [
                            'basic_information' => $basicInfo
                        ]
                    ]);
                }

                $responseData['basic_information'] = $basicInfo;
            }

            if ($tab === 'all' || $tab === 'phones') {
                $phones = $this->getPhonesData($clientId);
                if ($tab === 'phones') {
                    return response()->json([
                        'success' => true,
                        'message' => 'Client personal details retrieved successfully',
                        'data' => [
                            'phones' => $phones
                        ]
                    ]);
                }
                $responseData['phones'] = $phones;
            }

            if ($tab === 'all' || $tab === 'emails') {
                $emails = $this->getEmailsData($clientId);
                if ($tab === 'emails') {
                    return response()->json([
                        'success' => true,
                        'message' => 'Client personal details retrieved successfully',
                        'data' => [
                            'emails' => $emails
                        ]
                    ]);
                }
                $responseData['emails'] = $emails;
            }

            if ($tab === 'all' || $tab === 'passports') {
                $passports = $this->getPassportsData($clientId);
                if ($tab === 'passports') {
                    return response()->json([
                        'success' => true,
                        'message' => 'Client personal details retrieved successfully',
                        'data' => [
                            'passports' => $passports
                        ]
                    ]);
                }
                $responseData['passports'] = $passports;
            }

            if ($tab === 'all' || $tab === 'visas') {
                $visas = $this->getVisasData($clientId);
                if ($tab === 'visas') {
                    return response()->json([
                        'success' => true,
                        'message' => 'Client personal details retrieved successfully',
                        'data' => [
                            'visas' => $visas
                        ]
                    ]);
                }
                $responseData['visas'] = $visas;
            }

            if ($tab === 'all' || $tab === 'addresses') {
                $addresses = $this->getAddressesData($clientId);
                if ($tab === 'addresses') {
                    return response()->json([
                        'success' => true,
                        'message' => 'Client personal details retrieved successfully',
                        'data' => [
                            'addresses' => $addresses
                        ]
                    ]);
                }
                $responseData['addresses'] = $addresses;
            }

            if ($tab === 'all' || $tab === 'travels') {
                $travels = $this->getTravelsData($clientId);
                if ($tab === 'travels') {
                    return response()->json([
                        'success' => true,
                        'message' => 'Client personal details retrieved successfully',
                        'data' => [
                            'travels' => $travels
                        ]
                    ]);
                }
                $responseData['travels'] = $travels;
            }

            if ($tab === 'all' || $tab === 'qualifications') {
                $qualifications = $this->getQualificationsData($clientId);
                if ($tab === 'qualifications') {
                    return response()->json([
                        'success' => true,
                        'message' => 'Client personal details retrieved successfully',
                        'data' => [
                            'qualifications' => $qualifications
                        ]
                    ]);
                }
                $responseData['qualifications'] = $qualifications;
            }

            if ($tab === 'all' || $tab === 'experiences') {
                $experiences = $this->getExperiencesData($clientId);
                if ($tab === 'experiences') {
                    return response()->json([
                        'success' => true,
                        'message' => 'Client personal details retrieved successfully',
                        'data' => [
                            'experiences' => $experiences
                        ]
                    ]);
                }
                $responseData['experiences'] = $experiences;
            }

            if ($tab === 'all' || $tab === 'occupations') {
                $occupations = $this->getOccupationsData($clientId);
                if ($tab === 'occupations') {
                    return response()->json([
                        'success' => true,
                        'message' => 'Client personal details retrieved successfully',
                        'data' => [
                            'occupations' => $occupations
                        ]
                    ]);
                }
                $responseData['occupations'] = $occupations;
            }

            if ($tab === 'all' || $tab === 'test_scores') {
                $testScores = $this->getTestScoresData($clientId);
                if ($tab === 'test_scores') {
            return response()->json([
                'success' => true,
                'message' => 'Client personal details retrieved successfully',
                'data' => [
                            'test_scores' => $testScores
                        ]
                    ]);
                }
                $responseData['test_scores'] = $testScores;
            }

            // Return all data if tab is 'all'
            return response()->json([
                'success' => true,
                'message' => 'Client personal details retrieved successfully',
                'data' => $responseData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update client basic details - saves only to clientportal_details_audit table
     * 
     * This API allows clients to update their basic information.
     * Only updated fields will be saved to clientportal_details_audit table.
     * The clientportal_details table will not be used for these updates.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateClientBasicDetail(Request $request)
    {
        try {
            // Get authenticated client ID from token
            $admin = $request->user();
            $clientId = (int) $admin->id;

            // Verify the authenticated client (Admin model; Staff use Staff model)
            if (!$admin instanceof Admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. This endpoint is only available for clients.'
                ], 403);
            }

            // Remove client_id from request if present (readonly field)
            $requestData = $request->all();
            if (isset($requestData['client_id'])) {
                unset($requestData['client_id']);
            }

            // Validate the request
            $validator = \Illuminate\Support\Facades\Validator::make($requestData, [
                'first_name' => 'sometimes|string|max:255',
                'last_name' => 'sometimes|string|max:255',
                'date_of_birth' => 'sometimes|date_format:d/m/Y|before:today',
                'gender' => 'sometimes|string|in:Male,Female,Other',
                'marital_status' => 'sometimes|string|in:Single,Married,De Facto,Divorced,Widowed,Separated',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $userId = Auth::id() ?? $clientId;
            $updatedFields = [];

            // Field mapping from request key to meta_key
            $fieldMapping = [
                'first_name' => 'first_name',
                'last_name' => 'last_name',
                'date_of_birth' => 'dob',
                'gender' => 'gender',
                'marital_status' => 'marital_status',
            ];

            DB::beginTransaction();

            try {
                foreach ($fieldMapping as $requestKey => $metaKey) {
                    if (isset($requestData[$requestKey])) {
                        $newValue = $requestData[$requestKey];
                        
                        // Handle date_of_birth conversion from dd/mm/yyyy to database format
                        if ($requestKey === 'date_of_birth' && $newValue) {
                            try {
                                $date = Carbon::createFromFormat('d/m/Y', $newValue);
                                $newValue = $date->format('Y-m-d');
                            } catch (\Exception $e) {
                                DB::rollBack();
                                return response()->json([
                                    'success' => false,
                                    'message' => 'Invalid date format for date_of_birth. Expected format: dd/mm/yyyy'
                                ], 422);
                            }
                        }

                        // Get current value (from admins table or latest audit)
                        $currentValue = $this->getCurrentFieldValue($clientId, $metaKey, $admin);
                        
                        // Convert current value to string for comparison
                        $currentValueStr = (string) $currentValue;
                        $newValueStr = (string) $newValue;
                        
                        // Only save to audit if value has changed
                        if ($currentValueStr !== $newValueStr) {
                            // Save to audit table
                            ClientPortalDetailAudit::create([
                                'client_id' => $clientId,
                                'meta_key' => $metaKey,
                                'old_value' => $currentValueStr,
                                'new_value' => $newValueStr,
                                'meta_order' => 0,
                                'meta_type' => null,
                                'action' => 'update',
                                'updated_by' => $userId,
                                'updated_at' => now(),
                            ]);
                            
                            $updatedFields[$metaKey] = $newValueStr;
                        }

                        // If DOB is updated, recalculate age and save to audit
                        if ($metaKey === 'dob' && $newValue) {
                            $calculatedAge = $this->calculateAge($newValue, null);
                            
                            // Get current age value
                            $currentAge = $this->getCurrentFieldValue($clientId, 'age', $admin, $calculatedAge);
                            
                            // Save age update to audit if changed
                            if ((string) $currentAge !== (string) $calculatedAge) {
                                ClientPortalDetailAudit::create([
                                    'client_id' => $clientId,
                                    'meta_key' => 'age',
                                    'old_value' => (string) $currentAge,
                                    'new_value' => (string) $calculatedAge,
                                    'meta_order' => 0,
                                    'meta_type' => null,
                                    'action' => 'update',
                                    'updated_by' => $userId,
                                    'updated_at' => now(),
                                ]);
                                
                            $updatedFields['age'] = $calculatedAge;
                            }
                        }
                    }
                }

                if (count($updatedFields) > 0) {
                    $this->notifyStaffAndCreateActionForDetailUpdate($clientId, 'Basic detail updated');
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Client basic details updated successfully',
                    'data' => [
                        'updated_fields' => $updatedFields
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current field value from admins table or latest audit entry
     * 
     * @param int $clientId
     * @param string $metaKey
     * @param Admin $admin
     * @param mixed $defaultValue
     * @return mixed
     */
    private function getCurrentFieldValue($clientId, $metaKey, Admin $admin, $defaultValue = null)
    {
        // First check latest audit entry
        $latestAudit = ClientPortalDetailAudit::where('client_id', $clientId)
            ->where('meta_key', $metaKey)
            ->orderBy('updated_at', 'desc')
            ->first();
        
        if ($latestAudit && $latestAudit->new_value !== null) {
            return $latestAudit->new_value;
        }
        
        // If no audit entry, get from admins table
        $fieldMapping = [
            'first_name' => 'first_name',
            'last_name' => 'last_name',
            'client_id' => 'client_id',
            'dob' => 'dob',
            'age' => 'age',
            'gender' => 'gender',
            'marital_status' => 'marital_status',
        ];
        
        $adminField = $fieldMapping[$metaKey] ?? null;
        
        if ($adminField && isset($admin->$adminField)) {
            return $admin->$adminField;
        }
        
        return $defaultValue;
    }

    /**
     * Update client phone details - saves to clientportal_details_audit table
     * 
     * This API allows clients to update their phone numbers.
     * Rules: 1) Personal phone number cannot be updated (readonly), 2) Only one Personal phone number is allowed.
     * Phones are saved to clientportal_details_audit table with meta_key and meta_order.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateClientPhoneDetail(Request $request)
    {
        try {
            // Get authenticated client ID from token
            $admin = $request->user();
            $clientId = (int) $admin->id;

            // Verify the authenticated client (Admin model; Staff use Staff model)
            if (!$admin instanceof Admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. This endpoint is only available for clients.'
                ], 403);
            }

            // Validate the request
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'phones' => 'required|array|min:1',
                'phones.*.id' => 'present|nullable|integer',
                'phones.*.phone' => 'required|string|max:255',
                'phones.*.type' => 'required|string|in:Personal,Office,Mobile,Work,Home,Other',
                'phones.*.country_code' => 'nullable|string|max:10',
                'phones.*.extension' => 'nullable|string|max:10',
            ], [
                'phones.required' => 'At least one phone number is required.',
                'phones.*.id.present' => 'Phone ID field is required for each phone. Use null for new phones or provide the existing phone ID for updates.',
                'phones.*.id.integer' => 'Phone ID must be an integer or null.',
                'phones.*.phone.required' => 'Phone number is required for each entry.',
                'phones.*.type.required' => 'Phone type is required for each entry.',
                'phones.*.type.in' => 'Phone type must be one of: Personal, Office, Mobile, Work, Home, Other.',
            ]);

            // Custom validation: Ensure id field is always present and check Personal phone restrictions
            $validator->after(function ($validator) use ($request, $clientId) {
                $phones = $request->input('phones', []);
                
                // Ensure id field is always present (required field)
                foreach ($phones as $index => $phone) {
                    if (!array_key_exists('id', $phone)) {
                        $validator->errors()->add(
                            "phones.{$index}.id",
                            "Phone ID field is required. Use null for new phones or provide the existing phone ID for updates."
                        );
                    }
                }
                
                $personalPhoneCount = 0;
                $hasExistingPersonal = false;
                $existingPersonalPhoneId = null;

                // Check if there's an existing Personal phone in audit or source
                $existingPhones = $this->getPhonesData($clientId);
                foreach ($existingPhones as $existingPhone) {
                    if (strtolower($existingPhone['type'] ?? '') === 'personal') {
                        $hasExistingPersonal = true;
                        $existingPersonalPhoneId = $existingPhone['id'] ?? null;
                        break;
                    }
                }

                // Count Personal phones in the request and validate
                foreach ($phones as $index => $phone) {
                    $phoneType = strtolower($phone['type'] ?? '');
                    if ($phoneType === 'personal') {
                        $personalPhoneCount++;
                        $phoneNumber = $phone['phone'] ?? '';
                        $countryCode = $phone['country_code'] ?? '';
                        
                        // Check if trying to update existing Personal phone
                        if (isset($phone['id']) && $phone['id'] == $existingPersonalPhoneId) {
                            // Get the existing phone to check if values changed
                            $existingPhone = collect($existingPhones)->firstWhere('id', $phone['id']);
                            if ($existingPhone) {
                                // Check if trying to change the phone number, country code, or extension
                                if (($phone['phone'] ?? '') !== ($existingPhone['phone'] ?? '') || 
                                    ($phone['country_code'] ?? '') !== ($existingPhone['country_code'] ?? '') ||
                                    ($phone['extension'] ?? '') !== ($existingPhone['extension'] ?? '')) {
                                    $validator->errors()->add(
                                        "phones.{$index}.phone",
                                        "Personal phone number cannot be updated. It is readonly."
                                    );
                                }
                            }
                        } else {
                            // For new Personal phones or updating to Personal type, check uniqueness
                            if (!empty($phoneNumber)) {
                                // Check uniqueness in admins table (excluding current client)
                                $phoneExistsInAdmins = DB::table('admins')
                                    ->where('phone', $phoneNumber)
                                    ->where(function($query) use ($countryCode) {
                                        if (!empty($countryCode)) {
                                            $query->where('country_code', $countryCode);
                                        } else {
                                            $query->whereNull('country_code')->orWhere('country_code', '');
                                        }
                                    })
                                    ->where('id', '!=', $clientId)
                                    ->exists();
                                
                                // Check uniqueness in client_contacts table (excluding current client's contacts)
                                $phoneExistsInContacts = DB::table('client_contacts')
                                    ->where('phone', $phoneNumber)
                                    ->where(function($query) use ($countryCode) {
                                        if (!empty($countryCode)) {
                                            $query->where('country_code', $countryCode);
                                        } else {
                                            $query->whereNull('country_code')->orWhere('country_code', '');
                                        }
                                    })
                                    ->where('client_id', '!=', $clientId)
                                    ->exists();
                                
                                if ($phoneExistsInAdmins || $phoneExistsInContacts) {
                                    $validator->errors()->add(
                                        "phones.{$index}.phone",
                                        "This Personal phone number already exists in the system. Personal phone numbers must be unique."
                                    );
                                }
                            }
                        }
                        
                        // Check if trying to add a new Personal phone when one already exists
                        if (!isset($phone['id']) && $hasExistingPersonal) {
                            $validator->errors()->add(
                                "phones.{$index}.type",
                                "Cannot add another Personal phone number. Personal phone already exists."
                            );
                        }
                    }
                }

                // Check if trying to add more than one Personal phone in the same request
                if ($personalPhoneCount > 1) {
                    $validator->errors()->add(
                        "phones",
                        "Cannot add another Personal phone number. Only one Personal phone number is allowed."
                    );
                }
            });

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $userId = Auth::id() ?? $clientId;
            $phones = $request->input('phones');
            $updatedPhones = [];
            $responsePhones = []; // Only phones from the original request

            DB::beginTransaction();

            try {
                // Get existing phones to check for Personal phone protection
                $existingPhones = $this->getPhonesData($clientId);
                $existingPersonalPhoneId = null;
                $existingPersonalPhoneData = null;
                $existingPersonalPhoneIndex = null;
                foreach ($existingPhones as $idx => $existingPhone) {
                    if (strtolower($existingPhone['type'] ?? '') === 'personal') {
                        $existingPersonalPhoneId = $existingPhone['id'] ?? null;
                        $existingPersonalPhoneData = $existingPhone;
                        $existingPersonalPhoneIndex = $idx;
                        break;
                    }
                }

                // Check if existing Personal phone is included in the request
                $personalPhoneInRequest = false;
                if ($existingPersonalPhoneId) {
                    foreach ($phones as $phoneData) {
                        if (isset($phoneData['id']) && $phoneData['id'] == $existingPersonalPhoneId) {
                            $personalPhoneInRequest = true;
                            break;
                        }
                    }
                }

                // Get existing phone IDs from request to identify which ones to update
                $phoneIdsToUpdate = [];
                $phoneIdToMetaOrderMap = []; // Map phone ID to its meta_order
                
                foreach ($phones as $phoneData) {
                    // ID field is required - if it has a value (not null), it's an update; if null, it's a new record
                    if (isset($phoneData['id']) && $phoneData['id'] !== null && $phoneData['id'] !== '') {
                        $phoneIdsToUpdate[] = (int) $phoneData['id'];
                    }
                }

                // Get the highest existing meta_order BEFORE deleting (to ensure unique values for new phones)
                $maxMetaOrder = ClientPortalDetailAudit::where('client_id', $clientId)
                    ->whereIn('meta_key', ['phone', 'phone_type', 'phone_country_code', 'phone_extension'])
                    ->max('meta_order') ?? -1;

                // Get meta_order values for existing phones BEFORE deleting (if IDs provided)
                if (!empty($phoneIdsToUpdate)) {
                    $existingAuditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
                        ->where('meta_key', 'phone')
                        ->whereIn('meta_type', array_map('strval', $phoneIdsToUpdate))
                        ->get();

                    foreach ($existingAuditEntries as $entry) {
                        $pid = (int) $entry->meta_type;
                        if (!isset($phoneIdToMetaOrderMap[$pid])) {
                            $phoneIdToMetaOrderMap[$pid] = $entry->meta_order;
                        }
                    }

                    // Delete existing phone audit entries only for phones that are in the request
                    // This preserves the Personal phone's audit entries if it wasn't in the request
                    if (!empty($phoneIdToMetaOrderMap)) {
                        $ordersToDelete = array_values($phoneIdToMetaOrderMap);
                        ClientPortalDetailAudit::where('client_id', $clientId)
                            ->whereIn('meta_key', ['phone', 'phone_type', 'phone_country_code', 'phone_extension'])
                            ->whereIn('meta_order', $ordersToDelete)
                            ->delete();
                    }
                } else {
                    // If no phone IDs in request (all are new), delete all phone audit entries
                    ClientPortalDetailAudit::where('client_id', $clientId)
                        ->whereIn('meta_key', ['phone', 'phone_type', 'phone_country_code', 'phone_extension'])
                        ->delete();
                }
                // Note: If all phones have id: null (new phones), no deletion happens - safe behavior

                // Track which meta_order values are being reused (to avoid conflicts with new phones)
                $usedMetaOrders = array_values($phoneIdToMetaOrderMap);

                // Process only phones from the original request (don't add Personal phone to processing)
                $phonesToProcess = $phones;

                // Process each phone from the original request only
                foreach ($phonesToProcess as $index => $phoneData) {

                    // ID field is required: if null, it's a new record; if has value, it's an update
                    $phoneId = null;
                    $isNewRecord = false;
                    if (isset($phoneData['id']) && $phoneData['id'] !== null && $phoneData['id'] !== '') {
                        $phoneId = (int) $phoneData['id'];
                    } else {
                        // Generate timestamp-based 10-digit ID for new record
                        $phoneId = $this->generateTimestampBasedId();
                        $isNewRecord = true;
                    }
                    
                    $phone = $phoneData['phone'] ?? null;
                    $phoneType = $phoneData['type'] ?? 'Mobile';
                    $countryCode = $phoneData['country_code'] ?? null;
                    $extension = $phoneData['extension'] ?? null;

                    if (empty($phone)) {
                        continue; // Skip if phone is empty
                    }

                    // Check if this is the existing Personal phone - if so, use original data
                    $isExistingPersonal = false;
                    if (!$isNewRecord && $existingPersonalPhoneId && $phoneId == $existingPersonalPhoneId && 
                        strtolower($phoneType) === 'personal') {
                        $isExistingPersonal = true;
                        // Use original Personal phone data (readonly)
                        if ($existingPersonalPhoneData) {
                            $phone = $existingPersonalPhoneData['phone'] ?? $phone;
                            $phoneType = $existingPersonalPhoneData['type'] ?? $phoneType;
                            $countryCode = $existingPersonalPhoneData['country_code'] ?? $countryCode;
                            $extension = $existingPersonalPhoneData['extension'] ?? $extension;
                        }
                    }

                    // Determine action: 'create' for new records, 'update' for existing ones
                    $action = $isNewRecord ? 'create' : 'update';

                    // Determine meta_order: use existing if updating by ID, otherwise use next available
                    if (!$isNewRecord && isset($phoneIdToMetaOrderMap[$phoneId])) {
                        // Use existing meta_order for this phone
                        $metaOrder = $phoneIdToMetaOrderMap[$phoneId];
                    } else {
                        // New phone - use next available meta_order that doesn't conflict with reused values
                        do {
                            $maxMetaOrder++;
                            $metaOrder = $maxMetaOrder;
                        } while (in_array($metaOrder, $usedMetaOrders));
                        // Track this meta_order as used to avoid conflicts with subsequent new phones
                        $usedMetaOrders[] = $metaOrder;
                    }

                    // Save phone number - store record ID in meta_type (original ID for updates, generated ID for new records)
                    ClientPortalDetailAudit::create([
                        'client_id' => $clientId,
                        'meta_key' => 'phone',
                        'old_value' => null,
                        'new_value' => $phone,
                        'meta_order' => $metaOrder,
                        'meta_type' => (string) $phoneId, // Store record ID (original or generated)
                        'action' => $action,
                        'updated_by' => $userId,
                        'updated_at' => now(),
                    ]);

                    // Save phone type
                    ClientPortalDetailAudit::create([
                        'client_id' => $clientId,
                        'meta_key' => 'phone_type',
                        'old_value' => null,
                        'new_value' => $phoneType,
                        'meta_order' => $metaOrder,
                        'meta_type' => (string) $phoneId, // Store record ID for consistency
                        'action' => $action,
                        'updated_by' => $userId,
                        'updated_at' => now(),
                    ]);

                    // Save country code if provided
                    if ($countryCode) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'phone_country_code',
                            'old_value' => null,
                            'new_value' => $countryCode,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $phoneId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save extension if provided
                    if ($extension) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'phone_extension',
                            'old_value' => null,
                            'new_value' => $extension,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $phoneId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Add to response (all phones in $phonesToProcess are from original request)
                    $responsePhones[] = [
                        'id' => $phoneId,
                        'phone' => $phone,
                        'type' => $phoneType,
                        'country_code' => $countryCode,
                        'extension' => $extension,
                    ];
                }

                DB::commit();

                $this->notifyStaffAndCreateActionForDetailUpdate($clientId, 'Phone detail updated');

                return response()->json([
                    'success' => true,
                    'message' => 'Phone numbers updated successfully',
                    'data' => [
                        'phones' => $responsePhones
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate age from date of birth
     * 
     * @param string|null $dob
     * @param mixed $existingAge
     * @return mixed
     */
    private function calculateAge($dob, $existingAge = null)
    {
        if ($dob) {
            try {
                $dobDate = Carbon::parse($dob);
                return $dobDate->diff(Carbon::now())->format('%y years %m months');
            } catch (\Exception $e) {
                return $existingAge;
            }
        }
        return $existingAge;
    }

    /**
     * Format date to dd/mm/yyyy
     * 
     * @param string|null $date
     * @return string|null
     */
    private function formatDate($date)
    {
        if (!$date) {
            return null;
        }

        try {
            return Carbon::parse($date)->format('d/m/Y');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get phones data - from audit table if updated, otherwise from client_contacts table
     * 
     * @param int $clientId
     * @return array
     */
    /**
     * Get phones data - merge audit table (if updated/created/deleted) with source table
     * 
     * Implements 5-case logic:
     * Case 1: No audit records → Shows all from source table (with action: null)
     * Case 2: Action = 'update' → Overwrites source records based on meta_type column (with action: "update")
     * Case 3: Action = 'create' → Shows from audit table (newly created via API with generated IDs, with action: "create")
     * Case 4: Action = 'delete' → Excludes from audit table AND excludes related record with same ID from source table
     * Case 5: Default → Shows all from source table when no update action found (with action: null)
     * 
     * @param int $clientId
     * @return array
     */
    private function getPhonesData($clientId)
    {
        // Get all phones from source table
        $sourcePhones = $this->getPhonesFromSource($clientId);
        
        // Get all phones from audit table with action information
        $auditData = $this->getPhonesFromAudit($clientId);
        $auditPhones = $auditData['phones'];
        $actionMap = $auditData['actions'];
        
        // Case 1: If no audit records, return all source phones
        if (empty($auditPhones) && empty($actionMap)) {
            return $sourcePhones;
        }
        
        // Create a map of audit phones by ID and meta_type for quick lookup
        $auditPhonesByIdMap = [];
        $auditPhonesByMetaTypeMap = [];
        foreach ($auditPhones as $auditPhone) {
            $phoneId = $auditPhone['id'] ?? null;
            $action = $auditPhone['action'] ?? 'update';
            
            if ($phoneId !== null) {
                $auditPhonesByIdMap[$phoneId] = $auditPhone;
            }
            
            // Also map by meta_type (which is stored in the phone ID)
            if ($phoneId !== null) {
                $auditPhonesByMetaTypeMap[$phoneId] = [
                    'phone' => $auditPhone,
                    'action' => $action
                ];
            }
        }
        
        // Build final result array
        $mergedPhones = [];
        $processedIds = [];
        $deletedIds = []; // Track IDs that should be excluded (action='delete')
        
        // First, identify all deleted phones (Case 4) from actionMap
        foreach ($actionMap as $metaType => $action) {
            if ($action === 'delete') {
                $deletedIds[] = is_numeric($metaType) ? (int) $metaType : $metaType;
            }
        }
        
        // Also identify deleted phones from audit phones array itself (Case 4)
        foreach ($auditPhones as $auditPhone) {
            $phoneId = $auditPhone['id'] ?? null;
            $action = $auditPhone['action'] ?? 'update';
            
            if ($action === 'delete' && $phoneId !== null) {
                $deletedId = is_numeric($phoneId) ? (int) $phoneId : $phoneId;
                if (!in_array($deletedId, $deletedIds)) {
                    $deletedIds[] = $deletedId;
                }
            }
        }
        
        // Process source phones
        foreach ($sourcePhones as $sourcePhone) {
            $phoneId = $sourcePhone['id'];
            
            // Case 4: Skip if this phone is deleted (exclude from both audit and source table)
            if (in_array($phoneId, $deletedIds)) {
                continue;
            }
            
            // Case 2: Check if there's an audit entry with action='update' for this ID
            if (isset($auditPhonesByMetaTypeMap[$phoneId])) {
                $auditInfo = $auditPhonesByMetaTypeMap[$phoneId];
                // Case 2: Overwrite with audit data if action is 'update' (and not 'delete')
                if ($auditInfo['action'] === 'update') {
                    $mergedPhones[] = $auditInfo['phone'];
                    $processedIds[] = $phoneId;
                    continue;
                }
                // If action is 'delete', it's already handled above (Case 4)
            }
            
            // Case 5: Default - use source data (no update action found)
            $mergedPhones[] = $sourcePhone;
            $processedIds[] = $phoneId;
        }
        
        // Case 3: Add audit phones with action='create' (new phones created via API)
        // But exclude if they have action='delete' (Case 4)
        foreach ($auditPhones as $auditPhone) {
            $phoneId = $auditPhone['id'] ?? null;
            $action = $auditPhone['action'] ?? 'update';
            
            // Case 4: Skip if this phone is deleted
            if ($action === 'delete' || ($phoneId !== null && in_array($phoneId, $deletedIds))) {
                continue;
            }
            
            // Case 3: Add if action is 'create' and not already processed
            if ($action === 'create' && !in_array($phoneId, $processedIds)) {
                $mergedPhones[] = $auditPhone;
                $processedIds[] = $phoneId;
            }
        }
        
        // Case 6: Add audit phones with action='update' that weren't processed in first loop
        // These are phones that exist in audit table but don't exist in source table
        foreach ($auditPhones as $auditPhone) {
            $phoneId = $auditPhone['id'] ?? null;
            $action = $auditPhone['action'] ?? 'update';
            
            // Case 4: Skip if this phone is deleted
            if ($action === 'delete' || ($phoneId !== null && in_array($phoneId, $deletedIds))) {
                continue;
            }
            
            // Case 6: Add if action is 'update' and not already processed (not in source table)
            if ($action === 'update' && !in_array($phoneId, $processedIds)) {
                $mergedPhones[] = $auditPhone;
                $processedIds[] = $phoneId;
            }
        }
        
        return $mergedPhones;
    }

    /**
     * Get phones from audit table with action information
     * 
     * @param int $clientId
     * @return array Returns array with 'phones' and 'actions' (map of meta_type => action)
     */
    private function getPhonesFromAudit($clientId)
    {
        // Get all phone-related audit entries
        $auditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
            ->whereIn('meta_key', ['phone', 'phone_type', 'phone_country_code', 'phone_extension'])
            ->get();

        // Group by meta_order and meta_key, taking the latest new_value for each combination
        $phoneData = [];
        $latestEntries = []; // Track latest entry for each meta_order + meta_key combination
        $actionMap = []; // Map meta_type to action (for each meta_order)

        foreach ($auditEntries as $entry) {
            $order = $entry->meta_order;
            $key = $entry->meta_key;
            $processedKey = $order . '_' . $key;
            
            // Store the latest entry for this combination
            if (!isset($latestEntries[$processedKey])) {
                $latestEntries[$processedKey] = $entry;
            } else {
                // Compare updated_at to keep the latest
                if (strtotime($entry->updated_at) > strtotime($latestEntries[$processedKey]->updated_at)) {
                    $latestEntries[$processedKey] = $entry;
                }
            }
        }

        // First pass: Process phone entries to get IDs and actions
        foreach ($latestEntries as $processedKey => $entry) {
            $order = $entry->meta_order;
            $key = $entry->meta_key;
            
            // Only process 'phone' entries to get ID and action
            if ($key === 'phone' && !empty($entry->meta_type)) {
                $metaType = is_numeric($entry->meta_type) ? (int) $entry->meta_type : $entry->meta_type;
                $action = $entry->action ?? 'update';
                $actionMap[$metaType] = $action;
            }
        }

        // Second pass: Process all entries to build phone data
        foreach ($latestEntries as $processedKey => $entry) {
            $order = $entry->meta_order;
            $key = $entry->meta_key;
            
            if (!isset($phoneData[$order])) {
                // Get the original record ID from meta_type when meta_key is 'phone'
                $originalId = null;
                $action = 'update'; // Default action
                
                if ($key === 'phone' && !empty($entry->meta_type)) {
                    $originalId = is_numeric($entry->meta_type) ? (int) $entry->meta_type : null;
                    $action = $entry->action ?? 'update';
                } else {
                    // Try to find the phone entry for this meta_order to get ID and action
                    $phoneEntryKey = $order . '_phone';
                    if (isset($latestEntries[$phoneEntryKey])) {
                        $phoneEntry = $latestEntries[$phoneEntryKey];
                        if (!empty($phoneEntry->meta_type)) {
                            $originalId = is_numeric($phoneEntry->meta_type) ? (int) $phoneEntry->meta_type : null;
                            $action = $phoneEntry->action ?? 'update';
                        }
                    }
                }
                
                $phoneData[$order] = [
                    'id' => $originalId,
                    'phone' => null,
                    'type' => null,
                    'country_code' => null,
                    'extension' => null,
                    'action' => $action,
                ];
            }
            
            switch ($key) {
                case 'phone':
                    $phoneData[$order]['phone'] = $entry->new_value;
                    // Also set ID and action if not already set
                    if ($phoneData[$order]['id'] === null && !empty($entry->meta_type)) {
                        $phoneData[$order]['id'] = is_numeric($entry->meta_type) ? (int) $entry->meta_type : null;
                    }
                    if (!empty($entry->action)) {
                        $phoneData[$order]['action'] = $entry->action;
                    }
                    break;
                case 'phone_type':
                    $phoneData[$order]['type'] = $entry->new_value;
                    break;
                case 'phone_country_code':
                    $phoneData[$order]['country_code'] = $entry->new_value;
                    break;
                case 'phone_extension':
                    $phoneData[$order]['extension'] = $entry->new_value;
                    break;
            }
        }

        // Convert to indexed array and filter out entries without phone; add meta_order for approve/reject
        $phones = [];
        foreach ($phoneData as $order => $phone) {
            // Include phone if it has at least phone number
            if (!empty($phone['phone'])) {
                // Determine primary phone (Personal type first)
                $phoneType = strtolower($phone['type'] ?? '');
                $phone['is_primary'] = ($phoneType === 'personal') ? true : false;
                $phone['meta_order'] = $order;
                $phones[] = $phone;
            }
        }

        // If no Personal type found, mark first as primary
        $hasPersonal = false;
        foreach ($phones as $phone) {
            if (strtolower($phone['type'] ?? '') === 'personal') {
                $hasPersonal = true;
                break;
            }
        }
        if (!$hasPersonal && !empty($phones)) {
            $phones[0]['is_primary'] = true;
        }

        return [
            'phones' => $phones,
            'actions' => $actionMap
        ];
    }

    /**
     * Get phones from client_contacts source table
     * 
     * @param int $clientId
     * @return array
     */
    private function getPhonesFromSource($clientId)
    {
        $phones = [];
        
        $clientPhones = DB::table('client_contacts')
            ->where('client_id', $clientId)
            ->orderBy('id')
            ->get();

        foreach ($clientPhones as $index => $phone) {
            $phoneType = $phone->contact_type ?? 'Mobile';
            $phones[] = [
                'id' => $phone->id,
                'phone' => $phone->phone ?? '',
                'type' => $phoneType,
                'country_code' => $phone->country_code ?? null,
                'extension' => $phone->extension ?? null,
                'is_primary' => false, // Will be set below
                'action' => null, // Records from source table have action: null
            ];
        }

        // Determine primary phone (Personal type first, then first in list)
        $personalFound = false;
        foreach ($phones as $index => &$phone) {
            $phoneType = strtolower($phone['type'] ?? '');
            if ($phoneType === 'personal') {
                $phone['is_primary'] = true;
                $personalFound = true;
            }
        }

        // If no Personal type found, mark first as primary
        if (!$personalFound && !empty($phones)) {
            $phones[0]['is_primary'] = true;
        }

        return $phones;
    }

    /**
     * Get emails data - merge audit table (if updated/created/deleted) with source table
     * 
     * Implements 5-case logic:
     * Case 1: No audit records → Shows all from source table (with action: null)
     * Case 2: Action = 'update' → Overwrites source records based on meta_type column (with action: "update")
     * Case 3: Action = 'create' → Shows from audit table (newly created via API with generated IDs, with action: "create")
     * Case 4: Action = 'delete' → Excludes from audit table AND excludes related record with same ID from source table
     * Case 5: Default → Shows all from source table when no update action found (with action: null)
     * 
     * @param int $clientId
     * @return array
     */
    private function getEmailsData($clientId)
    {
        // Get all emails from source table
        $sourceEmails = $this->getEmailsFromSource($clientId);
        
        // Get all emails from audit table with action information
        $auditData = $this->getEmailsFromAudit($clientId);
        $auditEmails = $auditData['emails'];
        $actionMap = $auditData['actions'];
        
        // Case 1: If no audit records, return all source emails
        if (empty($auditEmails) && empty($actionMap)) {
            return $sourceEmails;
        }
        
        // Create a map of audit emails by ID and meta_type for quick lookup
        $auditEmailsByIdMap = [];
        $auditEmailsByMetaTypeMap = [];
        foreach ($auditEmails as $auditEmail) {
            $emailId = $auditEmail['id'] ?? null;
            $action = $auditEmail['action'] ?? 'update';
            
            if ($emailId !== null) {
                $auditEmailsByIdMap[$emailId] = $auditEmail;
            }
            
            // Also map by meta_type (which is stored in the email ID)
            if ($emailId !== null) {
                $auditEmailsByMetaTypeMap[$emailId] = [
                    'email' => $auditEmail,
                    'action' => $action
                ];
            }
        }
        
        // Build final result array
        $mergedEmails = [];
        $processedIds = [];
        $deletedIds = []; // Track IDs that should be excluded (action='delete')
        
        // First, identify all deleted emails (Case 4) from actionMap
        foreach ($actionMap as $metaType => $action) {
            if ($action === 'delete') {
                $deletedIds[] = is_numeric($metaType) ? (int) $metaType : $metaType;
            }
        }
        
        // Also identify deleted emails from audit emails array itself (Case 4)
        foreach ($auditEmails as $auditEmail) {
            $emailId = $auditEmail['id'] ?? null;
            $action = $auditEmail['action'] ?? 'update';
            
            if ($action === 'delete' && $emailId !== null) {
                $deletedId = is_numeric($emailId) ? (int) $emailId : $emailId;
                if (!in_array($deletedId, $deletedIds)) {
                    $deletedIds[] = $deletedId;
                }
            }
        }
        
        // Process source emails
        foreach ($sourceEmails as $sourceEmail) {
            $emailId = $sourceEmail['id'];
            
            // Case 4: Skip if this email is deleted (exclude from both audit and source table)
            if (in_array($emailId, $deletedIds)) {
                continue;
            }
            
            // Case 2: Check if there's an audit entry with action='update' for this ID
            if (isset($auditEmailsByMetaTypeMap[$emailId])) {
                $auditInfo = $auditEmailsByMetaTypeMap[$emailId];
                // Case 2: Overwrite with audit data if action is 'update' (and not 'delete')
                if ($auditInfo['action'] === 'update') {
                    $mergedEmails[] = $auditInfo['email'];
                    $processedIds[] = $emailId;
                    continue;
                }
                // If action is 'delete', it's already handled above (Case 4)
            }
            
            // Case 5: Default - use source data (no update action found)
            $mergedEmails[] = $sourceEmail;
            $processedIds[] = $emailId;
        }
        
        // Case 3: Add audit emails with action='create' (new emails created via API)
        // But exclude if they have action='delete' (Case 4)
        foreach ($auditEmails as $auditEmail) {
            $emailId = $auditEmail['id'] ?? null;
            $action = $auditEmail['action'] ?? 'update';
            
            // Case 4: Skip if this email is deleted
            if ($action === 'delete' || ($emailId !== null && in_array($emailId, $deletedIds))) {
                continue;
            }
            
            // Case 3: Add if action is 'create' and not already processed
            if ($action === 'create' && !in_array($emailId, $processedIds)) {
                $mergedEmails[] = $auditEmail;
                $processedIds[] = $emailId;
            }
        }
        
        // Case 6: Add audit emails with action='update' that weren't processed in first loop
        // These are emails that exist in audit table but don't exist in source table
        foreach ($auditEmails as $auditEmail) {
            $emailId = $auditEmail['id'] ?? null;
            $action = $auditEmail['action'] ?? 'update';
            
            // Case 4: Skip if this email is deleted
            if ($action === 'delete' || ($emailId !== null && in_array($emailId, $deletedIds))) {
                continue;
            }
            
            // Case 6: Add if action is 'update' and not already processed (not in source table)
            if ($action === 'update' && !in_array($emailId, $processedIds)) {
                $mergedEmails[] = $auditEmail;
                $processedIds[] = $emailId;
            }
        }
        
        return $mergedEmails;
    }

    /**
     * Get emails from audit table with action information
     * 
     * @param int $clientId
     * @return array Returns array with 'emails' and 'actions' (map of email identifier => action)
     */
    /**
     * Get emails from audit table with action information
     * 
     * @param int $clientId
     * @return array Returns array with 'emails' and 'actions' (map of meta_type => action)
     */
    private function getEmailsFromAudit($clientId)
    {
        // Get all email-related audit entries
        $auditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
            ->whereIn('meta_key', ['email', 'email_type'])
            ->get();

        // Group by meta_order and meta_key, taking the latest new_value for each combination
        $emailData = [];
        $latestEntries = []; // Track latest entry for each meta_order + meta_key combination
        $actionMap = []; // Map meta_type to action (for each meta_order)

        foreach ($auditEntries as $entry) {
            $order = $entry->meta_order;
            $key = $entry->meta_key;
            $processedKey = $order . '_' . $key;
            
            // Store the latest entry for this combination
            if (!isset($latestEntries[$processedKey])) {
                $latestEntries[$processedKey] = $entry;
            } else {
                // Compare updated_at to keep the latest
                if (strtotime($entry->updated_at) > strtotime($latestEntries[$processedKey]->updated_at)) {
                    $latestEntries[$processedKey] = $entry;
                }
            }
        }

        // First pass: Process email entries to get IDs and actions
        foreach ($latestEntries as $processedKey => $entry) {
            $order = $entry->meta_order;
            $key = $entry->meta_key;
            
            // Only process 'email' entries to get ID and action
            if ($key === 'email' && !empty($entry->meta_type)) {
                $metaType = is_numeric($entry->meta_type) ? (int) $entry->meta_type : $entry->meta_type;
                $action = $entry->action ?? 'update';
                $actionMap[$metaType] = $action;
            }
        }

        // Second pass: Process all entries to build email data
        foreach ($latestEntries as $processedKey => $entry) {
            $order = $entry->meta_order;
            $key = $entry->meta_key;
            
            if (!isset($emailData[$order])) {
                // Get the original record ID from meta_type when meta_key is 'email'
                $originalId = null;
                $action = 'update'; // Default action
                
                if ($key === 'email' && !empty($entry->meta_type)) {
                    $originalId = is_numeric($entry->meta_type) ? (int) $entry->meta_type : null;
                    $action = $entry->action ?? 'update';
                } else {
                    // Try to find the email entry for this meta_order to get ID and action
                    $emailEntryKey = $order . '_email';
                    if (isset($latestEntries[$emailEntryKey])) {
                        $emailEntry = $latestEntries[$emailEntryKey];
                        if (!empty($emailEntry->meta_type)) {
                            $originalId = is_numeric($emailEntry->meta_type) ? (int) $emailEntry->meta_type : null;
                            $action = $emailEntry->action ?? 'update';
                        }
                    }
                }
                
                $emailData[$order] = [
                    'id' => $originalId,
                    'email' => null,
                    'type' => null,
                    'action' => $action,
                ];
            }
            
            switch ($key) {
                case 'email':
                    $emailData[$order]['email'] = $entry->new_value;
                    // Also set ID and action if not already set
                    if ($emailData[$order]['id'] === null && !empty($entry->meta_type)) {
                        $emailData[$order]['id'] = is_numeric($entry->meta_type) ? (int) $entry->meta_type : null;
                    }
                    if (!empty($entry->action)) {
                        $emailData[$order]['action'] = $entry->action;
                    }
                    break;
                case 'email_type':
                    $emailData[$order]['type'] = $entry->new_value;
                    break;
            }
        }

        // Convert to indexed array and filter out entries without email; add meta_order for approve/reject
        $emails = [];
        foreach ($emailData as $order => $email) {
            // Include email if it has at least email address
            if (!empty($email['email'])) {
                // Determine primary email (Personal type first)
                $emailType = strtolower($email['type'] ?? '');
                $email['is_primary'] = ($emailType === 'personal') ? true : false;
                $email['meta_order'] = $order;
                $emails[] = $email;
            }
        }

        // If no Personal type found, mark first as primary
        $hasPersonal = false;
        foreach ($emails as $email) {
            if (strtolower($email['type'] ?? '') === 'personal') {
                $hasPersonal = true;
                break;
            }
        }
        if (!$hasPersonal && !empty($emails)) {
            $emails[0]['is_primary'] = true;
        }

        return [
            'emails' => $emails,
            'actions' => $actionMap
        ];
    }

    /**
     * Get emails from client_emails source table
     * 
     * @param int $clientId
     * @return array
     */
    private function getEmailsFromSource($clientId)
    {
        $emails = [];
        
        $clientEmails = DB::table('client_emails')
            ->where('client_id', $clientId)
            ->orderBy('id')
            ->get();

        foreach ($clientEmails as $email) {
            $emailType = $email->email_type ?? 'Personal';
            $emails[] = [
                'id' => $email->id,
                'email' => $email->email ?? '',
                'type' => $emailType,
                'is_primary' => false, // Will be set below
                'action' => null, // Records from source table have action: null
            ];
        }

        // Determine primary email (Personal type first, then first in list)
        $personalFound = false;
        foreach ($emails as $index => &$email) {
            $emailType = strtolower($email['type'] ?? '');
            if ($emailType === 'personal') {
                $email['is_primary'] = true;
                $personalFound = true;
            }
        }

        // If no Personal type found, mark first as primary
        if (!$personalFound && !empty($emails)) {
            $emails[0]['is_primary'] = true;
        }

        return $emails;
    }

    /**
     * Get passports data - merge audit table (if updated) with client_passport_informations table
     * Handles all cases based on action types:
     * Case 1: No audit records → show all from client_passport_informations table
     * Case 2: Action = 'update' → overwrite client_passport_informations records based on meta_type column
     * Case 3: Action = 'create' → show from audit table
     * Case 4: Action = 'delete' → don't show (exclude from results)
     * Case 5: Default → show all from client_passport_informations table
     * 
     * @param int $clientId
     * @return array
     */
    private function getPassportsData($clientId)
    {
        // Get all passports from source table
        $sourcePassports = $this->getPassportsFromSource($clientId);
        
        // Get all passports from audit table with action information
        $auditData = $this->getPassportsFromAudit($clientId);
        $auditPassports = $auditData['passports'];
        $actionMap = $auditData['actions'];
        
        // Case 1: If no audit records, return all source passports
        if (empty($auditPassports) && empty($actionMap)) {
            return $sourcePassports;
        }
        
        // Create a map of audit passports by ID and meta_type for quick lookup
        $auditPassportsByIdMap = [];
        $auditPassportsByMetaTypeMap = [];
        foreach ($auditPassports as $auditPassport) {
            $passportId = $auditPassport['id'] ?? null;
            $action = $auditPassport['action'] ?? 'update';
            
            if ($passportId !== null) {
                $auditPassportsByIdMap[$passportId] = $auditPassport;
            }
            
            // Also map by meta_type (which is stored in the passport ID)
            if ($passportId !== null) {
                $auditPassportsByMetaTypeMap[$passportId] = [
                    'passport' => $auditPassport,
                    'action' => $action
                ];
            }
        }
        
        // Build final result array
        $mergedPassports = [];
        $processedIds = [];
        $deletedIds = []; // Track IDs that should be excluded (action='delete')
        
        // First, identify all deleted passports (Case 4)
        foreach ($actionMap as $metaType => $action) {
            if ($action === 'delete') {
                $deletedIds[] = is_numeric($metaType) ? (int) $metaType : $metaType;
            }
        }
        
        // Process source passports
        foreach ($sourcePassports as $sourcePassport) {
            $passportId = $sourcePassport['id'];
            
            // Case 4: Skip if this passport is deleted
            if (in_array($passportId, $deletedIds)) {
                continue;
            }
            
            // Case 2: Check if there's an audit entry with action='update' for this ID
            if (isset($auditPassportsByMetaTypeMap[$passportId])) {
                $auditInfo = $auditPassportsByMetaTypeMap[$passportId];
                // Case 2: Overwrite with audit data if action is 'update' (and not 'delete')
                if ($auditInfo['action'] === 'update') {
                    $mergedPassports[] = $auditInfo['passport'];
                $processedIds[] = $passportId;
                    continue;
                }
                // If action is 'delete', it's already handled above (Case 4)
            }
            
            // Case 5: Default - use source data (no update action found)
                $mergedPassports[] = $sourcePassport;
                $processedIds[] = $passportId;
        }
        
        // Case 3: Add audit passports with action='create' (new passports created via API)
        // But exclude if they have action='delete' (Case 4)
        foreach ($auditPassports as $auditPassport) {
            $passportId = $auditPassport['id'] ?? null;
            $action = $auditPassport['action'] ?? 'update';
            
            // Case 4: Skip if this passport is deleted
            if ($action === 'delete' || ($passportId !== null && in_array($passportId, $deletedIds))) {
                continue;
            }
            
            // Case 3: Add if action is 'create' and not already processed
            if ($action === 'create' && !in_array($passportId, $processedIds)) {
                $mergedPassports[] = $auditPassport;
                $processedIds[] = $passportId;
            }
        }
        
        // Case 6: Add audit passports with action='update' that weren't processed in first loop
        // These are passports that exist in audit table but don't exist in source table
        foreach ($auditPassports as $auditPassport) {
            $passportId = $auditPassport['id'] ?? null;
            $action = $auditPassport['action'] ?? 'update';
            
            // Case 4: Skip if this passport is deleted
            if ($action === 'delete' || ($passportId !== null && in_array($passportId, $deletedIds))) {
                continue;
            }
            
            // Case 6: Add if action is 'update' and not already processed (not in source table)
            if ($action === 'update' && !in_array($passportId, $processedIds)) {
                $mergedPassports[] = $auditPassport;
                $processedIds[] = $passportId;
            }
        }
        
        return $mergedPassports;
    }

    /**
     * Get passports from audit table with action information
     * 
     * @param int $clientId
     * @return array Returns array with 'passports' and 'actions' (map of meta_type => action)
     */
    private function getPassportsFromAudit($clientId)
    {
        // Get all passport-related audit entries
        $auditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
            ->whereIn('meta_key', ['passport', 'passport_country', 'passport_issue_date', 'passport_expiry_date'])
            ->get();

        // Group by meta_order and meta_key, taking the latest new_value for each combination
        $passportData = [];
        $latestEntries = []; // Track latest entry for each meta_order + meta_key combination
        $actionMap = []; // Map meta_type to action (for each meta_order)

        foreach ($auditEntries as $entry) {
            $order = $entry->meta_order;
            $key = $entry->meta_key;
            $processedKey = $order . '_' . $key;
            
            // Store the latest entry for this combination
            if (!isset($latestEntries[$processedKey])) {
                $latestEntries[$processedKey] = $entry;
            } else {
                // Compare updated_at to keep the latest
                if (strtotime($entry->updated_at) > strtotime($latestEntries[$processedKey]->updated_at)) {
                    $latestEntries[$processedKey] = $entry;
                }
            }
        }

        // First pass: Process passport entries to get IDs and actions
        foreach ($latestEntries as $processedKey => $entry) {
            $order = $entry->meta_order;
            $key = $entry->meta_key;
            
            // Only process 'passport' entries to get ID and action
            if ($key === 'passport' && !empty($entry->meta_type)) {
                $metaType = is_numeric($entry->meta_type) ? (int) $entry->meta_type : $entry->meta_type;
                $action = $entry->action ?? 'update';
                $actionMap[$metaType] = $action;
            }
        }
        
        // Second pass: Process all entries to build passport data
        foreach ($latestEntries as $processedKey => $entry) {
            $order = $entry->meta_order;
            $key = $entry->meta_key;
            
            if (!isset($passportData[$order])) {
                // Get the original record ID from meta_type when meta_key is 'passport'
                $originalId = null;
                $action = 'update'; // Default action
                
                if ($key === 'passport' && !empty($entry->meta_type)) {
                    $originalId = is_numeric($entry->meta_type) ? (int) $entry->meta_type : null;
                    $action = $entry->action ?? 'update';
                } else {
                    // Try to find the passport entry for this meta_order to get ID and action
                    $passportEntryKey = $order . '_passport';
                    if (isset($latestEntries[$passportEntryKey])) {
                        $passportEntry = $latestEntries[$passportEntryKey];
                        if (!empty($passportEntry->meta_type)) {
                            $originalId = is_numeric($passportEntry->meta_type) ? (int) $passportEntry->meta_type : null;
                            $action = $passportEntry->action ?? 'update';
                        }
                    }
                }
                
                $passportData[$order] = [
                    'id' => $originalId,
                    'passport_number' => null,
                    'country' => null,
                    'issue_date' => null,
                    'expiry_date' => null,
                    'action' => $action,
                ];
            }
            
            switch ($key) {
                case 'passport':
                    $passportData[$order]['passport_number'] = $entry->new_value;
                    $passportData[$order]['action'] = $entry->action ?? 'update';
                    // Also set ID if not already set (in case passport entry comes after other fields)
                    if ($passportData[$order]['id'] === null && !empty($entry->meta_type)) {
                        $passportData[$order]['id'] = is_numeric($entry->meta_type) ? (int) $entry->meta_type : null;
                    }
                    break;
                case 'passport_country':
                    $passportData[$order]['country'] = $entry->new_value;
                    break;
                case 'passport_issue_date':
                    $passportData[$order]['issue_date'] = $entry->new_value ? $this->formatDate($entry->new_value) : null;
                    break;
                case 'passport_expiry_date':
                    $passportData[$order]['expiry_date'] = $entry->new_value ? $this->formatDate($entry->new_value) : null;
                    break;
            }
        }

        // Convert to indexed array and filter out entries without passport_number; include meta_order for approve/reject
        $passports = [];
        foreach ($passportData as $order => $passport) {
            if (!empty($passport['passport_number'])) {
                $passport['meta_order'] = $order;
                $passports[] = $passport;
            }
        }

        return [
            'passports' => $passports,
            'actions' => $actionMap
        ];
    }

    /**
     * Public entry point for CRM client portal: returns passports merged from
     * client_passport_informations and clientportal_details_audit (same logic as getPassportsData).
     *
     * @param int $clientId
     * @return array
     */
    public function getMergedPassportsForClient($clientId)
    {
        return $this->getPassportsData($clientId);
    }

    /**
     * Get merged qualifications for client (source + audit) for CRM Details tab.
     *
     * @param int $clientId
     * @return array
     */
    public function getMergedQualificationsForClient($clientId)
    {
        return $this->getQualificationsData($clientId);
    }

    /**
     * Get merged experiences for client (source + audit) for CRM Details tab.
     *
     * @param int $clientId
     * @return array
     */
    public function getMergedExperiencesForClient($clientId)
    {
        return $this->getExperiencesData($clientId);
    }

    /**
     * Get merged occupations for client (source + audit) for CRM Details tab.
     *
     * @param int $clientId
     * @return array
     */
    public function getMergedOccupationsForClient($clientId)
    {
        return $this->getOccupationsData($clientId);
    }

    /**
     * Get merged test scores for client (source + audit) for CRM Details tab.
     *
     * @param int $clientId
     * @return array
     */
    public function getMergedTestScoresForClient($clientId)
    {
        return $this->getTestScoresData($clientId);
    }

    /**
     * Generate a unique timestamp-based 10-digit ID for new passport records
     * 
     * @return int
     */
    private function generateTimestampBasedId()
    {
        // Get current Unix timestamp (10 digits)
        $timestamp = time();
        
        // Generate a random 3-digit number to add uniqueness
        $random = mt_rand(100, 999);
        
        // Combine timestamp and random, take last 10 digits
        // This ensures we always get a 10-digit number
        $combined = (string) ($timestamp . $random);
        $id = (int) substr($combined, -10);
        
        // Ensure it's exactly 10 digits (pad with zeros if needed, though unlikely)
        if (strlen((string) $id) < 10) {
            $id = (int) str_pad((string) $id, 10, '0', STR_PAD_LEFT);
        }
        
        return $id;
    }

    /**
     * Get passports from client_passport_informations source table
     * 
     * @param int $clientId
     * @return array
     */
    private function getPassportsFromSource($clientId)
    {
        $passports = [];
        
        $clientPassports = DB::table('client_passport_informations')
            ->where('client_id', $clientId)
            ->orderBy('id')
            ->get();

        foreach ($clientPassports as $passport) {
            $passports[] = [
                'id' => $passport->id,
                'passport_number' => $passport->passport ?? '',
                'country' => $passport->passport_country ?? null,
                'issue_date' => $passport->passport_issue_date ? $this->formatDate($passport->passport_issue_date) : null,
                'expiry_date' => $passport->passport_expiry_date ? $this->formatDate($passport->passport_expiry_date) : null,
                'action' => null, // Records from source table have action: null
            ];
        }

        return $passports;
    }

    /**
     * Get visas data - merge audit table (if updated) with client_visa_countries table
     * Handles all cases based on action types:
     * Case 1: No audit records → show all from client_visa_countries table
     * Case 2: Action = 'update' → overwrite client_visa_countries records based on meta_type column
     * Case 3: Action = 'create' → show from audit table
     * Case 4: Action = 'delete' → don't show from audit table AND exclude related record with same ID from client_visa_countries table
     * Case 5: Default → show all from client_visa_countries table
     * 
     * @param int $clientId
     * @return array
     */
    /**
     * Public entry point for CRM client portal: returns visas merged from
     * client_visa_countries and clientportal_details_audit (same logic as getVisasData).
     *
     * @param int $clientId
     * @return array
     */
    public function getMergedVisasForClient($clientId)
    {
        return $this->getVisasData($clientId);
    }

    /**
     * Get merged emails for client (source + audit) for CRM Details tab.
     *
     * @param int $clientId
     * @return array
     */
    public function getMergedEmailsForClient($clientId)
    {
        return $this->getEmailsData($clientId);
    }

    /**
     * Get merged phones for client (source + audit) for CRM Details tab.
     *
     * @param int $clientId
     * @return array
     */
    public function getMergedPhonesForClient($clientId)
    {
        return $this->getPhonesData($clientId);
    }

    private function getVisasData($clientId)
    {
        // Get all visas from source table
        $sourceVisas = $this->getVisasFromSource($clientId);
        
        // Get all visas from audit table with action information
        $auditData = $this->getVisasFromAudit($clientId);
        $auditVisas = $auditData['visas'];
        $actionMap = $auditData['actions'];
        
        // Case 1: If no audit records, return all source visas
        if (empty($auditVisas) && empty($actionMap)) {
            return $sourceVisas;
        }
        
        // Create a map of audit visas by ID and meta_type for quick lookup
        $auditVisasByIdMap = [];
        $auditVisasByMetaTypeMap = [];
        foreach ($auditVisas as $auditVisa) {
            $visaId = $auditVisa['id'] ?? null;
            $action = $auditVisa['action'] ?? 'update';
            
            if ($visaId !== null) {
                $auditVisasByIdMap[$visaId] = $auditVisa;
            }
            
            // Also map by meta_type (which is stored in the visa ID)
            if ($visaId !== null) {
                $auditVisasByMetaTypeMap[$visaId] = [
                    'visa' => $auditVisa,
                    'action' => $action
                ];
            }
        }
        
        // Build final result array
        $mergedVisas = [];
        $processedIds = [];
        $deletedIds = []; // Track IDs that should be excluded (action='delete')
        
        // First, identify all deleted visas (Case 4) from actionMap
        foreach ($actionMap as $metaType => $action) {
            if ($action === 'delete') {
                $deletedId = is_numeric($metaType) ? (int) $metaType : $metaType;
                $deletedIds[] = $deletedId;
            }
        }
        
        // Also identify deleted visas from audit visas array itself (Case 4)
        foreach ($auditVisas as $auditVisa) {
            $visaId = $auditVisa['id'] ?? null;
            $action = $auditVisa['action'] ?? 'update';
            
            if ($action === 'delete' && $visaId !== null) {
                $deletedId = is_numeric($visaId) ? (int) $visaId : $visaId;
                if (!in_array($deletedId, $deletedIds)) {
                    $deletedIds[] = $deletedId;
                }
            }
        }
        
        // Process source visas
        foreach ($sourceVisas as $sourceVisa) {
            $visaId = $sourceVisa['id'];
            
            // Case 4: Skip if this visa is deleted (exclude from both audit and source table)
            if (in_array($visaId, $deletedIds)) {
                continue;
            }
            
            // Case 2: Check if there's an audit entry with action='update' for this ID
            if (isset($auditVisasByMetaTypeMap[$visaId])) {
                $auditInfo = $auditVisasByMetaTypeMap[$visaId];
                // Case 2: Overwrite with audit data if action is 'update' (and not 'delete')
                if ($auditInfo['action'] === 'update') {
                    $mergedVisas[] = $auditInfo['visa'];
                    $processedIds[] = $visaId;
                    continue;
                }
                // If action is 'delete', it's already handled above (Case 4)
            }
            
            // Case 5: Default - use source data (no update action found)
            $mergedVisas[] = $sourceVisa;
            $processedIds[] = $visaId;
        }
        
        // Case 3: Add audit visas with action='create' (new visas created via API)
        // But exclude if they have action='delete' (Case 4)
        foreach ($auditVisas as $auditVisa) {
            $visaId = $auditVisa['id'] ?? null;
            $action = $auditVisa['action'] ?? 'update';
            
            // Case 4: Skip if this visa is deleted
            if ($action === 'delete' || ($visaId !== null && in_array($visaId, $deletedIds))) {
                continue;
            }
            
            // Case 3: Add if action is 'create' and not already processed
            if ($action === 'create' && !in_array($visaId, $processedIds)) {
                $mergedVisas[] = $auditVisa;
                $processedIds[] = $visaId;
            }
        }
        
        // Case 6: Add audit visas with action='update' that weren't processed in first loop
        // These are visas that exist in audit table but don't exist in source table
        foreach ($auditVisas as $auditVisa) {
            $visaId = $auditVisa['id'] ?? null;
            $action = $auditVisa['action'] ?? 'update';
            
            // Case 4: Skip if this visa is deleted
            if ($action === 'delete' || ($visaId !== null && in_array($visaId, $deletedIds))) {
                continue;
            }
            
            // Case 6: Add if action is 'update' and not already processed (not in source table)
            if ($action === 'update' && !in_array($visaId, $processedIds)) {
                $mergedVisas[] = $auditVisa;
                $processedIds[] = $visaId;
            }
        }

        // Ensure visa_country is populated from client's country_passport when null
        $client = Admin::find($clientId);
        $countryPassport = $client ? ($client->country_passport ?? null) : null;
        foreach ($mergedVisas as &$visa) {
            if (empty($visa['visa_country']) && $countryPassport) {
                $visa['visa_country'] = $countryPassport;
            }
        }
        
        return $mergedVisas;
    }

    /**
     * Get visas from audit table with action information
     * 
     * @param int $clientId
     * @return array Returns array with 'visas' and 'actions' (map of meta_type => action)
     */
    private function getVisasFromAudit($clientId)
    {
        // Get all visa-related audit entries
        $auditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
            ->whereIn('meta_key', ['visa', 'visa_country', 'visa_type', 'visa_description', 'visa_expiry_date', 'visa_grant_date'])
            ->get();

        // Group by meta_order and meta_key, taking the latest new_value for each combination
        $visaData = [];
        $latestEntries = []; // Track latest entry for each meta_order + meta_key combination
        $actionMap = []; // Map meta_type to action (for each meta_order)

        foreach ($auditEntries as $entry) {
            $order = $entry->meta_order;
            $key = $entry->meta_key;
            $processedKey = $order . '_' . $key;
            
            // Store the latest entry for this combination
            if (!isset($latestEntries[$processedKey])) {
                $latestEntries[$processedKey] = $entry;
            } else {
                // Compare updated_at to keep the latest
                if (strtotime($entry->updated_at) > strtotime($latestEntries[$processedKey]->updated_at)) {
                    $latestEntries[$processedKey] = $entry;
                }
            }
        }

        // First pass: Process visa entries to get IDs and actions
        foreach ($latestEntries as $processedKey => $entry) {
            $order = $entry->meta_order;
            $key = $entry->meta_key;
            
            // Only process 'visa' entries to get ID and action
            if ($key === 'visa' && !empty($entry->meta_type)) {
                $metaType = is_numeric($entry->meta_type) ? (int) $entry->meta_type : $entry->meta_type;
                $action = $entry->action ?? 'update';
                $actionMap[$metaType] = $action;
            }
        }

        // Second pass: Process all entries to build visa data
        foreach ($latestEntries as $processedKey => $entry) {
            $order = $entry->meta_order;
            $key = $entry->meta_key;
            
            // Initialize visa entry if not exists
            if (!isset($visaData[$order])) {
                // Get the original record ID from meta_type when meta_key is 'visa'
                $originalId = null;
                $action = 'update'; // Default action
                
                if ($key === 'visa' && !empty($entry->meta_type)) {
                    $originalId = is_numeric($entry->meta_type) ? (int) $entry->meta_type : null;
                    $action = $entry->action ?? 'update';
                } else {
                    // Try to find the visa entry for this meta_order to get ID and action
                    $visaEntryKey = $order . '_visa';
                    if (isset($latestEntries[$visaEntryKey])) {
                        $visaEntry = $latestEntries[$visaEntryKey];
                        if (!empty($visaEntry->meta_type)) {
                            $originalId = is_numeric($visaEntry->meta_type) ? (int) $visaEntry->meta_type : null;
                            $action = $visaEntry->action ?? 'update';
                        }
                    }
                }
                
                $visaData[$order] = [
                    'id' => $originalId,
                    'visa_country' => null,
                    'visa_type' => null,
                    'visa_description' => null,
                    'visa_expiry_date' => null,
                    'visa_grant_date' => null,
                    'action' => $action,
                ];
            }
            
            switch ($key) {
                case 'visa':
                    // Visa marker - get ID and action from meta_type
                    if (!empty($entry->meta_type)) {
                        $visaData[$order]['id'] = is_numeric($entry->meta_type) ? (int) $entry->meta_type : null;
                        $visaData[$order]['action'] = $entry->action ?? 'update';
                    }
                    break;
                case 'visa_country':
                    $visaData[$order]['visa_country'] = $entry->new_value;
                    break;
                case 'visa_type':
                    $visaData[$order]['visa_type'] = $entry->new_value;
                    break;
                case 'visa_description':
                    $visaData[$order]['visa_description'] = $entry->new_value;
                    break;
                case 'visa_expiry_date':
                    $visaData[$order]['visa_expiry_date'] = $entry->new_value ? $this->formatDate($entry->new_value) : null;
                    break;
                case 'visa_grant_date':
                    $visaData[$order]['visa_grant_date'] = $entry->new_value ? $this->formatDate($entry->new_value) : null;
                    break;
            }
        }

        // Convert to indexed array and filter out entries without visa_type; include meta_order for approve/reject
        $visas = [];
        foreach ($visaData as $order => $visa) {
            if (!empty($visa['visa_type'])) {
                $visa['meta_order'] = $order;
                $visas[] = $visa;
            }
        }

        return [
            'visas' => $visas,
            'actions' => $actionMap
        ];
    }

    /**
     * Get visas from client_visa_countries source table
     * 
     * @param int $clientId
     * @return array
     */
    private function getVisasFromSource($clientId)
    {
        $visas = [];
        $client = Admin::find($clientId);
        $countryPassport = $client ? ($client->country_passport ?? null) : null;

        $clientVisas = DB::table('client_visa_countries')
            ->where('client_id', $clientId)
            ->orderBy('id')
            ->get();

        foreach ($clientVisas as $visa) {
            $visas[] = [
                'id' => $visa->id,
                'visa_country' => $countryPassport,
                'visa_type' => $visa->visa_type ?? null,
                'visa_description' => $visa->visa_description ?? null,
                'visa_expiry_date' => !empty($visa->visa_expiry_date) ? $this->formatDate($visa->visa_expiry_date) : null,
                'visa_grant_date' => $visa->visa_grant_date ? $this->formatDate($visa->visa_grant_date) : null,
                'action' => null, // Records from source table have action: null
            ];
        }

        return $visas;
    }

    /**
     * Get addresses data - merge audit table (if updated) with client_addresses table
     * Handles all cases based on action types:
     * Case 1: No audit records → show all from client_addresses table
     * Case 2: Action = 'update' → overwrite client_addresses records based on meta_type column
     * Case 3: Action = 'create' → show from audit table
     * Case 4: Action = 'delete' → don't show from audit table AND exclude related record with same ID from client_addresses table
     * Case 5: Default → show all from client_addresses table
     * 
     * @param int $clientId
     * @return array
     */
    private function getAddressesData($clientId)
    {
        // Get all addresses from source table
        $sourceAddresses = $this->getAddressesFromSource($clientId);
        
        // Get all addresses from audit table with action information
        $auditData = $this->getAddressesFromAudit($clientId);
        $auditAddresses = $auditData['addresses'];
        $actionMap = $auditData['actions'];
        
        // Case 1: If no audit records, return all source addresses
        if (empty($auditAddresses) && empty($actionMap)) {
            return $sourceAddresses;
        }
        
        // Create a map of audit addresses by ID and meta_type for quick lookup
        $auditAddressesByIdMap = [];
        $auditAddressesByMetaTypeMap = [];
        foreach ($auditAddresses as $auditAddress) {
            $addressId = $auditAddress['id'] ?? null;
            $action = $auditAddress['action'] ?? 'update';
            
            if ($addressId !== null) {
                $auditAddressesByIdMap[$addressId] = $auditAddress;
            }
            
            // Also map by meta_type (which is stored in the address ID)
            if ($addressId !== null) {
                $auditAddressesByMetaTypeMap[$addressId] = [
                    'address' => $auditAddress,
                    'action' => $action
                ];
            }
        }
        
        // Build final result array
        $mergedAddresses = [];
        $processedIds = [];
        $deletedIds = []; // Track IDs that should be excluded (action='delete')
        
        // First, identify all deleted addresses (Case 4) from actionMap
        foreach ($actionMap as $metaType => $action) {
            if ($action === 'delete') {
                $deletedIds[] = is_numeric($metaType) ? (int) $metaType : $metaType;
            }
        }
        
        // Also identify deleted addresses from audit addresses array itself (Case 4)
        foreach ($auditAddresses as $auditAddress) {
            $addressId = $auditAddress['id'] ?? null;
            $action = $auditAddress['action'] ?? 'update';
            
            if ($action === 'delete' && $addressId !== null) {
                $deletedId = is_numeric($addressId) ? (int) $addressId : $addressId;
                if (!in_array($deletedId, $deletedIds)) {
                    $deletedIds[] = $deletedId;
                }
            }
        }
        
        // Process source addresses
        foreach ($sourceAddresses as $sourceAddress) {
            $addressId = $sourceAddress['id'];
            
            // Case 4: Skip if this address is deleted (exclude from both audit and source table)
            if (in_array($addressId, $deletedIds)) {
                continue;
            }
            
            // Case 2: Check if there's an audit entry with action='update' for this ID
            if (isset($auditAddressesByMetaTypeMap[$addressId])) {
                $auditInfo = $auditAddressesByMetaTypeMap[$addressId];
                // Case 2: Overwrite with audit data if action is 'update' (and not 'delete')
                if ($auditInfo['action'] === 'update') {
                    $mergedAddresses[] = $auditInfo['address'];
                    $processedIds[] = $addressId;
                    continue;
                }
                // If action is 'delete', it's already handled above (Case 4)
            }
            
            // Case 5: Default - use source data (no update action found)
            $mergedAddresses[] = $sourceAddress;
            $processedIds[] = $addressId;
        }
        
        // Case 3: Add audit addresses with action='create' (new addresses created via API)
        // But exclude if they have action='delete' (Case 4)
        foreach ($auditAddresses as $auditAddress) {
            $addressId = $auditAddress['id'] ?? null;
            $action = $auditAddress['action'] ?? 'update';
            
            // Case 4: Skip if this address is deleted
            if ($action === 'delete' || ($addressId !== null && in_array($addressId, $deletedIds))) {
                continue;
            }
            
            // Case 3: Add if action is 'create' and not already processed
            if ($action === 'create' && !in_array($addressId, $processedIds)) {
                $mergedAddresses[] = $auditAddress;
                $processedIds[] = $addressId;
            }
        }
        
        // Case 6: Add audit addresses with action='update' that weren't processed in first loop
        // These are addresses that exist in audit table but don't exist in source table
        foreach ($auditAddresses as $auditAddress) {
            $addressId = $auditAddress['id'] ?? null;
            $action = $auditAddress['action'] ?? 'update';
            
            // Case 4: Skip if this address is deleted
            if ($action === 'delete' || ($addressId !== null && in_array($addressId, $deletedIds))) {
                continue;
            }
            
            // Case 6: Add if action is 'update' and not already processed (not in source table)
            if ($action === 'update' && !in_array($addressId, $processedIds)) {
                $mergedAddresses[] = $auditAddress;
                $processedIds[] = $addressId;
            }
        }
        
        return $mergedAddresses;
    }

    /**
     * Get addresses from audit table with action information
     * 
     * @param int $clientId
     * @return array Returns array with 'addresses' and 'actions' (map of meta_type => action)
     */
    private function getAddressesFromAudit($clientId)
    {
        // Get all address-related audit entries
        $auditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
            ->whereIn('meta_key', ['address', 'address_line_1', 'address_line_2', 'address_suburb', 'address_state', 'address_postcode', 'address_country', 'address_regional_code', 'address_start_date', 'address_end_date', 'address_is_current'])
            ->get();

        // Group by meta_order and meta_key, taking the latest new_value for each combination
        $addressData = [];
        $latestEntries = []; // Track latest entry for each meta_order + meta_key combination
        $actionMap = []; // Map meta_type to action (for each meta_order)

        foreach ($auditEntries as $entry) {
            $order = $entry->meta_order;
            $key = $entry->meta_key;
            $processedKey = $order . '_' . $key;
            
            // Store the latest entry for this combination
            if (!isset($latestEntries[$processedKey])) {
                $latestEntries[$processedKey] = $entry;
            } else {
                // Compare updated_at to keep the latest
                if (strtotime($entry->updated_at) > strtotime($latestEntries[$processedKey]->updated_at)) {
                    $latestEntries[$processedKey] = $entry;
                }
            }
        }

        // First pass: Process address entries to get IDs and actions
        foreach ($latestEntries as $processedKey => $entry) {
            $order = $entry->meta_order;
            $key = $entry->meta_key;
            
            // Only process 'address_line_1' entries to get ID and action
            if ($key === 'address_line_1' && !empty($entry->meta_type)) {
                $metaType = is_numeric($entry->meta_type) ? (int) $entry->meta_type : $entry->meta_type;
                $action = $entry->action ?? 'update';
                $actionMap[$metaType] = $action;
            }
        }

        // Second pass: Process all entries to build address data
        foreach ($latestEntries as $processedKey => $entry) {
            $order = $entry->meta_order;
            $key = $entry->meta_key;
            
            if (!isset($addressData[$order])) {
                // Get the original record ID from meta_type when meta_key is 'address_line_1'
                $originalId = null;
                $action = 'update'; // Default action
                
                if ($key === 'address_line_1' && !empty($entry->meta_type)) {
                    $originalId = is_numeric($entry->meta_type) ? (int) $entry->meta_type : null;
                    $action = $entry->action ?? 'update';
                } else {
                    // Try to find the address_line_1 entry for this meta_order to get ID and action
                    $addressLine1Key = $order . '_address_line_1';
                    if (isset($latestEntries[$addressLine1Key])) {
                        $addressLine1Entry = $latestEntries[$addressLine1Key];
                        if (!empty($addressLine1Entry->meta_type)) {
                            $originalId = is_numeric($addressLine1Entry->meta_type) ? (int) $addressLine1Entry->meta_type : null;
                            $action = $addressLine1Entry->action ?? 'update';
                        }
                    }
                }
                
                $addressData[$order] = [
                    'id' => $originalId,
                    'search_address' => null,
                    'address_line_1' => null,
                    'address_line_2' => null,
                    'suburb' => null,
                    'state' => null,
                    'postcode' => null,
                    'country' => null,
                    'regional_code' => null,
                    'start_date' => null,
                    'end_date' => null,
                    'is_current' => false,
                    'action' => $action,
                ];
            }
            
            switch ($key) {
                case 'address':
                    $addressData[$order]['search_address'] = $entry->new_value;
                    break;
                case 'address_line_1':
                    $addressData[$order]['address_line_1'] = $entry->new_value;
                    // Also set ID and action if not already set
                    if ($addressData[$order]['id'] === null && !empty($entry->meta_type)) {
                        $addressData[$order]['id'] = is_numeric($entry->meta_type) ? (int) $entry->meta_type : null;
                    }
                    if (!empty($entry->action)) {
                        $addressData[$order]['action'] = $entry->action;
                    }
                    break;
                case 'address_line_2':
                    $addressData[$order]['address_line_2'] = $entry->new_value;
                    break;
                case 'address_suburb':
                    $addressData[$order]['suburb'] = $entry->new_value;
                    break;
                case 'address_state':
                    $addressData[$order]['state'] = $entry->new_value;
                    break;
                case 'address_postcode':
                    $addressData[$order]['postcode'] = $entry->new_value;
                    break;
                case 'address_country':
                    $addressData[$order]['country'] = $entry->new_value;
                    break;
                case 'address_regional_code':
                    $addressData[$order]['regional_code'] = $entry->new_value;
                    break;
                case 'address_start_date':
                    $addressData[$order]['start_date'] = $entry->new_value ? $this->formatDate($entry->new_value) : null;
                    break;
                case 'address_end_date':
                    $addressData[$order]['end_date'] = $entry->new_value ? $this->formatDate($entry->new_value) : null;
                    break;
                case 'address_is_current':
                    $addressData[$order]['is_current'] = ($entry->new_value == '1' || $entry->new_value == 1) ? true : false;
                    break;
            }
        }

        // Convert to indexed array and filter out entries without at least search_address or address_line_1; include meta_order for approve/reject
        $addresses = [];
        foreach ($addressData as $order => $address) {
            // Include address if it has at least search_address or address_line_1
            if (!empty($address['search_address']) || !empty($address['address_line_1'])) {
                $address['meta_order'] = $order;
                $addresses[] = $address;
            }
        }

        return [
            'addresses' => $addresses,
            'actions' => $actionMap
        ];
    }

    /**
     * Public entry point for CRM client portal: returns addresses merged from
     * client_addresses and clientportal_details_audit.
     *
     * @param int $clientId
     * @return array
     */
    public function getMergedAddressesForClient($clientId)
    {
        return $this->getAddressesData($clientId);
    }

    /**
     * Get addresses from client_addresses source table
     * 
     * @param int $clientId
     * @return array
     */
    private function getAddressesFromSource($clientId)
    {
        $addresses = [];
        
        $clientAddresses = DB::table('client_addresses')
            ->where('client_id', $clientId)
            ->orderBy('id')
            ->get();

        foreach ($clientAddresses as $address) {
            $addresses[] = [
                'id' => $address->id,
                'search_address' => $address->address ?? null,
                'address_line_1' => $address->address_line_1 ?? null,
                'address_line_2' => $address->address_line_2 ?? null,
                'suburb' => $address->suburb ?? null,
                'state' => $address->state ?? null,
                'postcode' => $address->zip ?? null,
                'country' => $address->country ?? null,
                'regional_code' => $address->regional_code ?? null,
                'start_date' => $address->start_date ? $this->formatDate($address->start_date) : null,
                'end_date' => $address->end_date ? $this->formatDate($address->end_date) : null,
                'is_current' => ($address->is_current == 1) ? true : false,
                'action' => null, // Records from source table have action: null
            ];
        }

        return $addresses;
    }

    /**
     * Get travels data - merge audit table (if updated) with client_travel_informations table
     * Handles all cases based on action types:
     * Case 1: No audit records → show all from client_travel_informations table
     * Case 2: Action = 'update' → overwrite client_travel_informations records based on meta_type column
     * Case 3: Action = 'create' → show from audit table
     * Case 4: Action = 'delete' → don't show from audit table AND exclude related record with same ID from client_travel_informations table
     * Case 5: Default → show all from client_travel_informations table
     * 
     * @param int $clientId
     * @return array
     */
    private function getTravelsData($clientId)
    {
        // Get all travels from source table
        $sourceTravels = $this->getTravelsFromSource($clientId);
        
        // Get all travels from audit table with action information
        $auditData = $this->getTravelsFromAudit($clientId);
        $auditTravels = $auditData['travels'];
        $actionMap = $auditData['actions'];
        
        // Case 1: If no audit records, return all source travels
        if (empty($auditTravels) && empty($actionMap)) {
            return $sourceTravels;
        }
        
        // Create a map of audit travels by ID and meta_type for quick lookup
        $auditTravelsByIdMap = [];
        $auditTravelsByMetaTypeMap = [];
        foreach ($auditTravels as $auditTravel) {
            $travelId = $auditTravel['id'] ?? null;
            $action = $auditTravel['action'] ?? 'update';
            
            if ($travelId !== null) {
                $auditTravelsByIdMap[$travelId] = $auditTravel;
            }
            
            // Also map by meta_type (which is stored in the travel ID)
            if ($travelId !== null) {
                $auditTravelsByMetaTypeMap[$travelId] = [
                    'travel' => $auditTravel,
                    'action' => $action
                ];
            }
        }
        
        // Build final result array
        $mergedTravels = [];
        $processedIds = [];
        $deletedIds = []; // Track IDs that should be excluded (action='delete')
        
        // First, identify all deleted travels (Case 4) from actionMap
        foreach ($actionMap as $metaType => $action) {
            if ($action === 'delete') {
                $deletedIds[] = is_numeric($metaType) ? (int) $metaType : $metaType;
            }
        }
        
        // Also identify deleted travels from audit travels array itself (Case 4)
        foreach ($auditTravels as $auditTravel) {
            $travelId = $auditTravel['id'] ?? null;
            $action = $auditTravel['action'] ?? 'update';
            
            if ($action === 'delete' && $travelId !== null) {
                $deletedId = is_numeric($travelId) ? (int) $travelId : $travelId;
                if (!in_array($deletedId, $deletedIds)) {
                    $deletedIds[] = $deletedId;
                }
            }
        }
        
        // Process source travels
        foreach ($sourceTravels as $sourceTravel) {
            $travelId = $sourceTravel['id'];
            
            // Case 4: Skip if this travel is deleted (exclude from both audit and source table)
            if (in_array($travelId, $deletedIds)) {
                continue;
            }
            
            // Case 2: Check if there's an audit entry with action='update' for this ID
            if (isset($auditTravelsByMetaTypeMap[$travelId])) {
                $auditInfo = $auditTravelsByMetaTypeMap[$travelId];
                // Case 2: Overwrite with audit data if action is 'update' (and not 'delete')
                if ($auditInfo['action'] === 'update') {
                    $mergedTravels[] = $auditInfo['travel'];
                    $processedIds[] = $travelId;
                    continue;
                }
                // If action is 'delete', it's already handled above (Case 4)
            }
            
            // Case 5: Default - use source data (no update action found)
            $mergedTravels[] = $sourceTravel;
            $processedIds[] = $travelId;
        }
        
        // Case 3: Add audit travels with action='create' (new travels created via API)
        // But exclude if they have action='delete' (Case 4)
        foreach ($auditTravels as $auditTravel) {
            $travelId = $auditTravel['id'] ?? null;
            $action = $auditTravel['action'] ?? 'update';
            
            // Case 4: Skip if this travel is deleted
            if ($action === 'delete' || ($travelId !== null && in_array($travelId, $deletedIds))) {
                continue;
            }
            
            // Case 3: Add if action is 'create' and not already processed
            if ($action === 'create' && !in_array($travelId, $processedIds)) {
                $mergedTravels[] = $auditTravel;
                $processedIds[] = $travelId;
            }
        }
        
        // Case 6: Add audit travels with action='update' that weren't processed in first loop
        // These are travels that exist in audit table but don't exist in source table
        foreach ($auditTravels as $auditTravel) {
            $travelId = $auditTravel['id'] ?? null;
            $action = $auditTravel['action'] ?? 'update';
            
            // Case 4: Skip if this travel is deleted
            if ($action === 'delete' || ($travelId !== null && in_array($travelId, $deletedIds))) {
                continue;
            }
            
            // Case 6: Add if action is 'update' and not already processed (not in source table)
            if ($action === 'update' && !in_array($travelId, $processedIds)) {
                $mergedTravels[] = $auditTravel;
                $processedIds[] = $travelId;
            }
        }
        
        return $mergedTravels;
    }

    /**
     * Get travels from audit table with action information
     * 
     * @param int $clientId
     * @return array Returns array with 'travels' and 'actions' (map of meta_type => action)
     */
    private function getTravelsFromAudit($clientId)
    {
        // Get all travel-related audit entries
        $auditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
            ->whereIn('meta_key', ['travel', 'travel_country_visited', 'travel_arrival_date', 'travel_departure_date', 'travel_purpose'])
            ->get();

        // Group by meta_order and meta_key, taking the latest new_value for each combination
        $travelData = [];
        $latestEntries = []; // Track latest entry for each meta_order + meta_key combination
        $actionMap = []; // Map meta_type to action (for each meta_order)

        foreach ($auditEntries as $entry) {
            $order = $entry->meta_order;
            $key = $entry->meta_key;
            $processedKey = $order . '_' . $key;
            
            // Store the latest entry for this combination
            if (!isset($latestEntries[$processedKey])) {
                $latestEntries[$processedKey] = $entry;
            } else {
                // Compare updated_at to keep the latest
                if (strtotime($entry->updated_at) > strtotime($latestEntries[$processedKey]->updated_at)) {
                    $latestEntries[$processedKey] = $entry;
                }
            }
        }

        // First pass: Process travel entries to get IDs and actions
        foreach ($latestEntries as $processedKey => $entry) {
            $order = $entry->meta_order;
            $key = $entry->meta_key;
            
            // Only process 'travel_country_visited' entries to get ID and action
            if ($key === 'travel_country_visited' && !empty($entry->meta_type)) {
                $metaType = is_numeric($entry->meta_type) ? (int) $entry->meta_type : $entry->meta_type;
                $action = $entry->action ?? 'update';
                $actionMap[$metaType] = $action;
            }
        }

        // Second pass: Process all entries to build travel data
        foreach ($latestEntries as $processedKey => $entry) {
            $order = $entry->meta_order;
            $key = $entry->meta_key;
            
            if (!isset($travelData[$order])) {
                // Get the original record ID from meta_type when meta_key is 'travel_country_visited'
                $originalId = null;
                $action = 'update'; // Default action
                
                if ($key === 'travel_country_visited' && !empty($entry->meta_type)) {
                    $originalId = is_numeric($entry->meta_type) ? (int) $entry->meta_type : null;
                    $action = $entry->action ?? 'update';
                } else {
                    // Try to find the travel_country_visited entry for this meta_order to get ID and action
                    $travelCountryKey = $order . '_travel_country_visited';
                    if (isset($latestEntries[$travelCountryKey])) {
                        $travelCountryEntry = $latestEntries[$travelCountryKey];
                        if (!empty($travelCountryEntry->meta_type)) {
                            $originalId = is_numeric($travelCountryEntry->meta_type) ? (int) $travelCountryEntry->meta_type : null;
                            $action = $travelCountryEntry->action ?? 'update';
                        }
                    }
                }
                
                $travelData[$order] = [
                    'id' => $originalId,
                    'country_visited' => null,
                    'arrival_date' => null,
                    'departure_date' => null,
                    'purpose' => null,
                    'action' => $action,
                ];
            }
            
            switch ($key) {
                case 'travel':
                    // Travel marker - just confirms this travel entry exists
                    break;
                case 'travel_country_visited':
                    $travelData[$order]['country_visited'] = $entry->new_value;
                    // Also set ID and action if not already set
                    if ($travelData[$order]['id'] === null && !empty($entry->meta_type)) {
                        $travelData[$order]['id'] = is_numeric($entry->meta_type) ? (int) $entry->meta_type : null;
                    }
                    if (!empty($entry->action)) {
                        $travelData[$order]['action'] = $entry->action;
                    }
                    break;
                case 'travel_arrival_date':
                    $travelData[$order]['arrival_date'] = $entry->new_value ? $this->formatDate($entry->new_value) : null;
                    break;
                case 'travel_departure_date':
                    $travelData[$order]['departure_date'] = $entry->new_value ? $this->formatDate($entry->new_value) : null;
                    break;
                case 'travel_purpose':
                    $travelData[$order]['purpose'] = $entry->new_value;
                    break;
            }
        }

        // Convert to indexed array and filter out entries without at least country_visited; include meta_order for approve/reject
        $travels = [];
        foreach ($travelData as $order => $travel) {
            // Include travel if it has country_visited
            if (!empty($travel['country_visited'])) {
                $travel['meta_order'] = $order;
                $travels[] = $travel;
            }
        }

        return [
            'travels' => $travels,
            'actions' => $actionMap
        ];
    }

    /**
     * Public entry point for CRM client portal: returns travels merged from
     * client_travel_informations and clientportal_details_audit.
     *
     * @param int $clientId
     * @return array
     */
    public function getMergedTravelsForClient($clientId)
    {
        return $this->getTravelsData($clientId);
    }

    /**
     * Get travels from client_travel_informations source table
     * 
     * @param int $clientId
     * @return array
     */
    private function getTravelsFromSource($clientId)
    {
        $travels = [];
        
        $clientTravels = DB::table('client_travel_informations')
            ->where('client_id', $clientId)
            ->orderByRaw('travel_arrival_date DESC NULLS LAST, created_at DESC')
            ->get();

        foreach ($clientTravels as $travel) {
            $travels[] = [
                'id' => $travel->id,
                'country_visited' => $travel->travel_country_visited ?? null,
                'arrival_date' => $travel->travel_arrival_date ? $this->formatDate($travel->travel_arrival_date) : null,
                'departure_date' => $travel->travel_departure_date ? $this->formatDate($travel->travel_departure_date) : null,
                'purpose' => $travel->travel_purpose ?? null,
                'action' => null, // Records from source table have action: null
            ];
        }

        return $travels;
    }

    /**
     * Get qualifications data - merge audit table (if updated) with client_qualifications table
     * Handles all cases based on action types:
     * Case 1: No audit records → show all from client_qualifications table
     * Case 2: Action = 'update' → overwrite client_qualifications records based on meta_type column
     * Case 3: Action = 'create' → show from audit table
     * Case 4: Action = 'delete' → don't show from audit table AND exclude related record with same ID from client_qualifications table
     * Case 5: Default → show all from client_qualifications table
     * 
     * @param int $clientId
     * @return array
     */
    private function getQualificationsData($clientId)
    {
        // Get all qualifications from source table
        $sourceQualifications = $this->getQualificationsFromSource($clientId);
        
        // Get all qualifications from audit table with action information
        $auditData = $this->getQualificationsFromAudit($clientId);
        $auditQualifications = $auditData['qualifications'];
        $actionMap = $auditData['actions'];
        
        // Case 1: If no audit records, return all source qualifications
        if (empty($auditQualifications) && empty($actionMap)) {
            return $sourceQualifications;
        }
        
        // Create a map of audit qualifications by ID and meta_type for quick lookup
        $auditQualificationsByIdMap = [];
        $auditQualificationsByMetaTypeMap = [];
        foreach ($auditQualifications as $auditQualification) {
            $qualificationId = $auditQualification['id'] ?? null;
            $action = $auditQualification['action'] ?? 'update';
            
            if ($qualificationId !== null) {
                $auditQualificationsByIdMap[$qualificationId] = $auditQualification;
            }
            
            // Also map by meta_type (which is stored in the qualification ID)
            if ($qualificationId !== null) {
                $auditQualificationsByMetaTypeMap[$qualificationId] = [
                    'qualification' => $auditQualification,
                    'action' => $action
                ];
            }
        }
        
        // Build final result array
        $mergedQualifications = [];
        $processedIds = [];
        $deletedIds = []; // Track IDs that should be excluded (action='delete')
        
        // First, identify all deleted qualifications (Case 4) from actionMap
        foreach ($actionMap as $metaType => $action) {
            if ($action === 'delete') {
                $deletedIds[] = is_numeric($metaType) ? (int) $metaType : $metaType;
            }
        }
        
        // Also identify deleted qualifications from audit qualifications array itself (Case 4)
        foreach ($auditQualifications as $auditQualification) {
            $qualificationId = $auditQualification['id'] ?? null;
            $action = $auditQualification['action'] ?? 'update';
            
            if ($action === 'delete' && $qualificationId !== null) {
                $deletedId = is_numeric($qualificationId) ? (int) $qualificationId : $qualificationId;
                if (!in_array($deletedId, $deletedIds)) {
                    $deletedIds[] = $deletedId;
                }
            }
        }
        
        // Process source qualifications
        foreach ($sourceQualifications as $sourceQualification) {
            $qualificationId = $sourceQualification['id'];
            
            // Case 4: Skip if this qualification is deleted (exclude from both audit and source table)
            if (in_array($qualificationId, $deletedIds)) {
                continue;
            }
            
            // Case 2: Check if there's an audit entry with action='update' for this ID
            if (isset($auditQualificationsByMetaTypeMap[$qualificationId])) {
                $auditInfo = $auditQualificationsByMetaTypeMap[$qualificationId];
                // Case 2: Overwrite with audit data if action is 'update' (and not 'delete')
                if ($auditInfo['action'] === 'update') {
                    $mergedQualifications[] = $auditInfo['qualification'];
                    $processedIds[] = $qualificationId;
                    continue;
                }
                // If action is 'delete', it's already handled above (Case 4)
            }
            
            // Case 5: Default - use source data (no update action found)
            $mergedQualifications[] = $sourceQualification;
            $processedIds[] = $qualificationId;
        }
        
        // Case 3: Add audit qualifications with action='create' (new qualifications created via API)
        // But exclude if they have action='delete' (Case 4)
        foreach ($auditQualifications as $auditQualification) {
            $qualificationId = $auditQualification['id'] ?? null;
            $action = $auditQualification['action'] ?? 'update';
            
            // Case 4: Skip if this qualification is deleted
            if ($action === 'delete' || ($qualificationId !== null && in_array($qualificationId, $deletedIds))) {
                continue;
            }
            
            // Case 3: Add if action is 'create' and not already processed
            if ($action === 'create' && !in_array($qualificationId, $processedIds)) {
                $mergedQualifications[] = $auditQualification;
                $processedIds[] = $qualificationId;
            }
        }
        
        // Case 6: Add audit qualifications with action='update' that weren't processed in first loop
        // These are qualifications that exist in audit table but don't exist in source table
        foreach ($auditQualifications as $auditQualification) {
            $qualificationId = $auditQualification['id'] ?? null;
            $action = $auditQualification['action'] ?? 'update';
            
            // Case 4: Skip if this qualification is deleted
            if ($action === 'delete' || ($qualificationId !== null && in_array($qualificationId, $deletedIds))) {
                continue;
            }
            
            // Case 6: Add if action is 'update' and not already processed (not in source table)
            if ($action === 'update' && !in_array($qualificationId, $processedIds)) {
                $mergedQualifications[] = $auditQualification;
                $processedIds[] = $qualificationId;
            }
        }
        
        return $mergedQualifications;
    }

    /**
     * Get qualifications from audit table with action information
     * 
     * @param int $clientId
     * @return array Returns array with 'qualifications' and 'actions' (map of meta_type => action)
     */
    private function getQualificationsFromAudit($clientId)
    {
        // Get all qualification-related audit entries
        $auditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
            ->whereIn('meta_key', ['qualification', 'qualification_level', 'qualification_name', 'qualification_college_name', 'qualification_campus', 'qualification_country', 'qualification_state', 'qualification_start_date', 'qualification_finish_date', 'qualification_relevant', 'qualification_specialist_education', 'qualification_stem', 'qualification_regional_study'])
            ->get();

        // Group by meta_order and meta_key, taking the latest new_value for each combination
        $qualificationData = [];
        $latestEntries = []; // Track latest entry for each meta_order + meta_key combination
        $actionMap = []; // Map meta_type to action (for each meta_order)

        foreach ($auditEntries as $entry) {
            $order = $entry->meta_order;
            $key = $entry->meta_key;
            $processedKey = $order . '_' . $key;
            
            // Store the latest entry for this combination
            if (!isset($latestEntries[$processedKey])) {
                $latestEntries[$processedKey] = $entry;
            } else {
                // Compare updated_at to keep the latest
                if (strtotime($entry->updated_at) > strtotime($latestEntries[$processedKey]->updated_at)) {
                    $latestEntries[$processedKey] = $entry;
                }
            }
        }

        // First pass: Process qualification entries to get IDs and actions
        foreach ($latestEntries as $processedKey => $entry) {
            $order = $entry->meta_order;
            $key = $entry->meta_key;
            
            // Only process 'qualification_name' entries to get ID and action
            if ($key === 'qualification_name' && !empty($entry->meta_type)) {
                $metaType = is_numeric($entry->meta_type) ? (int) $entry->meta_type : $entry->meta_type;
                $action = $entry->action ?? 'update';
                $actionMap[$metaType] = $action;
            }
        }

        // Second pass: Process all entries to build qualification data
        foreach ($latestEntries as $processedKey => $entry) {
            $order = $entry->meta_order;
            $key = $entry->meta_key;
            
            if (!isset($qualificationData[$order])) {
                // Get the original record ID from meta_type when meta_key is 'qualification_name'
                $originalId = null;
                $action = 'update'; // Default action
                
                if ($key === 'qualification_name' && !empty($entry->meta_type)) {
                    $originalId = is_numeric($entry->meta_type) ? (int) $entry->meta_type : null;
                    $action = $entry->action ?? 'update';
                } else {
                    // Try to find the qualification_name entry for this meta_order to get ID and action
                    $qualificationNameKey = $order . '_qualification_name';
                    if (isset($latestEntries[$qualificationNameKey])) {
                        $qualificationNameEntry = $latestEntries[$qualificationNameKey];
                        if (!empty($qualificationNameEntry->meta_type)) {
                            $originalId = is_numeric($qualificationNameEntry->meta_type) ? (int) $qualificationNameEntry->meta_type : null;
                            $action = $qualificationNameEntry->action ?? 'update';
                        }
                    }
                }
                
                $qualificationData[$order] = [
                    'id' => $originalId,
                    'level' => null,
                    'name' => null,
                    'college_name' => null,
                    'campus' => null,
                    'country' => null,
                    'state' => null,
                    'start_date' => null,
                    'finish_date' => null,
                    'relevant_qualification' => false,
                    'action' => $action,
                ];
            }
            
            switch ($key) {
                case 'qualification':
                    // Qualification marker - just confirms this qualification entry exists
                    break;
                case 'qualification_level':
                    $qualificationData[$order]['level'] = $entry->new_value;
                    break;
                case 'qualification_name':
                    $qualificationData[$order]['name'] = $entry->new_value;
                    // Also set ID and action if not already set
                    if ($qualificationData[$order]['id'] === null && !empty($entry->meta_type)) {
                        $qualificationData[$order]['id'] = is_numeric($entry->meta_type) ? (int) $entry->meta_type : null;
                    }
                    if (!empty($entry->action)) {
                        $qualificationData[$order]['action'] = $entry->action;
                    }
                    break;
                case 'qualification_college_name':
                    $qualificationData[$order]['college_name'] = $entry->new_value;
                    break;
                case 'qualification_campus':
                    $qualificationData[$order]['campus'] = $entry->new_value;
                    break;
                case 'qualification_country':
                    $qualificationData[$order]['country'] = $entry->new_value;
                    break;
                case 'qualification_state':
                    $qualificationData[$order]['state'] = $entry->new_value;
                    break;
                case 'qualification_start_date':
                    $qualificationData[$order]['start_date'] = $entry->new_value ? $this->formatDate($entry->new_value) : null;
                    break;
                case 'qualification_finish_date':
                    $qualificationData[$order]['finish_date'] = $entry->new_value ? $this->formatDate($entry->new_value) : null;
                    break;
                case 'qualification_relevant':
                    $qualificationData[$order]['relevant_qualification'] = ($entry->new_value == '1' || $entry->new_value == 1) ? true : false;
                    break;
            }
        }

        // Convert to indexed array and filter out entries without at least level or name; add meta_order for approve/reject
        $qualifications = [];
        foreach ($qualificationData as $order => $qualification) {
            // Include qualification if it has at least level or name
            if (!empty($qualification['level']) || !empty($qualification['name'])) {
                $qualification['meta_order'] = $order;
                $qualifications[] = $qualification;
            }
        }

        return [
            'qualifications' => $qualifications,
            'actions' => $actionMap
        ];
    }

    /**
     * Get qualifications from client_qualifications source table
     * 
     * @param int $clientId
     * @return array
     */
    private function getQualificationsFromSource($clientId)
    {
        $qualifications = [];
        
        $clientQualifications = DB::table('client_qualifications')
            ->where('client_id', $clientId)
            ->orderBy('id')
            ->get();

        foreach ($clientQualifications as $qualification) {
            $qualifications[] = [
                'id' => $qualification->id,
                'level' => $qualification->level ?? null,
                'name' => $qualification->name ?? null,
                'college_name' => $qualification->qual_college_name ?? null,
                'campus' => $qualification->qual_campus ?? null,
                'country' => $qualification->country ?? null,
                'state' => $qualification->qual_state ?? null,
                'start_date' => $qualification->start_date ? $this->formatDate($qualification->start_date) : null,
                'finish_date' => $qualification->finish_date ? $this->formatDate($qualification->finish_date) : null,
                'relevant_qualification' => ($qualification->relevant_qualification == 1) ? true : false,
                'action' => null, // Records from source table have action: null
            ];
        }

        return $qualifications;
    }

    /**
     * Get experiences data - merge audit table (if updated) with client_experiences table
     * Handles all cases based on action types:
     * Case 1: No audit records → show all from client_experiences table
     * Case 2: Action = 'update' → overwrite client_experiences records based on meta_type column
     * Case 3: Action = 'create' → show from audit table
     * Case 4: Action = 'delete' → don't show from audit table AND exclude related record with same ID from client_experiences table
     * Case 5: Default → show all from client_experiences table
     * 
     * @param int $clientId
     * @return array
     */
    private function getExperiencesData($clientId)
    {
        // Get all experiences from source table
        $sourceExperiences = $this->getExperiencesFromSource($clientId);
        
        // Get all experiences from audit table with action information
        $auditData = $this->getExperiencesFromAudit($clientId);
        $auditExperiences = $auditData['experiences'];
        $actionMap = $auditData['actions'];
        
        // Case 1: If no audit records, return all source experiences
        if (empty($auditExperiences) && empty($actionMap)) {
            return $sourceExperiences;
        }
        
        // Create a map of audit experiences by ID and meta_type for quick lookup
        $auditExperiencesByIdMap = [];
        $auditExperiencesByMetaTypeMap = [];
        foreach ($auditExperiences as $auditExperience) {
            $experienceId = $auditExperience['id'] ?? null;
            $action = $auditExperience['action'] ?? 'update';
            
            if ($experienceId !== null) {
                $auditExperiencesByIdMap[$experienceId] = $auditExperience;
            }
            
            // Also map by meta_type (which is stored in the experience ID)
            if ($experienceId !== null) {
                $auditExperiencesByMetaTypeMap[$experienceId] = [
                    'experience' => $auditExperience,
                    'action' => $action
                ];
            }
        }
        
        // Build final result array
        $mergedExperiences = [];
        $processedIds = [];
        $deletedIds = []; // Track IDs that should be excluded (action='delete')
        
        // First, identify all deleted experiences (Case 4) from actionMap
        foreach ($actionMap as $metaType => $action) {
            if ($action === 'delete') {
                $deletedIds[] = is_numeric($metaType) ? (int) $metaType : $metaType;
            }
        }
        
        // Also identify deleted experiences from audit experiences array itself (Case 4)
        foreach ($auditExperiences as $auditExperience) {
            $experienceId = $auditExperience['id'] ?? null;
            $action = $auditExperience['action'] ?? 'update';
            
            if ($action === 'delete' && $experienceId !== null) {
                $deletedId = is_numeric($experienceId) ? (int) $experienceId : $experienceId;
                if (!in_array($deletedId, $deletedIds)) {
                    $deletedIds[] = $deletedId;
                }
            }
        }
        
        // Process source experiences
        foreach ($sourceExperiences as $sourceExperience) {
            $experienceId = $sourceExperience['id'];
            
            // Case 4: Skip if this experience is deleted (exclude from both audit and source table)
            if (in_array($experienceId, $deletedIds)) {
                continue;
            }
            
            // Case 2: Check if there's an audit entry with action='update' for this ID
            if (isset($auditExperiencesByMetaTypeMap[$experienceId])) {
                $auditInfo = $auditExperiencesByMetaTypeMap[$experienceId];
                // Case 2: Overwrite with audit data if action is 'update' (and not 'delete')
                if ($auditInfo['action'] === 'update') {
                    $mergedExperiences[] = $auditInfo['experience'];
                    $processedIds[] = $experienceId;
                    continue;
                }
                // If action is 'delete', it's already handled above (Case 4)
            }
            
            // Case 5: Default - use source data (no update action found)
            $mergedExperiences[] = $sourceExperience;
            $processedIds[] = $experienceId;
        }
        
        // Case 3: Add audit experiences with action='create' (new experiences created via API)
        // But exclude if they have action='delete' (Case 4)
        foreach ($auditExperiences as $auditExperience) {
            $experienceId = $auditExperience['id'] ?? null;
            $action = $auditExperience['action'] ?? 'update';
            
            // Case 4: Skip if this experience is deleted
            if ($action === 'delete' || ($experienceId !== null && in_array($experienceId, $deletedIds))) {
                continue;
            }
            
            // Case 3: Add if action is 'create' and not already processed
            if ($action === 'create' && !in_array($experienceId, $processedIds)) {
                $mergedExperiences[] = $auditExperience;
                $processedIds[] = $experienceId;
            }
        }
        
        // Case 6: Add audit experiences with action='update' that weren't processed in first loop
        // These are experiences that exist in audit table but don't exist in source table
        foreach ($auditExperiences as $auditExperience) {
            $experienceId = $auditExperience['id'] ?? null;
            $action = $auditExperience['action'] ?? 'update';
            
            // Case 4: Skip if this experience is deleted
            if ($action === 'delete' || ($experienceId !== null && in_array($experienceId, $deletedIds))) {
                continue;
            }
            
            // Case 6: Add if action is 'update' and not already processed (not in source table)
            if ($action === 'update' && !in_array($experienceId, $processedIds)) {
                $mergedExperiences[] = $auditExperience;
                $processedIds[] = $experienceId;
            }
        }
        
        return $mergedExperiences;
    }

    /**
     * Get experiences from audit table with action information
     * 
     * @param int $clientId
     * @return array Returns array with 'experiences' and 'actions' (map of meta_type => action)
     */
    private function getExperiencesFromAudit($clientId)
    {
        // Get all experience-related audit entries
        $auditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
            ->whereIn('meta_key', ['experience', 'experience_job_title', 'experience_job_code', 'experience_country', 'experience_start_date', 'experience_finish_date', 'experience_relevant', 'experience_employer_name', 'experience_state', 'experience_job_type', 'experience_fte_multiplier'])
            ->get();

        // Group by meta_order and meta_key, taking the latest new_value for each combination
        $experienceData = [];
        $latestEntries = []; // Track latest entry for each meta_order + meta_key combination
        $actionMap = []; // Map meta_type to action (for each meta_order)

        foreach ($auditEntries as $entry) {
            $order = $entry->meta_order;
            $key = $entry->meta_key;
            $processedKey = $order . '_' . $key;
            
            // Store the latest entry for this combination
            if (!isset($latestEntries[$processedKey])) {
                $latestEntries[$processedKey] = $entry;
            } else {
                // Compare updated_at to keep the latest
                if (strtotime($entry->updated_at) > strtotime($latestEntries[$processedKey]->updated_at)) {
                    $latestEntries[$processedKey] = $entry;
                }
            }
        }

        // First pass: Process experience entries to get IDs and actions
        foreach ($latestEntries as $processedKey => $entry) {
            $order = $entry->meta_order;
            $key = $entry->meta_key;
            
            // Only process 'experience_job_title' entries to get ID and action
            if ($key === 'experience_job_title' && !empty($entry->meta_type)) {
                $metaType = is_numeric($entry->meta_type) ? (int) $entry->meta_type : $entry->meta_type;
                $action = $entry->action ?? 'update';
                $actionMap[$metaType] = $action;
            }
        }

        // Second pass: Process all entries to build experience data
        foreach ($latestEntries as $processedKey => $entry) {
            $order = $entry->meta_order;
            $key = $entry->meta_key;
            
            if (!isset($experienceData[$order])) {
                // Get the original record ID from meta_type when meta_key is 'experience_job_title'
                $originalId = null;
                $action = 'update'; // Default action
                
                if ($key === 'experience_job_title' && !empty($entry->meta_type)) {
                    $originalId = is_numeric($entry->meta_type) ? (int) $entry->meta_type : null;
                    $action = $entry->action ?? 'update';
                } else {
                    // Try to find the experience_job_title entry for this meta_order to get ID and action
                    $jobTitleKey = $order . '_experience_job_title';
                    if (isset($latestEntries[$jobTitleKey])) {
                        $jobTitleEntry = $latestEntries[$jobTitleKey];
                        if (!empty($jobTitleEntry->meta_type)) {
                            $originalId = is_numeric($jobTitleEntry->meta_type) ? (int) $jobTitleEntry->meta_type : null;
                            $action = $jobTitleEntry->action ?? 'update';
                        }
                    }
                }
                
                $experienceData[$order] = [
                    'id' => $originalId,
                    'job_title' => null,
                    'job_code' => null,
                    'country' => null,
                    'start_date' => null,
                    'finish_date' => null,
                    'relevant_experience' => false,
                    'employer_name' => null,
                    'state' => null,
                    'job_type' => null,
                    'fte_multiplier' => null,
                    'action' => $action,
                ];
            }
            
            switch ($key) {
                case 'experience':
                    // Experience marker - just confirms this experience entry exists
                    break;
                case 'experience_job_title':
                    $experienceData[$order]['job_title'] = $entry->new_value;
                    // Also set ID and action if not already set
                    if ($experienceData[$order]['id'] === null && !empty($entry->meta_type)) {
                        $experienceData[$order]['id'] = is_numeric($entry->meta_type) ? (int) $entry->meta_type : null;
                    }
                    if (!empty($entry->action)) {
                        $experienceData[$order]['action'] = $entry->action;
                    }
                    break;
                case 'experience_job_code':
                    $experienceData[$order]['job_code'] = $entry->new_value;
                    break;
                case 'experience_country':
                    $experienceData[$order]['country'] = $entry->new_value;
                    break;
                case 'experience_start_date':
                    $experienceData[$order]['start_date'] = $entry->new_value ? $this->formatDate($entry->new_value) : null;
                    break;
                case 'experience_finish_date':
                    $experienceData[$order]['finish_date'] = $entry->new_value ? $this->formatDate($entry->new_value) : null;
                    break;
                case 'experience_relevant':
                    $experienceData[$order]['relevant_experience'] = ($entry->new_value == '1' || $entry->new_value == 1) ? true : false;
                    break;
                case 'experience_employer_name':
                    $experienceData[$order]['employer_name'] = $entry->new_value;
                    break;
                case 'experience_state':
                    $experienceData[$order]['state'] = $entry->new_value;
                    break;
                case 'experience_job_type':
                    $experienceData[$order]['job_type'] = $entry->new_value;
                    break;
                case 'experience_fte_multiplier':
                    $experienceData[$order]['fte_multiplier'] = $entry->new_value ? (float) $entry->new_value : null;
                    break;
            }
        }

        // Convert to indexed array and filter out entries without at least job_title; add meta_order for approve/reject
        $experiences = [];
        foreach ($experienceData as $order => $experience) {
            // Include experience if it has job_title
            if (!empty($experience['job_title'])) {
                $experience['meta_order'] = $order;
                $experiences[] = $experience;
            }
        }

        return [
            'experiences' => $experiences,
            'actions' => $actionMap
        ];
    }

    /**
     * Get experiences from client_experiences source table
     * 
     * @param int $clientId
     * @return array
     */
    private function getExperiencesFromSource($clientId)
    {
        $experiences = [];
        
        $clientExperiences = DB::table('client_experiences')
            ->where('client_id', $clientId)
            ->orderBy('id')
            ->get();

        foreach ($clientExperiences as $experience) {
            $experiences[] = [
                'id' => $experience->id,
                'job_title' => $experience->job_title ?? null,
                'job_code' => $experience->job_code ?? null,
                'country' => $experience->job_country ?? null,
                'start_date' => $experience->job_start_date ? $this->formatDate($experience->job_start_date) : null,
                'finish_date' => $experience->job_finish_date ? $this->formatDate($experience->job_finish_date) : null,
                'relevant_experience' => ($experience->relevant_experience == 1) ? true : false,
                'employer_name' => $experience->job_emp_name ?? null,
                'state' => $experience->job_state ?? null,
                'job_type' => $experience->job_type ?? null,
                'fte_multiplier' => $experience->fte_multiplier ? (float) $experience->fte_multiplier : null,
                'action' => null, // Records from source table have action: null
            ];
        }

        return $experiences;
    }

    /**
     * Get occupations data - from audit table if updated, otherwise from client_occupations table
     * 
     * @param int $clientId
     * @return array
     */
    /**
     * Get occupations data - merge audit table (if updated/created/deleted) with source table
     * 
     * Implements 5-case logic:
     * Case 1: No audit records → Shows all from source table (with action: null)
     * Case 2: Action = 'update' → Overwrites source records based on meta_type column (with action: "update")
     * Case 3: Action = 'create' → Shows from audit table (newly created via API with generated IDs, with action: "create")
     * Case 4: Action = 'delete' → Excludes from audit table AND excludes related record with same ID from source table
     * Case 5: Default → Shows all from source table when no update action found (with action: null)
     * 
     * @param int $clientId
     * @return array
     */
    private function getOccupationsData($clientId)
    {
        // Get all occupations from source table
        $sourceOccupations = $this->getOccupationsFromSource($clientId);
        
        // Get all occupations from audit table with action information
        $auditData = $this->getOccupationsFromAudit($clientId);
        $auditOccupations = $auditData['occupations'];
        $actionMap = $auditData['actions'];
        
        // Case 1: If no audit records, return all source occupations
        if (empty($auditOccupations) && empty($actionMap)) {
            return $sourceOccupations;
        }
        
        // Create a map of audit occupations by ID and meta_type for quick lookup
        $auditOccupationsByIdMap = [];
        $auditOccupationsByMetaTypeMap = [];
        foreach ($auditOccupations as $auditOccupation) {
            $occupationId = $auditOccupation['id'] ?? null;
            $action = $auditOccupation['action'] ?? 'update';
            
            if ($occupationId !== null) {
                $auditOccupationsByIdMap[$occupationId] = $auditOccupation;
            }
            
            // Also map by meta_type (which is stored in the occupation ID)
            if ($occupationId !== null) {
                $auditOccupationsByMetaTypeMap[$occupationId] = [
                    'occupation' => $auditOccupation,
                    'action' => $action
                ];
            }
        }
        
        // Build final result array
        $mergedOccupations = [];
        $processedIds = [];
        $deletedIds = []; // Track IDs that should be excluded (action='delete')
        
        // First, identify all deleted occupations (Case 4) from actionMap
        foreach ($actionMap as $metaType => $action) {
            if ($action === 'delete') {
                $deletedIds[] = is_numeric($metaType) ? (int) $metaType : $metaType;
            }
        }
        
        // Also identify deleted occupations from audit occupations array itself (Case 4)
        foreach ($auditOccupations as $auditOccupation) {
            $occupationId = $auditOccupation['id'] ?? null;
            $action = $auditOccupation['action'] ?? 'update';
            
            if ($action === 'delete' && $occupationId !== null) {
                $deletedId = is_numeric($occupationId) ? (int) $occupationId : $occupationId;
                if (!in_array($deletedId, $deletedIds)) {
                    $deletedIds[] = $deletedId;
                }
            }
        }
        
        // Process source occupations
        foreach ($sourceOccupations as $sourceOccupation) {
            $occupationId = $sourceOccupation['id'];
            
            // Case 4: Skip if this occupation is deleted (exclude from both audit and source table)
            if (in_array($occupationId, $deletedIds)) {
                continue;
            }
            
            // Case 2: Check if there's an audit entry with action='update' for this ID
            if (isset($auditOccupationsByMetaTypeMap[$occupationId])) {
                $auditInfo = $auditOccupationsByMetaTypeMap[$occupationId];
                // Case 2: Overwrite with audit data if action is 'update' (and not 'delete')
                if ($auditInfo['action'] === 'update') {
                    $mergedOccupations[] = $auditInfo['occupation'];
                    $processedIds[] = $occupationId;
                    continue;
                }
                // If action is 'delete', it's already handled above (Case 4)
            }
            
            // Case 5: Default - use source data (no update action found)
            $mergedOccupations[] = $sourceOccupation;
            $processedIds[] = $occupationId;
        }
        
        // Case 3: Add audit occupations with action='create' (new occupations created via API)
        // But exclude if they have action='delete' (Case 4)
        foreach ($auditOccupations as $auditOccupation) {
            $occupationId = $auditOccupation['id'] ?? null;
            $action = $auditOccupation['action'] ?? 'update';
            
            // Case 4: Skip if this occupation is deleted
            if ($action === 'delete' || ($occupationId !== null && in_array($occupationId, $deletedIds))) {
                continue;
            }
            
            // Case 3: Add if action is 'create' and not already processed
            if ($action === 'create' && !in_array($occupationId, $processedIds)) {
                $mergedOccupations[] = $auditOccupation;
                $processedIds[] = $occupationId;
            }
        }
        
        // Case 6: Add audit occupations with action='update' that weren't processed in first loop
        // These are occupations that exist in audit table but don't exist in source table
        foreach ($auditOccupations as $auditOccupation) {
            $occupationId = $auditOccupation['id'] ?? null;
            $action = $auditOccupation['action'] ?? 'update';
            
            // Case 4: Skip if this occupation is deleted
            if ($action === 'delete' || ($occupationId !== null && in_array($occupationId, $deletedIds))) {
                continue;
            }
            
            // Case 6: Add if action is 'update' and not already processed (not in source table)
            if ($action === 'update' && !in_array($occupationId, $processedIds)) {
                $mergedOccupations[] = $auditOccupation;
                $processedIds[] = $occupationId;
            }
        }
        
        return $mergedOccupations;
    }

    /**
     * Get occupations from audit table with action information
     * 
     * @param int $clientId
     * @return array Returns array with 'occupations' and 'actions' (map of meta_type => action)
     */
    private function getOccupationsFromAudit($clientId)
    {
        // Get all occupation-related audit entries
        $auditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
            ->whereIn('meta_key', ['occupation', 'occupation_skill_assessment', 'occupation_nominated', 'occupation_code', 'occupation_assessing_authority', 'occupation_visa_subclass', 'occupation_assessment_date', 'occupation_expiry_date', 'occupation_reference_no', 'occupation_relevant', 'occupation_anzsco_id'])
            ->get();

        // Group by meta_order and meta_key, taking the latest new_value for each combination
        $occupationData = [];
        $latestEntries = []; // Track latest entry for each meta_order + meta_key combination
        $actionMap = []; // Map meta_type to action (for each meta_order)

        foreach ($auditEntries as $entry) {
            $order = $entry->meta_order;
            $key = $entry->meta_key;
            $processedKey = $order . '_' . $key;
            
            // Store the latest entry for this combination
            if (!isset($latestEntries[$processedKey])) {
                $latestEntries[$processedKey] = $entry;
            } else {
                // Compare updated_at to keep the latest
                if (strtotime($entry->updated_at) > strtotime($latestEntries[$processedKey]->updated_at)) {
                    $latestEntries[$processedKey] = $entry;
                }
            }
        }

        // First pass: Process occupation entries to get IDs and actions
        foreach ($latestEntries as $processedKey => $entry) {
            $order = $entry->meta_order;
            $key = $entry->meta_key;
            
            // Only process 'occupation_nominated' entries to get ID and action
            if ($key === 'occupation_nominated' && !empty($entry->meta_type)) {
                $metaType = is_numeric($entry->meta_type) ? (int) $entry->meta_type : $entry->meta_type;
                $action = $entry->action ?? 'update';
                $actionMap[$metaType] = $action;
            }
        }

        // Second pass: Process all entries to build occupation data
        foreach ($latestEntries as $processedKey => $entry) {
            $order = $entry->meta_order;
            $key = $entry->meta_key;
            
            if (!isset($occupationData[$order])) {
                // Get the original record ID from meta_type when meta_key is 'occupation_nominated' or 'occupation_code'
                $originalId = null;
                $action = 'update'; // Default action
                
                if ($key === 'occupation_nominated' && !empty($entry->meta_type)) {
                    $originalId = is_numeric($entry->meta_type) ? (int) $entry->meta_type : null;
                    $action = $entry->action ?? 'update';
                } else {
                    // Try to find the occupation_nominated entry for this meta_order to get ID and action
                    $nominatedKey = $order . '_occupation_nominated';
                    if (isset($latestEntries[$nominatedKey])) {
                        $nominatedEntry = $latestEntries[$nominatedKey];
                        if (!empty($nominatedEntry->meta_type)) {
                            $originalId = is_numeric($nominatedEntry->meta_type) ? (int) $nominatedEntry->meta_type : null;
                            $action = $nominatedEntry->action ?? 'update';
                        }
                    }
                }
                
                $occupationData[$order] = [
                    'id' => $originalId,
                    'skill_assessment' => null,
                    'nominated_occupation' => null,
                    'occupation_code' => null,
                    'assessing_authority' => null,
                    'visa_subclass' => null,
                    'assessment_date' => null,
                    'expiry_date' => null,
                    'reference_no' => null,
                    'relevant_occupation' => false,
                    'anzsco_occupation_id' => null,
                    'action' => $action,
                ];
            }
            
            switch ($key) {
                case 'occupation':
                    // Occupation marker - just confirms this occupation entry exists
                    break;
                case 'occupation_skill_assessment':
                    $occupationData[$order]['skill_assessment'] = $entry->new_value;
                    break;
                case 'occupation_nominated':
                    $occupationData[$order]['nominated_occupation'] = $entry->new_value;
                    // Also set ID and action if not already set
                    if ($occupationData[$order]['id'] === null && !empty($entry->meta_type)) {
                        $occupationData[$order]['id'] = is_numeric($entry->meta_type) ? (int) $entry->meta_type : null;
                    }
                    if (!empty($entry->action)) {
                        $occupationData[$order]['action'] = $entry->action;
                    }
                    break;
                case 'occupation_code':
                    $occupationData[$order]['occupation_code'] = $entry->new_value;
                    break;
                case 'occupation_assessing_authority':
                    $occupationData[$order]['assessing_authority'] = $entry->new_value;
                    break;
                case 'occupation_visa_subclass':
                    $occupationData[$order]['visa_subclass'] = $entry->new_value;
                    break;
                case 'occupation_assessment_date':
                    $occupationData[$order]['assessment_date'] = $entry->new_value ? $this->formatDate($entry->new_value) : null;
                    break;
                case 'occupation_expiry_date':
                    $occupationData[$order]['expiry_date'] = $entry->new_value ? $this->formatDate($entry->new_value) : null;
                    break;
                case 'occupation_reference_no':
                    $occupationData[$order]['reference_no'] = $entry->new_value;
                    break;
                case 'occupation_relevant':
                    $occupationData[$order]['relevant_occupation'] = ($entry->new_value == '1' || $entry->new_value == 1) ? true : false;
                    break;
                case 'occupation_anzsco_id':
                    $occupationData[$order]['anzsco_occupation_id'] = $entry->new_value ? (int) $entry->new_value : null;
                    break;
            }
        }

        // Convert to indexed array and filter out entries without at least nominated_occupation or occupation_code; add meta_order for approve/reject
        $occupations = [];
        foreach ($occupationData as $order => $occupation) {
            // Include occupation if it has at least nominated_occupation or occupation_code
            if (!empty($occupation['nominated_occupation']) || !empty($occupation['occupation_code'])) {
                $occupation['meta_order'] = $order;
                $occupations[] = $occupation;
            }
        }

        return [
            'occupations' => $occupations,
            'actions' => $actionMap
        ];
    }

    /**
     * Get occupations from client_occupations source table
     * 
     * @param int $clientId
     * @return array
     */
    private function getOccupationsFromSource($clientId)
    {
        $occupations = [];
        
        $clientOccupations = DB::table('client_occupations')
            ->where('client_id', $clientId)
            ->orderBy('id')
            ->get();

        foreach ($clientOccupations as $occupation) {
            $occupations[] = [
                'id' => $occupation->id,
                'skill_assessment' => $occupation->skill_assessment ?? null,
                'nominated_occupation' => $occupation->nomi_occupation ?? null,
                'occupation_code' => $occupation->occupation_code ?? null,
                'assessing_authority' => $occupation->list ?? null,
                'visa_subclass' => $occupation->visa_subclass ?? null,
                'assessment_date' => !empty($occupation->dates) ? $this->formatDate($occupation->dates) : null,
                'expiry_date' => !empty($occupation->expiry_dates) ? $this->formatDate($occupation->expiry_dates) : null,
                'reference_no' => $occupation->occ_reference_no ?? null,
                'relevant_occupation' => ($occupation->relevant_occupation == 1) ? true : false,
                'anzsco_occupation_id' => $occupation->anzsco_occupation_id ? (int) $occupation->anzsco_occupation_id : null,
                'action' => null, // Records from source table have action: null
            ];
        }

        return $occupations;
    }

    /**
     * Get test scores data - from audit table if updated, otherwise from client_testscore table
     * 
     * @param int $clientId
     * @return array
     */
    /**
     * Get test scores data - merge audit table (if updated/created/deleted) with source table
     * 
     * Implements 5-case logic:
     * Case 1: No audit records → Shows all from source table (with action: null)
     * Case 2: Action = 'update' → Overwrites source records based on meta_type column (with action: "update")
     * Case 3: Action = 'create' → Shows from audit table (newly created via API with generated IDs, with action: "create")
     * Case 4: Action = 'delete' → Excludes from audit table AND excludes related record with same ID from source table
     * Case 5: Default → Shows all from source table when no update action found (with action: null)
     * 
     * @param int $clientId
     * @return array
     */
    private function getTestScoresData($clientId)
    {
        // Get all test scores from source table
        $sourceTestScores = $this->getTestScoresFromSource($clientId);
        
        // Get all test scores from audit table with action information
        $auditData = $this->getTestScoresFromAudit($clientId);
        $auditTestScores = $auditData['test_scores'];
        $actionMap = $auditData['actions'];
        
        // Case 1: If no audit records, return all source test scores
        if (empty($auditTestScores) && empty($actionMap)) {
            return $sourceTestScores;
        }
        
        // Create a map of audit test scores by ID and meta_type for quick lookup
        $auditTestScoresByIdMap = [];
        $auditTestScoresByMetaTypeMap = [];
        foreach ($auditTestScores as $auditTestScore) {
            $testScoreId = $auditTestScore['id'] ?? null;
            $action = $auditTestScore['action'] ?? 'update';
            
            if ($testScoreId !== null) {
                $auditTestScoresByIdMap[$testScoreId] = $auditTestScore;
            }
            
            // Also map by meta_type (which is stored in the test score ID)
            if ($testScoreId !== null) {
                $auditTestScoresByMetaTypeMap[$testScoreId] = [
                    'test_score' => $auditTestScore,
                    'action' => $action
                ];
            }
        }
        
        // Build final result array
        $mergedTestScores = [];
        $processedIds = [];
        $deletedIds = []; // Track IDs that should be excluded (action='delete')
        
        // First, identify all deleted test scores (Case 4) from actionMap
        foreach ($actionMap as $metaType => $action) {
            if ($action === 'delete') {
                $deletedIds[] = is_numeric($metaType) ? (int) $metaType : $metaType;
            }
        }
        
        // Also identify deleted test scores from audit test scores array itself (Case 4)
        foreach ($auditTestScores as $auditTestScore) {
            $testScoreId = $auditTestScore['id'] ?? null;
            $action = $auditTestScore['action'] ?? 'update';
            
            if ($action === 'delete' && $testScoreId !== null) {
                $deletedId = is_numeric($testScoreId) ? (int) $testScoreId : $testScoreId;
                if (!in_array($deletedId, $deletedIds)) {
                    $deletedIds[] = $deletedId;
                }
            }
        }
        
        // Process source test scores
        foreach ($sourceTestScores as $sourceTestScore) {
            $testScoreId = $sourceTestScore['id'];
            
            // Case 4: Skip if this test score is deleted (exclude from both audit and source table)
            if (in_array($testScoreId, $deletedIds)) {
                continue;
            }
            
            // Case 2: Check if there's an audit entry with action='update' for this ID
            if (isset($auditTestScoresByMetaTypeMap[$testScoreId])) {
                $auditInfo = $auditTestScoresByMetaTypeMap[$testScoreId];
                // Case 2: Overwrite with audit data if action is 'update' (and not 'delete')
                if ($auditInfo['action'] === 'update') {
                    $mergedTestScores[] = $auditInfo['test_score'];
                    $processedIds[] = $testScoreId;
                    continue;
                }
                // If action is 'delete', it's already handled above (Case 4)
            }
            
            // Case 5: Default - use source data (no update action found)
            $mergedTestScores[] = $sourceTestScore;
            $processedIds[] = $testScoreId;
        }
        
        // Case 3: Add audit test scores with action='create' (new test scores created via API)
        // But exclude if they have action='delete' (Case 4)
        foreach ($auditTestScores as $auditTestScore) {
            $testScoreId = $auditTestScore['id'] ?? null;
            $action = $auditTestScore['action'] ?? 'update';
            
            // Case 4: Skip if this test score is deleted
            if ($action === 'delete' || ($testScoreId !== null && in_array($testScoreId, $deletedIds))) {
                continue;
            }
            
            // Case 3: Add if action is 'create' and not already processed
            if ($action === 'create' && !in_array($testScoreId, $processedIds)) {
                $mergedTestScores[] = $auditTestScore;
                $processedIds[] = $testScoreId;
            }
        }
        
        // Case 6: Add audit test scores with action='update' that weren't processed in first loop
        // These are test scores that exist in audit table but don't exist in source table
        foreach ($auditTestScores as $auditTestScore) {
            $testScoreId = $auditTestScore['id'] ?? null;
            $action = $auditTestScore['action'] ?? 'update';
            
            // Case 4: Skip if this test score is deleted
            if ($action === 'delete' || ($testScoreId !== null && in_array($testScoreId, $deletedIds))) {
                continue;
            }
            
            // Case 6: Add if action is 'update' and not already processed (not in source table)
            if ($action === 'update' && !in_array($testScoreId, $processedIds)) {
                $mergedTestScores[] = $auditTestScore;
                $processedIds[] = $testScoreId;
            }
        }
        
        return $mergedTestScores;
    }

    /**
     * Get test scores from audit table with action information
     * 
     * @param int $clientId
     * @return array Returns array with 'test_scores' and 'actions' (map of meta_type => action)
     */
    private function getTestScoresFromAudit($clientId)
    {
        // Get all test score-related audit entries
        $auditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
            ->whereIn('meta_key', ['test_score', 'test_score_test_type', 'test_score_listening', 'test_score_reading', 'test_score_writing', 'test_score_speaking', 'test_score_overall_score', 'test_score_test_date', 'test_score_reference_no', 'test_score_relevant'])
            ->get();

        // Group by meta_order and meta_key, taking the latest new_value for each combination
        $testScoreData = [];
        $latestEntries = []; // Track latest entry for each meta_order + meta_key combination
        $actionMap = []; // Map meta_type to action (for each meta_order)

        foreach ($auditEntries as $entry) {
            $order = $entry->meta_order;
            $key = $entry->meta_key;
            $processedKey = $order . '_' . $key;
            
            // Store the latest entry for this combination
            if (!isset($latestEntries[$processedKey])) {
                $latestEntries[$processedKey] = $entry;
            } else {
                // Compare updated_at to keep the latest
                if (strtotime($entry->updated_at) > strtotime($latestEntries[$processedKey]->updated_at)) {
                    $latestEntries[$processedKey] = $entry;
                }
            }
        }

        // First pass: Process test score entries to get IDs and actions
        foreach ($latestEntries as $processedKey => $entry) {
            $order = $entry->meta_order;
            $key = $entry->meta_key;
            
            // Only process 'test_score_test_type' entries to get ID and action
            if ($key === 'test_score_test_type' && !empty($entry->meta_type)) {
                $metaType = is_numeric($entry->meta_type) ? (int) $entry->meta_type : $entry->meta_type;
                $action = $entry->action ?? 'update';
                $actionMap[$metaType] = $action;
            }
        }

        // Second pass: Process all entries to build test score data
        foreach ($latestEntries as $processedKey => $entry) {
            $order = $entry->meta_order;
            $key = $entry->meta_key;
            
            if (!isset($testScoreData[$order])) {
                // Get the original record ID from meta_type when meta_key is 'test_score_test_type'
                $originalId = null;
                $action = 'update'; // Default action
                
                if ($key === 'test_score_test_type' && !empty($entry->meta_type)) {
                    $originalId = is_numeric($entry->meta_type) ? (int) $entry->meta_type : null;
                    $action = $entry->action ?? 'update';
                } else {
                    // Try to find the test_score_test_type entry for this meta_order to get ID and action
                    $testTypeKey = $order . '_test_score_test_type';
                    if (isset($latestEntries[$testTypeKey])) {
                        $testTypeEntry = $latestEntries[$testTypeKey];
                        if (!empty($testTypeEntry->meta_type)) {
                            $originalId = is_numeric($testTypeEntry->meta_type) ? (int) $testTypeEntry->meta_type : null;
                            $action = $testTypeEntry->action ?? 'update';
                        }
                    }
                }
                
                $testScoreData[$order] = [
                    'id' => $originalId,
                    'test_type' => null,
                    'listening' => null,
                    'reading' => null,
                    'writing' => null,
                    'speaking' => null,
                    'overall_score' => null,
                    'test_date' => null,
                    'reference_no' => null,
                    'relevant_test' => false,
                    'action' => $action,
                ];
            }
            
            switch ($key) {
                case 'test_score':
                    // Test score marker - just confirms this test score entry exists
                    break;
                case 'test_score_test_type':
                    $testScoreData[$order]['test_type'] = $entry->new_value;
                    // Also set ID and action if not already set
                    if ($testScoreData[$order]['id'] === null && !empty($entry->meta_type)) {
                        $testScoreData[$order]['id'] = is_numeric($entry->meta_type) ? (int) $entry->meta_type : null;
                    }
                    if (!empty($entry->action)) {
                        $testScoreData[$order]['action'] = $entry->action;
                    }
                    break;
                case 'test_score_listening':
                    $testScoreData[$order]['listening'] = $entry->new_value ? (float) $entry->new_value : null;
                    break;
                case 'test_score_reading':
                    $testScoreData[$order]['reading'] = $entry->new_value ? (float) $entry->new_value : null;
                    break;
                case 'test_score_writing':
                    $testScoreData[$order]['writing'] = $entry->new_value ? (float) $entry->new_value : null;
                    break;
                case 'test_score_speaking':
                    $testScoreData[$order]['speaking'] = $entry->new_value ? (float) $entry->new_value : null;
                    break;
                case 'test_score_overall_score':
                    $testScoreData[$order]['overall_score'] = $entry->new_value ? (float) $entry->new_value : null;
                    break;
                case 'test_score_test_date':
                    $testScoreData[$order]['test_date'] = $entry->new_value ? $this->formatDate($entry->new_value) : null;
                    break;
                case 'test_score_reference_no':
                    $testScoreData[$order]['reference_no'] = $entry->new_value;
                    break;
                case 'test_score_relevant':
                    $testScoreData[$order]['relevant_test'] = ($entry->new_value == '1' || $entry->new_value == 1) ? true : false;
                    break;
            }
        }

        // Convert to indexed array and filter out entries without at least test_type; add meta_order for approve/reject
        $testScores = [];
        foreach ($testScoreData as $order => $testScore) {
            // Include test score if it has test_type
            if (!empty($testScore['test_type'])) {
                $testScore['meta_order'] = $order;
                $testScores[] = $testScore;
            }
        }

        return [
            'test_scores' => $testScores,
            'actions' => $actionMap
        ];
    }

    /**
     * Get test scores from client_testscore source table
     * 
     * @param int $clientId
     * @return array
     */
    private function getTestScoresFromSource($clientId)
    {
        $testScores = [];
        
        $clientTestScores = DB::table('client_testscore')
            ->where('client_id', $clientId)
            ->orderBy('id')
            ->get();

        foreach ($clientTestScores as $testScore) {
            $testScores[] = [
                'id' => $testScore->id,
                'test_type' => $testScore->test_type ?? null,
                'listening' => $testScore->listening ? (float) $testScore->listening : null,
                'reading' => $testScore->reading ? (float) $testScore->reading : null,
                'writing' => $testScore->writing ? (float) $testScore->writing : null,
                'speaking' => $testScore->speaking ? (float) $testScore->speaking : null,
                'overall_score' => $testScore->overall_score ? (float) $testScore->overall_score : null,
                'test_date' => $testScore->test_date ? $this->formatDate($testScore->test_date) : null,
                'reference_no' => $testScore->test_reference_no ?? null,
                'relevant_test' => ($testScore->relevant_test == 1 || strtolower($testScore->relevant_test ?? '') === 'yes') ? true : false,
                'action' => null, // Records from source table have action: null
            ];
        }

        return $testScores;
    }

    /**
     * Update client passport details - saves to clientportal_details_audit table
     * 
     * This API allows clients to update their passport information.
     * Passports are saved to clientportal_details_audit table with meta_key and meta_order.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateClientPassportDetail(Request $request)
    {
        try {
            // Get authenticated client ID from token
            $admin = $request->user();
            $clientId = (int) $admin->id;

            // Verify the authenticated client (Admin model; Staff use Staff model)
            if (!$admin instanceof Admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. This endpoint is only available for clients.'
                ], 403);
            }

            // Validate the request
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'passports' => 'required|array|min:1',
                'passports.*.id' => 'present|nullable|integer',
                'passports.*.passport_number' => 'required|string|max:255',
                'passports.*.country' => 'nullable|string|max:255',
                'passports.*.issue_date' => 'nullable|date_format:d/m/Y',
                'passports.*.expiry_date' => 'nullable|date_format:d/m/Y',
            ], [
                'passports.required' => 'At least one passport is required.',
                'passports.*.id.present' => 'Passport ID field is required for each passport. Use null for new passports or provide the existing passport ID for updates.',
                'passports.*.id.integer' => 'Passport ID must be an integer or null.',
                'passports.*.passport_number.required' => 'Passport number is required for each passport.',
                'passports.*.issue_date.date_format' => 'Issue date must be in dd/mm/yyyy format.',
                'passports.*.expiry_date.date_format' => 'Expiry date must be in dd/mm/yyyy format.',
            ]);

            // Custom validation: Ensure id field is always present and check date logic
            $validator->after(function ($validator) use ($request) {
                $passports = $request->input('passports', []);
                foreach ($passports as $index => $passport) {
                    // Ensure id field is always present (required field)
                    if (!array_key_exists('id', $passport)) {
                        $validator->errors()->add(
                            "passports.{$index}.id",
                            "Passport ID field is required. Use null for new passports or provide the existing passport ID for updates."
                        );
                    }
                    
                    // Validate date logic: expiry date must be after or equal to issue date
                    if (!empty($passport['issue_date']) && !empty($passport['expiry_date'])) {
                        try {
                            $issueDate = Carbon::createFromFormat('d/m/Y', $passport['issue_date']);
                            $expiryDate = Carbon::createFromFormat('d/m/Y', $passport['expiry_date']);
                            
                            if ($expiryDate->lt($issueDate)) {
                                $validator->errors()->add(
                                    "passports.{$index}.expiry_date",
                                    "Expiry date must be after or equal to issue date."
                                );
                            }
                        } catch (\Exception $e) {
                            // Date format validation already handled above
                        }
                    }
                    // Validate: issue date cannot be a future date (expiry can be future)
                    if (!empty($passport['issue_date'])) {
                        try {
                            $issueDate = Carbon::createFromFormat('d/m/Y', $passport['issue_date']);
                            if ($issueDate->isFuture()) {
                                $validator->errors()->add(
                                    "passports.{$index}.issue_date",
                                    "The document issue date cannot be a future date."
                                );
                            }
                        } catch (\Exception $e) {}
                    }
                }
            });

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $userId = Auth::id() ?? $clientId;
            $passports = $request->input('passports');
            $updatedPassports = [];

            DB::beginTransaction();

            try {
                // Get existing passport IDs from request to identify which ones to update
                $passportIdsToUpdate = [];
                $passportIdToMetaOrderMap = []; // Map passport ID to its meta_order
                
                foreach ($passports as $passportData) {
                    // ID field is required - if it has a value (not null), it's an update; if null, it's a new record
                    if (isset($passportData['id']) && $passportData['id'] !== null && $passportData['id'] !== '') {
                        $passportIdsToUpdate[] = (int) $passportData['id'];
                    }
                }

                // Get the highest existing meta_order BEFORE deleting (to ensure unique values for new passports)
                $maxMetaOrder = ClientPortalDetailAudit::where('client_id', $clientId)
                    ->whereIn('meta_key', ['passport', 'passport_country', 'passport_issue_date', 'passport_expiry_date'])
                    ->max('meta_order') ?? -1;

                // Get meta_order values for existing passports BEFORE deleting (if IDs provided)
                if (!empty($passportIdsToUpdate)) {
                    $existingAuditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
                        ->where('meta_key', 'passport')
                        ->whereIn('meta_type', array_map('strval', $passportIdsToUpdate))
                        ->get();

                    foreach ($existingAuditEntries as $entry) {
                        $pid = (int) $entry->meta_type;
                        if (!isset($passportIdToMetaOrderMap[$pid])) {
                            $passportIdToMetaOrderMap[$pid] = $entry->meta_order;
                        }
                    }

                    // Delete audit entries for specific passports being updated
                    if (!empty($passportIdToMetaOrderMap)) {
                        $ordersToDelete = array_values($passportIdToMetaOrderMap);
                        ClientPortalDetailAudit::where('client_id', $clientId)
                            ->whereIn('meta_key', ['passport', 'passport_country', 'passport_issue_date', 'passport_expiry_date'])
                            ->whereIn('meta_order', $ordersToDelete)
                            ->delete();
                    }
                }
                // Note: If all passports have id: null (new passports), no deletion happens - safe behavior

                // Track which meta_order values are being reused (to avoid conflicts with new passports)
                $usedMetaOrders = array_values($passportIdToMetaOrderMap);

                // Process each passport
                foreach ($passports as $index => $passportData) {
                    // ID field is required: if null, it's a new record; if has value, it's an update
                    $passportId = null;
                    $isNewRecord = false;
                    if (isset($passportData['id']) && $passportData['id'] !== null && $passportData['id'] !== '') {
                        $passportId = (int) $passportData['id'];
                    } else {
                        // Generate timestamp-based 10-digit ID for new record
                        $passportId = $this->generateTimestampBasedId();
                        $isNewRecord = true;
                    }
                    $passportNumber = $passportData['passport_number'] ?? null;
                    $country = $passportData['country'] ?? null;
                    $issueDate = $passportData['issue_date'] ?? null;
                    $expiryDate = $passportData['expiry_date'] ?? null;

                    if (empty($passportNumber)) {
                        continue; // Skip if passport number is empty
                    }

                    // Determine action: 'create' for new records, 'update' for existing ones
                    $action = $isNewRecord ? 'create' : 'update';

                    // Determine meta_order: use existing if updating by ID, otherwise use next available
                    if (!$isNewRecord && isset($passportIdToMetaOrderMap[$passportId])) {
                        // Use existing meta_order for this passport
                        $metaOrder = $passportIdToMetaOrderMap[$passportId];
                    } else {
                        // New passport - use next available meta_order that doesn't conflict with reused values
                        do {
                            $maxMetaOrder++;
                            $metaOrder = $maxMetaOrder;
                        } while (in_array($metaOrder, $usedMetaOrders));
                        // Track this meta_order as used to avoid conflicts with subsequent new passports
                        $usedMetaOrders[] = $metaOrder;
                    }

                    // Convert dates from dd/mm/yyyy to Y-m-d format
                    $issueDateDb = null;
                    if ($issueDate) {
                        try {
                            $date = Carbon::createFromFormat('d/m/Y', $issueDate);
                            $issueDateDb = $date->format('Y-m-d');
                        } catch (\Exception $e) {
                            DB::rollBack();
                            return response()->json([
                                'success' => false,
                                'message' => "Invalid date format for issue_date at index {$index}. Expected format: dd/mm/yyyy"
                            ], 422);
                        }
                    }

                    $expiryDateDb = null;
                    if ($expiryDate) {
                        try {
                            $date = Carbon::createFromFormat('d/m/Y', $expiryDate);
                            $expiryDateDb = $date->format('Y-m-d');
                        } catch (\Exception $e) {
                            DB::rollBack();
                            return response()->json([
                                'success' => false,
                                'message' => "Invalid date format for expiry_date at index {$index}. Expected format: dd/mm/yyyy"
                            ], 422);
                        }
                    }

                    // Save passport number - store record ID in meta_type (original ID for updates, generated ID for new records)
                    ClientPortalDetailAudit::create([
                        'client_id' => $clientId,
                        'meta_key' => 'passport',
                        'old_value' => null,
                        'new_value' => $passportNumber,
                        'meta_order' => $metaOrder,
                        'meta_type' => (string) $passportId, // Store record ID (original or generated)
                        'action' => $action,
                        'updated_by' => $userId,
                        'updated_at' => now(),
                    ]);

                    // Save passport country if provided
                    if ($country) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'passport_country',
                            'old_value' => null,
                            'new_value' => $country,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $passportId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save issue date if provided
                    if ($issueDateDb) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'passport_issue_date',
                            'old_value' => null,
                            'new_value' => $issueDateDb,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $passportId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save expiry date if provided
                    if ($expiryDateDb) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'passport_expiry_date',
                            'old_value' => null,
                            'new_value' => $expiryDateDb,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $passportId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    $updatedPassports[] = [
                        'id' => $passportId, // Original ID for updates, generated ID for new records
                        'passport_number' => $passportNumber,
                        'country' => $country,
                        'issue_date' => $issueDate,
                        'expiry_date' => $expiryDate,
                    ];
                }

                DB::commit();

                $this->notifyStaffAndCreateActionForDetailUpdate($clientId, 'Passport detail updated');

                return response()->json([
                    'success' => true,
                    'message' => 'Passport details updated successfully',
                    'data' => [
                        'passports' => $updatedPassports
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete client tab detail - unified API to delete various client details by type
     * 
     * This API allows clients to delete their information by type (passport, visa, phone, email, etc.).
     * If the record exists in audit table, it updates the action to 'delete'.
     * If it doesn't exist in audit table but exists in source table, it creates audit entries with action='delete'.
     * 
     * Supported types: passport, visa, phone, email, address, travel, qualification, experience, occupation, testscore
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteClientTabDetail(Request $request)
    {
        try {
            // Get authenticated client ID from token
            $admin = $request->user();
            $clientId = (int) $admin->id;

            // Verify the authenticated client (Admin model; Staff use Staff model)
            if (!$admin instanceof Admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. This endpoint is only available for clients.'
                ], 403);
            }

            // Validate the request
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'id' => 'required|integer',
                'type' => 'required|string|in:passport,visa,phone,email,address,travel,qualification,experience,occupation,testscore',
            ], [
                'id.required' => 'ID is required.',
                'id.integer' => 'ID must be an integer.',
                'type.required' => 'Type is required.',
                'type.in' => 'Type must be one of: passport, visa, phone, email, address, travel, qualification, experience, occupation, testscore.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $recordId = (int) $request->input('id');
            $type = $request->input('type');
            $userId = Auth::id() ?? $clientId;

            // Route to specific type handler
            switch ($type) {
                case 'passport':
                    return $this->deletePassportRecord($clientId, $recordId, $userId);
                case 'visa':
                    return $this->deleteVisaRecord($clientId, $recordId, $userId);
                case 'phone':
                    return $this->deletePhoneRecord($clientId, $recordId, $userId);
                case 'email':
                    return $this->deleteEmailRecord($clientId, $recordId, $userId);
                case 'address':
                    return $this->deleteAddressRecord($clientId, $recordId, $userId);
                case 'travel':
                    return $this->deleteTravelRecord($clientId, $recordId, $userId);
                case 'qualification':
                    return $this->deleteQualificationRecord($clientId, $recordId, $userId);
                case 'experience':
                    return $this->deleteExperienceRecord($clientId, $recordId, $userId);
                case 'occupation':
                    return $this->deleteOccupationRecord($clientId, $recordId, $userId);
                case 'testscore':
                    return $this->deleteTestScoreRecord($clientId, $recordId, $userId);
                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid type specified.'
                    ], 422);
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete passport record - helper method for passport deletion
     * 
     * @param int $clientId
     * @param int $passportId
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    private function deletePassportRecord($clientId, $passportId, $userId)
    {
        DB::beginTransaction();

        try {
            // Step 1: Check if record exists in audit table with meta_type matching id and client_id matching user_id
            $existingAuditEntry = ClientPortalDetailAudit::where('client_id', $clientId)
                ->where('meta_key', 'passport')
                ->where('meta_type', (string) $passportId)
                ->first();

            if ($existingAuditEntry) {
                // Record exists in audit table - update action to 'delete' for all related entries
                $metaOrder = $existingAuditEntry->meta_order;

                // Update all related passport entries to action='delete'
                ClientPortalDetailAudit::where('client_id', $clientId)
                    ->whereIn('meta_key', ['passport', 'passport_country', 'passport_issue_date', 'passport_expiry_date'])
                    ->where('meta_order', $metaOrder)
                    ->update([
                        'action' => 'delete',
                        'updated_by' => $userId,
                        'updated_at' => now(),
                    ]);

                DB::commit();

                $this->notifyStaffAndCreateActionForDetailUpdate($clientId, 'Passport detail deleted');

                return response()->json([
                    'success' => true,
                    'message' => 'Passport deleted successfully',
                    'data' => [
                        'id' => $passportId,
                        'type' => 'passport',
                        'action' => 'delete'
                    ]
                ]);
            }

            // Step 2: Record doesn't exist in audit table - check source table
            $sourcePassport = DB::table('client_passport_informations')
                ->where('client_id', $clientId)
                ->where('id', $passportId)
                ->first();

            if (!$sourcePassport) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Passport ID does not exist'
                ], 404);
            }

            // Step 3: Record exists in source table - insert into audit table with action='delete'
            // Get the highest existing meta_order to continue from there
            $maxMetaOrder = ClientPortalDetailAudit::where('client_id', $clientId)
                ->whereIn('meta_key', ['passport', 'passport_country', 'passport_issue_date', 'passport_expiry_date'])
                ->max('meta_order') ?? -1;
            $metaOrder = $maxMetaOrder + 1;

            // Format dates from database format to dd/mm/yyyy
            $issueDate = $sourcePassport->passport_issue_date ? $this->formatDate($sourcePassport->passport_issue_date) : null;
            $expiryDate = $sourcePassport->passport_expiry_date ? $this->formatDate($sourcePassport->passport_expiry_date) : null;

            // Convert dates from dd/mm/yyyy to Y-m-d format for storage
            $issueDateDb = null;
            if (!empty($sourcePassport->passport_issue_date)) {
                try {
                    $date = Carbon::createFromFormat('Y-m-d', $sourcePassport->passport_issue_date);
                    $issueDateDb = $date->format('Y-m-d');
                } catch (\Exception $e) {
                    // If date is already in wrong format, try to parse it
                    try {
                        $date = Carbon::parse($sourcePassport->passport_issue_date);
                        $issueDateDb = $date->format('Y-m-d');
                    } catch (\Exception $e2) {
                        $issueDateDb = null;
                    }
                }
            }

            $expiryDateDb = null;
            if (!empty($sourcePassport->passport_expiry_date)) {
                try {
                    $date = Carbon::createFromFormat('Y-m-d', $sourcePassport->passport_expiry_date);
                    $expiryDateDb = $date->format('Y-m-d');
                } catch (\Exception $e) {
                    // If date is already in wrong format, try to parse it
                    try {
                        $date = Carbon::parse($sourcePassport->passport_expiry_date);
                        $expiryDateDb = $date->format('Y-m-d');
                    } catch (\Exception $e2) {
                        $expiryDateDb = null;
                    }
                }
            }

            // Insert passport number
            ClientPortalDetailAudit::create([
                'client_id' => $clientId,
                'meta_key' => 'passport',
                'old_value' => null,
                'new_value' => $sourcePassport->passport ?? '',
                'meta_order' => $metaOrder,
                'meta_type' => (string) $passportId,
                'action' => 'delete',
                'updated_by' => $userId,
                'updated_at' => now(),
            ]);

            // Insert passport country if exists
            if ($sourcePassport->passport_country) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'passport_country',
                    'old_value' => null,
                    'new_value' => $sourcePassport->passport_country,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $passportId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            // Insert issue date if exists
            if ($issueDateDb) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'passport_issue_date',
                    'old_value' => null,
                    'new_value' => $issueDateDb,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $passportId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            // Insert expiry date if exists
            if ($expiryDateDb) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'passport_expiry_date',
                    'old_value' => null,
                    'new_value' => $expiryDateDb,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $passportId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            $this->notifyStaffAndCreateActionForDetailUpdate($clientId, 'Passport detail deleted');

            return response()->json([
                'success' => true,
                'message' => 'Passport deleted successfully',
                'data' => [
                    'id' => $passportId,
                    'type' => 'passport',
                    'passport_number' => $sourcePassport->passport ?? '',
                    'country' => $sourcePassport->passport_country ?? null,
                    'issue_date' => $issueDate,
                    'expiry_date' => $expiryDate,
                    'action' => 'delete'
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete visa record - helper method for visa deletion
     * 
     * @param int $clientId
     * @param int $visaId
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    private function deleteVisaRecord($clientId, $visaId, $userId)
    {
        DB::beginTransaction();

        try {
            // Step 1: Check if record exists in audit table with meta_type matching id and client_id matching user_id
            $existingAuditEntry = ClientPortalDetailAudit::where('client_id', $clientId)
                ->where('meta_key', 'visa')
                ->where('meta_type', (string) $visaId)
                ->first();

            if ($existingAuditEntry) {
                // Record exists in audit table - update action to 'delete' for all related entries
                $metaOrder = $existingAuditEntry->meta_order;

                // Update all related visa entries to action='delete'
                ClientPortalDetailAudit::where('client_id', $clientId)
                    ->whereIn('meta_key', ['visa', 'visa_country', 'visa_type', 'visa_description', 'visa_expiry_date', 'visa_grant_date'])
                    ->where('meta_order', $metaOrder)
                    ->update([
                        'action' => 'delete',
                        'updated_by' => $userId,
                        'updated_at' => now(),
                    ]);

                DB::commit();

                $this->notifyStaffAndCreateActionForDetailUpdate($clientId, 'Visa detail deleted');

                return response()->json([
                    'success' => true,
                    'message' => 'Visa deleted successfully',
                    'data' => [
                        'id' => $visaId,
                        'type' => 'visa',
                        'action' => 'delete'
                    ]
                ]);
            }

            // Step 2: Record doesn't exist in audit table - check source table
            $sourceVisa = DB::table('client_visa_countries')
                ->where('client_id', $clientId)
                ->where('id', $visaId)
                ->first();

            if (!$sourceVisa) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Visa ID does not exist'
                ], 404);
            }

            // Step 3: Record exists in source table - insert into audit table with action='delete'
            // Get the highest existing meta_order to continue from there
            $maxMetaOrder = ClientPortalDetailAudit::where('client_id', $clientId)
                ->whereIn('meta_key', ['visa', 'visa_country', 'visa_type', 'visa_description', 'visa_expiry_date', 'visa_grant_date'])
                ->max('meta_order') ?? -1;
            $metaOrder = $maxMetaOrder + 1;

            // Format dates from database format to dd/mm/yyyy
            $expiryDate = !empty($sourceVisa->visa_expiry_date) ? $this->formatDate($sourceVisa->visa_expiry_date) : null;
            $grantDate = !empty($sourceVisa->visa_grant_date) ? $this->formatDate($sourceVisa->visa_grant_date) : null;

            // Convert dates from Y-m-d to Y-m-d format for storage (already in correct format, but handle edge cases)
            $expiryDateDb = null;
            if (!empty($sourceVisa->visa_expiry_date)) {
                try {
                    $date = Carbon::createFromFormat('Y-m-d', $sourceVisa->visa_expiry_date);
                    $expiryDateDb = $date->format('Y-m-d');
                } catch (\Exception $e) {
                    // If date is already in wrong format, try to parse it
                    try {
                        $date = Carbon::parse($sourceVisa->visa_expiry_date);
                        $expiryDateDb = $date->format('Y-m-d');
                    } catch (\Exception $e2) {
                        $expiryDateDb = null;
                    }
                }
            }

            $grantDateDb = null;
            if (!empty($sourceVisa->visa_grant_date)) {
                try {
                    $date = Carbon::createFromFormat('Y-m-d', $sourceVisa->visa_grant_date);
                    $grantDateDb = $date->format('Y-m-d');
                } catch (\Exception $e) {
                    // If date is already in wrong format, try to parse it
                    try {
                        $date = Carbon::parse($sourceVisa->visa_grant_date);
                        $grantDateDb = $date->format('Y-m-d');
                    } catch (\Exception $e2) {
                        $grantDateDb = null;
                    }
                }
            }

            // Insert visa marker
            ClientPortalDetailAudit::create([
                'client_id' => $clientId,
                'meta_key' => 'visa',
                'old_value' => null,
                'new_value' => '1', // Marker value
                'meta_order' => $metaOrder,
                'meta_type' => (string) $visaId,
                'action' => 'delete',
                'updated_by' => $userId,
                'updated_at' => now(),
            ]);

            // Insert visa type if exists
            if ($sourceVisa->visa_type !== null) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'visa_type',
                    'old_value' => null,
                    'new_value' => (string) $sourceVisa->visa_type,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $visaId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            // Insert visa description if exists
            if ($sourceVisa->visa_description) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'visa_description',
                    'old_value' => null,
                    'new_value' => $sourceVisa->visa_description,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $visaId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            // Insert visa expiry date if exists
            if ($expiryDateDb) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'visa_expiry_date',
                    'old_value' => null,
                    'new_value' => $expiryDateDb,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $visaId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            // Insert visa grant date if exists
            if ($grantDateDb) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'visa_grant_date',
                    'old_value' => null,
                    'new_value' => $grantDateDb,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $visaId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            $this->notifyStaffAndCreateActionForDetailUpdate($clientId, 'Visa detail deleted');

            $client = Admin::find($clientId);
            return response()->json([
                'success' => true,
                'message' => 'Visa deleted successfully',
                'data' => [
                    'id' => $visaId,
                    'type' => 'visa',
                    'visa_country' => $client ? ($client->country_passport ?? null) : null,
                    'visa_type' => $sourceVisa->visa_type ?? null,
                    'visa_description' => $sourceVisa->visa_description ?? null,
                    'visa_expiry_date' => $expiryDate,
                    'visa_grant_date' => $grantDate,
                    'action' => 'delete'
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete phone record - helper method for phone deletion
     * 
     * @param int $clientId
     * @param int $phoneId
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    private function deletePhoneRecord($clientId, $phoneId, $userId)
    {
        DB::beginTransaction();

        try {
            // Step 1: Check if record exists in audit table with meta_type matching id and client_id matching user_id
            $existingAuditEntry = ClientPortalDetailAudit::where('client_id', $clientId)
                ->where('meta_key', 'phone')
                ->where('meta_type', (string) $phoneId)
                ->first();

            if ($existingAuditEntry) {
                // Record exists in audit table - check if it's a Personal phone
                $metaOrder = $existingAuditEntry->meta_order;
                
                // Get phone_type from audit table
                $phoneTypeEntry = ClientPortalDetailAudit::where('client_id', $clientId)
                    ->where('meta_key', 'phone_type')
                    ->where('meta_order', $metaOrder)
                    ->orderBy('updated_at', 'desc')
                    ->first();
                
                $phoneType = $phoneTypeEntry ? $phoneTypeEntry->new_value : null;
                
                // Check if phone type is Personal (case-insensitive)
                if (strtolower(trim($phoneType ?? '')) === 'personal') {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Personal phone number cannot be deleted. It is readonly.'
                    ], 422);
                }

                // Update all related phone entries to action='delete'
                ClientPortalDetailAudit::where('client_id', $clientId)
                    ->whereIn('meta_key', ['phone', 'phone_type', 'phone_country_code', 'phone_extension'])
                    ->where('meta_order', $metaOrder)
                    ->update([
                        'action' => 'delete',
                        'updated_by' => $userId,
                        'updated_at' => now(),
                    ]);

                DB::commit();

                $this->notifyStaffAndCreateActionForDetailUpdate($clientId, 'Phone detail deleted');

                return response()->json([
                    'success' => true,
                    'message' => 'Phone deleted successfully',
                    'data' => [
                        'id' => $phoneId,
                        'type' => 'phone',
                        'action' => 'delete'
                    ]
                ]);
            }

            // Step 2: Record doesn't exist in audit table - check source table
            $sourcePhone = DB::table('client_contacts')
                ->where('client_id', $clientId)
                ->where('id', $phoneId)
                ->first();

            if (!$sourcePhone) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Phone ID does not exist'
                ], 404);
            }
            
            // Check if phone type is Personal (case-insensitive)
            $phoneType = $sourcePhone->contact_type ?? 'Mobile';
            if (strtolower(trim($phoneType)) === 'personal') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Personal phone number cannot be deleted. It is readonly.'
                ], 422);
            }

            // Step 3: Record exists in source table - insert into audit table with action='delete'
            // Get the highest existing meta_order to continue from there
            $maxMetaOrder = ClientPortalDetailAudit::where('client_id', $clientId)
                ->whereIn('meta_key', ['phone', 'phone_type', 'phone_country_code', 'phone_extension'])
                ->max('meta_order') ?? -1;
            $metaOrder = $maxMetaOrder + 1;

            // Insert phone number
            ClientPortalDetailAudit::create([
                'client_id' => $clientId,
                'meta_key' => 'phone',
                'old_value' => null,
                'new_value' => $sourcePhone->phone ?? '',
                'meta_order' => $metaOrder,
                'meta_type' => (string) $phoneId,
                'action' => 'delete',
                'updated_by' => $userId,
                'updated_at' => now(),
            ]);

            // Insert phone type
            $phoneType = $sourcePhone->contact_type ?? 'Mobile';
            ClientPortalDetailAudit::create([
                'client_id' => $clientId,
                'meta_key' => 'phone_type',
                'old_value' => null,
                'new_value' => $phoneType,
                'meta_order' => $metaOrder,
                'meta_type' => (string) $phoneId,
                'action' => 'delete',
                'updated_by' => $userId,
                'updated_at' => now(),
            ]);

            // Insert country code if exists
            if ($sourcePhone->country_code) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'phone_country_code',
                    'old_value' => null,
                    'new_value' => $sourcePhone->country_code,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $phoneId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            // Insert extension if exists
            $extension = $sourcePhone->extension ?? null;
            if ($extension) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'phone_extension',
                    'old_value' => null,
                    'new_value' => $extension,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $phoneId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            $this->notifyStaffAndCreateActionForDetailUpdate($clientId, 'Phone detail deleted');

            return response()->json([
                'success' => true,
                'message' => 'Phone deleted successfully',
                'data' => [
                    'id' => $phoneId,
                    'type' => 'phone',
                    'phone' => $sourcePhone->phone ?? '',
                    'phone_type' => $phoneType,
                    'country_code' => $sourcePhone->country_code ?? null,
                    'extension' => $extension ?? null,
                    'action' => 'delete'
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete email record - helper method for email deletion
     * 
     * @param int $clientId
     * @param int $emailId
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    private function deleteEmailRecord($clientId, $emailId, $userId)
    {
        DB::beginTransaction();

        try {
            // Step 1: Check if record exists in audit table with meta_type matching id and client_id matching user_id
            $existingAuditEntry = ClientPortalDetailAudit::where('client_id', $clientId)
                ->where('meta_key', 'email')
                ->where('meta_type', (string) $emailId)
                ->first();

            if ($existingAuditEntry) {
                // Record exists in audit table - check if it's a Personal email
                $metaOrder = $existingAuditEntry->meta_order;
                
                // Get email_type from audit table
                $emailTypeEntry = ClientPortalDetailAudit::where('client_id', $clientId)
                    ->where('meta_key', 'email_type')
                    ->where('meta_order', $metaOrder)
                    ->orderBy('updated_at', 'desc')
                    ->first();
                
                $emailType = $emailTypeEntry ? $emailTypeEntry->new_value : null;
                
                // Check if email type is Personal (case-insensitive)
                if (strtolower(trim($emailType ?? '')) === 'personal') {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Personal email cannot be deleted. It is readonly.'
                    ], 422);
                }

                // Update all related email entries to action='delete'
                ClientPortalDetailAudit::where('client_id', $clientId)
                    ->whereIn('meta_key', ['email', 'email_type'])
                    ->where('meta_order', $metaOrder)
                    ->update([
                        'action' => 'delete',
                        'updated_by' => $userId,
                        'updated_at' => now(),
                    ]);

                DB::commit();

                $this->notifyStaffAndCreateActionForDetailUpdate($clientId, 'Email detail deleted');

                return response()->json([
                    'success' => true,
                    'message' => 'Email deleted successfully',
                    'data' => [
                        'id' => $emailId,
                        'type' => 'email',
                        'action' => 'delete'
                    ]
                ]);
            }

            // Step 2: Record doesn't exist in audit table - check source table
            $sourceEmail = DB::table('client_emails')
                ->where('client_id', $clientId)
                ->where('id', $emailId)
                ->first();

            if (!$sourceEmail) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Email ID does not exist'
                ], 404);
            }
            
            // Check if email type is Personal (case-insensitive)
            $emailType = $sourceEmail->email_type ?? 'Personal';
            if (strtolower(trim($emailType)) === 'personal') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Personal email cannot be deleted. It is readonly.'
                ], 422);
            }

            // Step 3: Record exists in source table - insert into audit table with action='delete'
            // Get the highest existing meta_order to continue from there
            $maxMetaOrder = ClientPortalDetailAudit::where('client_id', $clientId)
                ->whereIn('meta_key', ['email', 'email_type'])
                ->max('meta_order') ?? -1;
            $metaOrder = $maxMetaOrder + 1;

            // Insert email address
            ClientPortalDetailAudit::create([
                'client_id' => $clientId,
                'meta_key' => 'email',
                'old_value' => null,
                'new_value' => $sourceEmail->email ?? '',
                'meta_order' => $metaOrder,
                'meta_type' => (string) $emailId,
                'action' => 'delete',
                'updated_by' => $userId,
                'updated_at' => now(),
            ]);

            // Insert email type
            $emailType = $sourceEmail->email_type ?? 'Personal';
            ClientPortalDetailAudit::create([
                'client_id' => $clientId,
                'meta_key' => 'email_type',
                'old_value' => null,
                'new_value' => $emailType,
                'meta_order' => $metaOrder,
                'meta_type' => (string) $emailId,
                'action' => 'delete',
                'updated_by' => $userId,
                'updated_at' => now(),
            ]);

            DB::commit();

            $this->notifyStaffAndCreateActionForDetailUpdate($clientId, 'Email detail deleted');

            return response()->json([
                'success' => true,
                'message' => 'Email deleted successfully',
                'data' => [
                    'id' => $emailId,
                    'type' => 'email',
                    'email' => $sourceEmail->email ?? '',
                    'email_type' => $emailType,
                    'action' => 'delete'
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete address record - helper method for address deletion
     * 
     * @param int $clientId
     * @param int $addressId
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    private function deleteAddressRecord($clientId, $addressId, $userId)
    {
        DB::beginTransaction();

        try {
            // Step 1: Check if record exists in audit table with meta_type matching id and client_id matching user_id
            // Use address_line_1 as the primary meta_key (same as in getAddressesFromAudit)
            $existingAuditEntry = ClientPortalDetailAudit::where('client_id', $clientId)
                ->where('meta_key', 'address_line_1')
                ->where('meta_type', (string) $addressId)
                ->first();

            if ($existingAuditEntry) {
                // Record exists in audit table - update action to 'delete' for all related entries
                $metaOrder = $existingAuditEntry->meta_order;

                // Update all related address entries to action='delete'
                ClientPortalDetailAudit::where('client_id', $clientId)
                    ->whereIn('meta_key', ['address', 'address_line_1', 'address_line_2', 'address_suburb', 'address_state', 'address_postcode', 'address_country', 'address_regional_code', 'address_start_date', 'address_end_date', 'address_is_current'])
                    ->where('meta_order', $metaOrder)
                    ->update([
                        'action' => 'delete',
                        'updated_by' => $userId,
                        'updated_at' => now(),
                    ]);

                DB::commit();

                $this->notifyStaffAndCreateActionForDetailUpdate($clientId, 'Address detail deleted');

                return response()->json([
                    'success' => true,
                    'message' => 'Address deleted successfully',
                    'data' => [
                        'id' => $addressId,
                        'type' => 'address',
                        'action' => 'delete'
                    ]
                ]);
            }

            // Step 2: Record doesn't exist in audit table - check source table
            $sourceAddress = DB::table('client_addresses')
                ->where('client_id', $clientId)
                ->where('id', $addressId)
                ->first();

            if (!$sourceAddress) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Address ID does not exist'
                ], 404);
            }

            // Step 3: Record exists in source table - insert into audit table with action='delete'
            // Get the highest existing meta_order to continue from there
            $maxMetaOrder = ClientPortalDetailAudit::where('client_id', $clientId)
                ->whereIn('meta_key', ['address', 'address_line_1', 'address_line_2', 'address_suburb', 'address_state', 'address_postcode', 'address_country', 'address_regional_code', 'address_start_date', 'address_end_date', 'address_is_current'])
                ->max('meta_order') ?? -1;
            $metaOrder = $maxMetaOrder + 1;

            // Format dates from database format to dd/mm/yyyy
            $startDate = $sourceAddress->start_date ? $this->formatDate($sourceAddress->start_date) : null;
            $endDate = $sourceAddress->end_date ? $this->formatDate($sourceAddress->end_date) : null;

            // Convert dates from dd/mm/yyyy to Y-m-d format for storage
            $startDateDb = null;
            if (!empty($sourceAddress->start_date)) {
                try {
                    $date = Carbon::createFromFormat('Y-m-d', $sourceAddress->start_date);
                    $startDateDb = $date->format('Y-m-d');
                } catch (\Exception $e) {
                    try {
                        $date = Carbon::parse($sourceAddress->start_date);
                        $startDateDb = $date->format('Y-m-d');
                    } catch (\Exception $e2) {
                        $startDateDb = null;
                    }
                }
            }

            $endDateDb = null;
            if (!empty($sourceAddress->end_date)) {
                try {
                    $date = Carbon::createFromFormat('Y-m-d', $sourceAddress->end_date);
                    $endDateDb = $date->format('Y-m-d');
                } catch (\Exception $e) {
                    try {
                        $date = Carbon::parse($sourceAddress->end_date);
                        $endDateDb = $date->format('Y-m-d');
                    } catch (\Exception $e2) {
                        $endDateDb = null;
                    }
                }
            }

            // Insert search_address if exists
            if ($sourceAddress->address) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'address',
                    'old_value' => null,
                    'new_value' => $sourceAddress->address,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $addressId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            // Insert address_line_1
            ClientPortalDetailAudit::create([
                'client_id' => $clientId,
                'meta_key' => 'address_line_1',
                'old_value' => null,
                'new_value' => $sourceAddress->address_line_1 ?? '',
                'meta_order' => $metaOrder,
                'meta_type' => (string) $addressId,
                'action' => 'delete',
                'updated_by' => $userId,
                'updated_at' => now(),
            ]);

            // Insert address_line_2 if exists
            if ($sourceAddress->address_line_2) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'address_line_2',
                    'old_value' => null,
                    'new_value' => $sourceAddress->address_line_2,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $addressId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            // Insert suburb
            ClientPortalDetailAudit::create([
                'client_id' => $clientId,
                'meta_key' => 'address_suburb',
                'old_value' => null,
                'new_value' => $sourceAddress->suburb ?? '',
                'meta_order' => $metaOrder,
                'meta_type' => (string) $addressId,
                'action' => 'delete',
                'updated_by' => $userId,
                'updated_at' => now(),
            ]);

            // Insert state
            ClientPortalDetailAudit::create([
                'client_id' => $clientId,
                'meta_key' => 'address_state',
                'old_value' => null,
                'new_value' => $sourceAddress->state ?? '',
                'meta_order' => $metaOrder,
                'meta_type' => (string) $addressId,
                'action' => 'delete',
                'updated_by' => $userId,
                'updated_at' => now(),
            ]);

            // Insert postcode
            ClientPortalDetailAudit::create([
                'client_id' => $clientId,
                'meta_key' => 'address_postcode',
                'old_value' => null,
                'new_value' => $sourceAddress->zip ?? '',
                'meta_order' => $metaOrder,
                'meta_type' => (string) $addressId,
                'action' => 'delete',
                'updated_by' => $userId,
                'updated_at' => now(),
            ]);

            // Insert country
            ClientPortalDetailAudit::create([
                'client_id' => $clientId,
                'meta_key' => 'address_country',
                'old_value' => null,
                'new_value' => $sourceAddress->country ?? '',
                'meta_order' => $metaOrder,
                'meta_type' => (string) $addressId,
                'action' => 'delete',
                'updated_by' => $userId,
                'updated_at' => now(),
            ]);

            // Insert regional_code if exists
            if ($sourceAddress->regional_code) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'address_regional_code',
                    'old_value' => null,
                    'new_value' => $sourceAddress->regional_code,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $addressId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            // Insert start_date if exists
            if ($startDateDb) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'address_start_date',
                    'old_value' => null,
                    'new_value' => $startDateDb,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $addressId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            // Insert end_date if exists
            if ($endDateDb) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'address_end_date',
                    'old_value' => null,
                    'new_value' => $endDateDb,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $addressId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            // Insert is_current
            $isCurrent = ($sourceAddress->is_current == 1) ? true : false;
            ClientPortalDetailAudit::create([
                'client_id' => $clientId,
                'meta_key' => 'address_is_current',
                'old_value' => null,
                'new_value' => $isCurrent ? '1' : '0',
                'meta_order' => $metaOrder,
                'meta_type' => (string) $addressId,
                'action' => 'delete',
                'updated_by' => $userId,
                'updated_at' => now(),
            ]);

            DB::commit();

            $this->notifyStaffAndCreateActionForDetailUpdate($clientId, 'Address detail deleted');

            return response()->json([
                'success' => true,
                'message' => 'Address deleted successfully',
                'data' => [
                    'id' => $addressId,
                    'type' => 'address',
                    'search_address' => $sourceAddress->address ?? null,
                    'address_line_1' => $sourceAddress->address_line_1 ?? null,
                    'address_line_2' => $sourceAddress->address_line_2 ?? null,
                    'suburb' => $sourceAddress->suburb ?? null,
                    'state' => $sourceAddress->state ?? null,
                    'postcode' => $sourceAddress->zip ?? null,
                    'country' => $sourceAddress->country ?? null,
                    'regional_code' => $sourceAddress->regional_code ?? null,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'is_current' => $isCurrent,
                    'action' => 'delete'
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete travel record - helper method for travel deletion
     * 
     * @param int $clientId
     * @param int $travelId
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    private function deleteTravelRecord($clientId, $travelId, $userId)
    {
        DB::beginTransaction();

        try {
            // Step 1: Check if record exists in audit table
            $existingAuditEntry = ClientPortalDetailAudit::where('client_id', $clientId)
                ->where('meta_key', 'travel_country_visited')
                ->where('meta_type', (string) $travelId)
                ->first();

            if ($existingAuditEntry) {
                $metaOrder = $existingAuditEntry->meta_order;

                ClientPortalDetailAudit::where('client_id', $clientId)
                    ->whereIn('meta_key', ['travel', 'travel_country_visited', 'travel_arrival_date', 'travel_departure_date', 'travel_purpose'])
                    ->where('meta_order', $metaOrder)
                    ->update([
                        'action' => 'delete',
                        'updated_by' => $userId,
                        'updated_at' => now(),
                    ]);

                DB::commit();

                $this->notifyStaffAndCreateActionForDetailUpdate($clientId, 'Travel detail deleted');

                return response()->json([
                    'success' => true,
                    'message' => 'Travel deleted successfully',
                    'data' => [
                        'id' => $travelId,
                        'type' => 'travel',
                        'action' => 'delete'
                    ]
                ]);
            }

            // Step 2: Check source table
            $sourceTravel = DB::table('client_travel_informations')
                ->where('client_id', $clientId)
                ->where('id', $travelId)
                ->first();

            if (!$sourceTravel) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Travel ID does not exist'
                ], 404);
            }

            // Step 3: Insert into audit table
            $maxMetaOrder = ClientPortalDetailAudit::where('client_id', $clientId)
                ->whereIn('meta_key', ['travel', 'travel_country_visited', 'travel_arrival_date', 'travel_departure_date', 'travel_purpose'])
                ->max('meta_order') ?? -1;
            $metaOrder = $maxMetaOrder + 1;

            $arrivalDate = $sourceTravel->travel_arrival_date ? $this->formatDate($sourceTravel->travel_arrival_date) : null;
            $departureDate = $sourceTravel->travel_departure_date ? $this->formatDate($sourceTravel->travel_departure_date) : null;

            $arrivalDateDb = null;
            if (!empty($sourceTravel->travel_arrival_date)) {
                try {
                    $date = Carbon::createFromFormat('Y-m-d', $sourceTravel->travel_arrival_date);
                    $arrivalDateDb = $date->format('Y-m-d');
                } catch (\Exception $e) {
                    try {
                        $date = Carbon::parse($sourceTravel->travel_arrival_date);
                        $arrivalDateDb = $date->format('Y-m-d');
                    } catch (\Exception $e2) {
                        $arrivalDateDb = null;
                    }
                }
            }

            $departureDateDb = null;
            if (!empty($sourceTravel->travel_departure_date)) {
                try {
                    $date = Carbon::createFromFormat('Y-m-d', $sourceTravel->travel_departure_date);
                    $departureDateDb = $date->format('Y-m-d');
                } catch (\Exception $e) {
                    try {
                        $date = Carbon::parse($sourceTravel->travel_departure_date);
                        $departureDateDb = $date->format('Y-m-d');
                    } catch (\Exception $e2) {
                        $departureDateDb = null;
                    }
                }
            }

            ClientPortalDetailAudit::create([
                'client_id' => $clientId,
                'meta_key' => 'travel',
                'old_value' => null,
                'new_value' => '1',
                'meta_order' => $metaOrder,
                'meta_type' => (string) $travelId,
                'action' => 'delete',
                'updated_by' => $userId,
                'updated_at' => now(),
            ]);

            ClientPortalDetailAudit::create([
                'client_id' => $clientId,
                'meta_key' => 'travel_country_visited',
                'old_value' => null,
                'new_value' => $sourceTravel->travel_country_visited ?? '',
                'meta_order' => $metaOrder,
                'meta_type' => (string) $travelId,
                'action' => 'delete',
                'updated_by' => $userId,
                'updated_at' => now(),
            ]);

            if ($arrivalDateDb) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'travel_arrival_date',
                    'old_value' => null,
                    'new_value' => $arrivalDateDb,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $travelId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            if ($departureDateDb) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'travel_departure_date',
                    'old_value' => null,
                    'new_value' => $departureDateDb,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $travelId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            if ($sourceTravel->travel_purpose) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'travel_purpose',
                    'old_value' => null,
                    'new_value' => $sourceTravel->travel_purpose,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $travelId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            $this->notifyStaffAndCreateActionForDetailUpdate($clientId, 'Travel detail deleted');

            return response()->json([
                'success' => true,
                'message' => 'Travel deleted successfully',
                'data' => [
                    'id' => $travelId,
                    'type' => 'travel',
                    'country_visited' => $sourceTravel->travel_country_visited ?? null,
                    'arrival_date' => $arrivalDate,
                    'departure_date' => $departureDate,
                    'purpose' => $sourceTravel->travel_purpose ?? null,
                    'action' => 'delete'
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete qualification record - helper method for qualification deletion
     * 
     * @param int $clientId
     * @param int $qualificationId
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    private function deleteQualificationRecord($clientId, $qualificationId, $userId)
    {
        DB::beginTransaction();

        try {
            // Step 1: Check if record exists in audit table
            $existingAuditEntry = ClientPortalDetailAudit::where('client_id', $clientId)
                ->where('meta_key', 'qualification_name')
                ->where('meta_type', (string) $qualificationId)
                ->first();

            if ($existingAuditEntry) {
                $metaOrder = $existingAuditEntry->meta_order;

                ClientPortalDetailAudit::where('client_id', $clientId)
                    ->whereIn('meta_key', ['qualification', 'qualification_level', 'qualification_name', 'qualification_college_name', 'qualification_campus', 'qualification_country', 'qualification_state', 'qualification_start_date', 'qualification_finish_date', 'qualification_relevant', 'qualification_specialist_education', 'qualification_stem', 'qualification_regional_study'])
                    ->where('meta_order', $metaOrder)
                    ->update([
                        'action' => 'delete',
                        'updated_by' => $userId,
                        'updated_at' => now(),
                    ]);

                DB::commit();

                $this->notifyStaffAndCreateActionForDetailUpdate($clientId, 'Qualification detail deleted');

                return response()->json([
                    'success' => true,
                    'message' => 'Qualification deleted successfully',
                    'data' => [
                        'id' => $qualificationId,
                        'type' => 'qualification',
                        'action' => 'delete'
                    ]
                ]);
            }

            // Step 2: Check source table
            $sourceQualification = DB::table('client_qualifications')
                ->where('client_id', $clientId)
                ->where('id', $qualificationId)
                ->first();

            if (!$sourceQualification) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Qualification ID does not exist'
                ], 404);
            }

            // Step 3: Insert into audit table
            $maxMetaOrder = ClientPortalDetailAudit::where('client_id', $clientId)
                ->whereIn('meta_key', ['qualification', 'qualification_level', 'qualification_name', 'qualification_college_name', 'qualification_campus', 'qualification_country', 'qualification_state', 'qualification_start_date', 'qualification_finish_date', 'qualification_relevant', 'qualification_specialist_education', 'qualification_stem', 'qualification_regional_study'])
                ->max('meta_order') ?? -1;
            $metaOrder = $maxMetaOrder + 1;

            $startDate = $sourceQualification->start_date ? $this->formatDate($sourceQualification->start_date) : null;
            $finishDate = $sourceQualification->finish_date ? $this->formatDate($sourceQualification->finish_date) : null;

            $startDateDb = null;
            if (!empty($sourceQualification->start_date)) {
                try {
                    $date = Carbon::createFromFormat('Y-m-d', $sourceQualification->start_date);
                    $startDateDb = $date->format('Y-m-d');
                } catch (\Exception $e) {
                    try {
                        $date = Carbon::parse($sourceQualification->start_date);
                        $startDateDb = $date->format('Y-m-d');
                    } catch (\Exception $e2) {
                        $startDateDb = null;
                    }
                }
            }

            $finishDateDb = null;
            if (!empty($sourceQualification->finish_date)) {
                try {
                    $date = Carbon::createFromFormat('Y-m-d', $sourceQualification->finish_date);
                    $finishDateDb = $date->format('Y-m-d');
                } catch (\Exception $e) {
                    try {
                        $date = Carbon::parse($sourceQualification->finish_date);
                        $finishDateDb = $date->format('Y-m-d');
                    } catch (\Exception $e2) {
                        $finishDateDb = null;
                    }
                }
            }

            ClientPortalDetailAudit::create([
                'client_id' => $clientId,
                'meta_key' => 'qualification',
                'old_value' => null,
                'new_value' => '1',
                'meta_order' => $metaOrder,
                'meta_type' => (string) $qualificationId,
                'action' => 'delete',
                'updated_by' => $userId,
                'updated_at' => now(),
            ]);

            if ($sourceQualification->level) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'qualification_level',
                    'old_value' => null,
                    'new_value' => $sourceQualification->level,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $qualificationId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            if ($sourceQualification->name) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'qualification_name',
                    'old_value' => null,
                    'new_value' => $sourceQualification->name,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $qualificationId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            if ($sourceQualification->qual_college_name) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'qualification_college_name',
                    'old_value' => null,
                    'new_value' => $sourceQualification->qual_college_name,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $qualificationId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            if ($sourceQualification->qual_campus) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'qualification_campus',
                    'old_value' => null,
                    'new_value' => $sourceQualification->qual_campus,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $qualificationId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            if ($sourceQualification->country) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'qualification_country',
                    'old_value' => null,
                    'new_value' => $sourceQualification->country,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $qualificationId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            if ($sourceQualification->qual_state) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'qualification_state',
                    'old_value' => null,
                    'new_value' => $sourceQualification->qual_state,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $qualificationId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            if ($startDateDb) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'qualification_start_date',
                    'old_value' => null,
                    'new_value' => $startDateDb,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $qualificationId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            if ($finishDateDb) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'qualification_finish_date',
                    'old_value' => null,
                    'new_value' => $finishDateDb,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $qualificationId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            ClientPortalDetailAudit::create([
                'client_id' => $clientId,
                'meta_key' => 'qualification_relevant',
                'old_value' => null,
                'new_value' => ($sourceQualification->relevant_qualification == 1) ? '1' : '0',
                'meta_order' => $metaOrder,
                'meta_type' => (string) $qualificationId,
                'action' => 'delete',
                'updated_by' => $userId,
                'updated_at' => now(),
            ]);

            DB::commit();

            $this->notifyStaffAndCreateActionForDetailUpdate($clientId, 'Qualification detail deleted');

            return response()->json([
                'success' => true,
                'message' => 'Qualification deleted successfully',
                'data' => [
                    'id' => $qualificationId,
                    'type' => 'qualification',
                    'level' => $sourceQualification->level ?? null,
                    'name' => $sourceQualification->name ?? null,
                    'college_name' => $sourceQualification->qual_college_name ?? null,
                    'campus' => $sourceQualification->qual_campus ?? null,
                    'country' => $sourceQualification->country ?? null,
                    'state' => $sourceQualification->qual_state ?? null,
                    'start_date' => $startDate,
                    'finish_date' => $finishDate,
                    'relevant_qualification' => ($sourceQualification->relevant_qualification == 1) ? true : false,
                    'action' => 'delete'
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete experience record - helper method for experience deletion
     * 
     * @param int $clientId
     * @param int $experienceId
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    private function deleteExperienceRecord($clientId, $experienceId, $userId)
    {
        DB::beginTransaction();

        try {
            // Step 1: Check if record exists in audit table
            $existingAuditEntry = ClientPortalDetailAudit::where('client_id', $clientId)
                ->where('meta_key', 'experience_job_title')
                ->where('meta_type', (string) $experienceId)
                ->first();

            if ($existingAuditEntry) {
                $metaOrder = $existingAuditEntry->meta_order;

                ClientPortalDetailAudit::where('client_id', $clientId)
                    ->whereIn('meta_key', ['experience', 'experience_job_title', 'experience_job_code', 'experience_country', 'experience_start_date', 'experience_finish_date', 'experience_relevant', 'experience_employer_name', 'experience_state', 'experience_job_type', 'experience_fte_multiplier'])
                    ->where('meta_order', $metaOrder)
                    ->update([
                        'action' => 'delete',
                        'updated_by' => $userId,
                        'updated_at' => now(),
                    ]);

                DB::commit();

                $this->notifyStaffAndCreateActionForDetailUpdate($clientId, 'Experience detail deleted');

                return response()->json([
                    'success' => true,
                    'message' => 'Experience deleted successfully',
                    'data' => [
                        'id' => $experienceId,
                        'type' => 'experience',
                        'action' => 'delete'
                    ]
                ]);
            }

            // Step 2: Check source table
            $sourceExperience = DB::table('client_experiences')
                ->where('client_id', $clientId)
                ->where('id', $experienceId)
                ->first();

            if (!$sourceExperience) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Experience ID does not exist'
                ], 404);
            }

            // Step 3: Insert into audit table
            $maxMetaOrder = ClientPortalDetailAudit::where('client_id', $clientId)
                ->whereIn('meta_key', ['experience', 'experience_job_title', 'experience_job_code', 'experience_country', 'experience_start_date', 'experience_finish_date', 'experience_relevant', 'experience_employer_name', 'experience_state', 'experience_job_type', 'experience_fte_multiplier'])
                ->max('meta_order') ?? -1;
            $metaOrder = $maxMetaOrder + 1;

            $startDate = $sourceExperience->job_start_date ? $this->formatDate($sourceExperience->job_start_date) : null;
            $finishDate = $sourceExperience->job_finish_date ? $this->formatDate($sourceExperience->job_finish_date) : null;

            $startDateDb = null;
            if (!empty($sourceExperience->job_start_date)) {
                try {
                    $date = Carbon::createFromFormat('Y-m-d', $sourceExperience->job_start_date);
                    $startDateDb = $date->format('Y-m-d');
                } catch (\Exception $e) {
                    try {
                        $date = Carbon::parse($sourceExperience->job_start_date);
                        $startDateDb = $date->format('Y-m-d');
                    } catch (\Exception $e2) {
                        $startDateDb = null;
                    }
                }
            }

            $finishDateDb = null;
            if (!empty($sourceExperience->job_finish_date)) {
                try {
                    $date = Carbon::createFromFormat('Y-m-d', $sourceExperience->job_finish_date);
                    $finishDateDb = $date->format('Y-m-d');
                } catch (\Exception $e) {
                    try {
                        $date = Carbon::parse($sourceExperience->job_finish_date);
                        $finishDateDb = $date->format('Y-m-d');
                    } catch (\Exception $e2) {
                        $finishDateDb = null;
                    }
                }
            }

            ClientPortalDetailAudit::create([
                'client_id' => $clientId,
                'meta_key' => 'experience',
                'old_value' => null,
                'new_value' => '1',
                'meta_order' => $metaOrder,
                'meta_type' => (string) $experienceId,
                'action' => 'delete',
                'updated_by' => $userId,
                'updated_at' => now(),
            ]);

            if ($sourceExperience->job_title) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'experience_job_title',
                    'old_value' => null,
                    'new_value' => $sourceExperience->job_title,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $experienceId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            if ($sourceExperience->job_code) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'experience_job_code',
                    'old_value' => null,
                    'new_value' => $sourceExperience->job_code,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $experienceId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            if ($sourceExperience->job_emp_name) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'experience_employer_name',
                    'old_value' => null,
                    'new_value' => $sourceExperience->job_emp_name,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $experienceId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            if ($sourceExperience->job_country) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'experience_country',
                    'old_value' => null,
                    'new_value' => $sourceExperience->job_country,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $experienceId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            if ($sourceExperience->job_state) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'experience_state',
                    'old_value' => null,
                    'new_value' => $sourceExperience->job_state,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $experienceId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            if ($sourceExperience->job_type) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'experience_job_type',
                    'old_value' => null,
                    'new_value' => $sourceExperience->job_type,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $experienceId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            if ($startDateDb) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'experience_start_date',
                    'old_value' => null,
                    'new_value' => $startDateDb,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $experienceId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            if ($finishDateDb) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'experience_finish_date',
                    'old_value' => null,
                    'new_value' => $finishDateDb,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $experienceId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            ClientPortalDetailAudit::create([
                'client_id' => $clientId,
                'meta_key' => 'experience_relevant',
                'old_value' => null,
                'new_value' => ($sourceExperience->relevant_experience == 1) ? '1' : '0',
                'meta_order' => $metaOrder,
                'meta_type' => (string) $experienceId,
                'action' => 'delete',
                'updated_by' => $userId,
                'updated_at' => now(),
            ]);

            if ($sourceExperience->fte_multiplier !== null) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'experience_fte_multiplier',
                    'old_value' => null,
                    'new_value' => (string) $sourceExperience->fte_multiplier,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $experienceId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            $this->notifyStaffAndCreateActionForDetailUpdate($clientId, 'Experience detail deleted');

            return response()->json([
                'success' => true,
                'message' => 'Experience deleted successfully',
                'data' => [
                    'id' => $experienceId,
                    'type' => 'experience',
                    'job_title' => $sourceExperience->job_title ?? null,
                    'job_code' => $sourceExperience->job_code ?? null,
                    'country' => $sourceExperience->job_country ?? null,
                    'start_date' => $startDate,
                    'finish_date' => $finishDate,
                    'relevant_experience' => ($sourceExperience->relevant_experience == 1) ? true : false,
                    'employer_name' => $sourceExperience->job_emp_name ?? null,
                    'state' => $sourceExperience->job_state ?? null,
                    'job_type' => $sourceExperience->job_type ?? null,
                    'fte_multiplier' => $sourceExperience->fte_multiplier ? (float) $sourceExperience->fte_multiplier : null,
                    'action' => 'delete'
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete occupation record - helper method for occupation deletion
     * 
     * @param int $clientId
     * @param int $occupationId
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    private function deleteOccupationRecord($clientId, $occupationId, $userId)
    {
        DB::beginTransaction();

        try {
            // Step 1: Check if record exists in audit table
            $existingAuditEntry = ClientPortalDetailAudit::where('client_id', $clientId)
                ->where('meta_key', 'occupation_nominated')
                ->where('meta_type', (string) $occupationId)
                ->first();

            if ($existingAuditEntry) {
                $metaOrder = $existingAuditEntry->meta_order;

                ClientPortalDetailAudit::where('client_id', $clientId)
                    ->whereIn('meta_key', ['occupation', 'occupation_skill_assessment', 'occupation_nominated', 'occupation_code', 'occupation_assessing_authority', 'occupation_visa_subclass', 'occupation_assessment_date', 'occupation_expiry_date', 'occupation_reference_no', 'occupation_relevant', 'occupation_anzsco_id'])
                    ->where('meta_order', $metaOrder)
                    ->update([
                        'action' => 'delete',
                        'updated_by' => $userId,
                        'updated_at' => now(),
                    ]);

                DB::commit();

                $this->notifyStaffAndCreateActionForDetailUpdate($clientId, 'Occupation detail deleted');

                return response()->json([
                    'success' => true,
                    'message' => 'Occupation deleted successfully',
                    'data' => [
                        'id' => $occupationId,
                        'type' => 'occupation',
                        'action' => 'delete'
                    ]
                ]);
            }

            // Step 2: Check source table
            $sourceOccupation = DB::table('client_occupations')
                ->where('client_id', $clientId)
                ->where('id', $occupationId)
                ->first();

            if (!$sourceOccupation) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Occupation ID does not exist'
                ], 404);
            }

            // Step 3: Insert into audit table
            $maxMetaOrder = ClientPortalDetailAudit::where('client_id', $clientId)
                ->whereIn('meta_key', ['occupation', 'occupation_skill_assessment', 'occupation_nominated', 'occupation_code', 'occupation_assessing_authority', 'occupation_visa_subclass', 'occupation_assessment_date', 'occupation_expiry_date', 'occupation_reference_no', 'occupation_relevant', 'occupation_anzsco_id'])
                ->max('meta_order') ?? -1;
            $metaOrder = $maxMetaOrder + 1;

            $assessmentDate = !empty($sourceOccupation->dates) ? $this->formatDate($sourceOccupation->dates) : null;
            $expiryDate = !empty($sourceOccupation->expiry_dates) ? $this->formatDate($sourceOccupation->expiry_dates) : null;

            $assessmentDateDb = null;
            if (!empty($sourceOccupation->dates)) {
                try {
                    $date = Carbon::createFromFormat('Y-m-d', $sourceOccupation->dates);
                    $assessmentDateDb = $date->format('Y-m-d');
                } catch (\Exception $e) {
                    try {
                        $date = Carbon::parse($sourceOccupation->dates);
                        $assessmentDateDb = $date->format('Y-m-d');
                    } catch (\Exception $e2) {
                        $assessmentDateDb = null;
                    }
                }
            }

            $expiryDateDb = null;
            if (!empty($sourceOccupation->expiry_dates)) {
                try {
                    $date = Carbon::createFromFormat('Y-m-d', $sourceOccupation->expiry_dates);
                    $expiryDateDb = $date->format('Y-m-d');
                } catch (\Exception $e) {
                    try {
                        $date = Carbon::parse($sourceOccupation->expiry_dates);
                        $expiryDateDb = $date->format('Y-m-d');
                    } catch (\Exception $e2) {
                        $expiryDateDb = null;
                    }
                }
            }

            ClientPortalDetailAudit::create([
                'client_id' => $clientId,
                'meta_key' => 'occupation',
                'old_value' => null,
                'new_value' => '1',
                'meta_order' => $metaOrder,
                'meta_type' => (string) $occupationId,
                'action' => 'delete',
                'updated_by' => $userId,
                'updated_at' => now(),
            ]);

            if ($sourceOccupation->skill_assessment) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'occupation_skill_assessment',
                    'old_value' => null,
                    'new_value' => $sourceOccupation->skill_assessment,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $occupationId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            if ($sourceOccupation->nomi_occupation) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'occupation_nominated',
                    'old_value' => null,
                    'new_value' => $sourceOccupation->nomi_occupation,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $occupationId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            if ($sourceOccupation->occupation_code) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'occupation_code',
                    'old_value' => null,
                    'new_value' => $sourceOccupation->occupation_code,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $occupationId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            if ($sourceOccupation->list) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'occupation_assessing_authority',
                    'old_value' => null,
                    'new_value' => $sourceOccupation->list,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $occupationId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            if ($sourceOccupation->visa_subclass) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'occupation_visa_subclass',
                    'old_value' => null,
                    'new_value' => $sourceOccupation->visa_subclass,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $occupationId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            if ($assessmentDateDb) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'occupation_assessment_date',
                    'old_value' => null,
                    'new_value' => $assessmentDateDb,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $occupationId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            if ($expiryDateDb) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'occupation_expiry_date',
                    'old_value' => null,
                    'new_value' => $expiryDateDb,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $occupationId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            if ($sourceOccupation->occ_reference_no) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'occupation_reference_no',
                    'old_value' => null,
                    'new_value' => $sourceOccupation->occ_reference_no,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $occupationId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            ClientPortalDetailAudit::create([
                'client_id' => $clientId,
                'meta_key' => 'occupation_relevant',
                'old_value' => null,
                'new_value' => ($sourceOccupation->relevant_occupation == 1) ? '1' : '0',
                'meta_order' => $metaOrder,
                'meta_type' => (string) $occupationId,
                'action' => 'delete',
                'updated_by' => $userId,
                'updated_at' => now(),
            ]);

            if ($sourceOccupation->anzsco_occupation_id) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'occupation_anzsco_id',
                    'old_value' => null,
                    'new_value' => (string) $sourceOccupation->anzsco_occupation_id,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $occupationId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            $this->notifyStaffAndCreateActionForDetailUpdate($clientId, 'Occupation detail deleted');

            return response()->json([
                'success' => true,
                'message' => 'Occupation deleted successfully',
                'data' => [
                    'id' => $occupationId,
                    'type' => 'occupation',
                    'skill_assessment' => $sourceOccupation->skill_assessment ?? null,
                    'nominated_occupation' => $sourceOccupation->nomi_occupation ?? null,
                    'occupation_code' => $sourceOccupation->occupation_code ?? null,
                    'assessing_authority' => $sourceOccupation->list ?? null,
                    'visa_subclass' => $sourceOccupation->visa_subclass ?? null,
                    'assessment_date' => $assessmentDate,
                    'expiry_date' => $expiryDate,
                    'reference_no' => $sourceOccupation->occ_reference_no ?? null,
                    'relevant_occupation' => ($sourceOccupation->relevant_occupation == 1) ? true : false,
                    'anzsco_occupation_id' => $sourceOccupation->anzsco_occupation_id ? (int) $sourceOccupation->anzsco_occupation_id : null,
                    'action' => 'delete'
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete test score record - helper method for testscore deletion
     * 
     * @param int $clientId
     * @param int $testScoreId
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    private function deleteTestScoreRecord($clientId, $testScoreId, $userId)
    {
        DB::beginTransaction();

        try {
            // Step 1: Check if record exists in audit table
            $existingAuditEntry = ClientPortalDetailAudit::where('client_id', $clientId)
                ->where('meta_key', 'test_score_test_type')
                ->where('meta_type', (string) $testScoreId)
                ->first();

            if ($existingAuditEntry) {
                $metaOrder = $existingAuditEntry->meta_order;

                ClientPortalDetailAudit::where('client_id', $clientId)
                    ->whereIn('meta_key', ['test_score', 'test_score_test_type', 'test_score_listening', 'test_score_reading', 'test_score_writing', 'test_score_speaking', 'test_score_overall_score', 'test_score_test_date', 'test_score_reference_no', 'test_score_relevant'])
                    ->where('meta_order', $metaOrder)
                    ->update([
                        'action' => 'delete',
                        'updated_by' => $userId,
                        'updated_at' => now(),
                    ]);

                DB::commit();

                $this->notifyStaffAndCreateActionForDetailUpdate($clientId, 'Test score detail deleted');

                return response()->json([
                    'success' => true,
                    'message' => 'Test score deleted successfully',
                    'data' => [
                        'id' => $testScoreId,
                        'type' => 'testscore',
                        'action' => 'delete'
                    ]
                ]);
            }

            // Step 2: Check source table
            $sourceTestScore = DB::table('client_testscore')
                ->where('client_id', $clientId)
                ->where('id', $testScoreId)
                ->first();

            if (!$sourceTestScore) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Test score ID does not exist'
                ], 404);
            }

            // Step 3: Insert into audit table
            $maxMetaOrder = ClientPortalDetailAudit::where('client_id', $clientId)
                ->whereIn('meta_key', ['test_score', 'test_score_test_type', 'test_score_listening', 'test_score_reading', 'test_score_writing', 'test_score_speaking', 'test_score_overall_score', 'test_score_test_date', 'test_score_reference_no', 'test_score_relevant'])
                ->max('meta_order') ?? -1;
            $metaOrder = $maxMetaOrder + 1;

            $testDate = $sourceTestScore->test_date ? $this->formatDate($sourceTestScore->test_date) : null;

            $testDateDb = null;
            if (!empty($sourceTestScore->test_date)) {
                try {
                    $date = Carbon::createFromFormat('Y-m-d', $sourceTestScore->test_date);
                    $testDateDb = $date->format('Y-m-d');
                } catch (\Exception $e) {
                    try {
                        $date = Carbon::parse($sourceTestScore->test_date);
                        $testDateDb = $date->format('Y-m-d');
                    } catch (\Exception $e2) {
                        $testDateDb = null;
                    }
                }
            }

            ClientPortalDetailAudit::create([
                'client_id' => $clientId,
                'meta_key' => 'test_score',
                'old_value' => null,
                'new_value' => '1',
                'meta_order' => $metaOrder,
                'meta_type' => (string) $testScoreId,
                'action' => 'delete',
                'updated_by' => $userId,
                'updated_at' => now(),
            ]);

            if ($sourceTestScore->test_type) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'test_score_test_type',
                    'old_value' => null,
                    'new_value' => $sourceTestScore->test_type,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $testScoreId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            if ($sourceTestScore->listening !== null) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'test_score_listening',
                    'old_value' => null,
                    'new_value' => (string) $sourceTestScore->listening,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $testScoreId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            if ($sourceTestScore->reading !== null) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'test_score_reading',
                    'old_value' => null,
                    'new_value' => (string) $sourceTestScore->reading,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $testScoreId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            if ($sourceTestScore->writing !== null) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'test_score_writing',
                    'old_value' => null,
                    'new_value' => (string) $sourceTestScore->writing,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $testScoreId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            if ($sourceTestScore->speaking !== null) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'test_score_speaking',
                    'old_value' => null,
                    'new_value' => (string) $sourceTestScore->speaking,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $testScoreId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            if ($sourceTestScore->overall_score !== null) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'test_score_overall_score',
                    'old_value' => null,
                    'new_value' => (string) $sourceTestScore->overall_score,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $testScoreId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            if ($testDateDb) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'test_score_test_date',
                    'old_value' => null,
                    'new_value' => $testDateDb,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $testScoreId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            if ($sourceTestScore->test_reference_no) {
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'test_score_reference_no',
                    'old_value' => null,
                    'new_value' => $sourceTestScore->test_reference_no,
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $testScoreId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);
            }

            ClientPortalDetailAudit::create([
                'client_id' => $clientId,
                'meta_key' => 'test_score_relevant',
                'old_value' => null,
                'new_value' => (($sourceTestScore->relevant_test == 1) || (strtolower($sourceTestScore->relevant_test ?? '') === 'yes')) ? '1' : '0',
                'meta_order' => $metaOrder,
                'meta_type' => (string) $testScoreId,
                'action' => 'delete',
                'updated_by' => $userId,
                'updated_at' => now(),
            ]);

            DB::commit();

            $this->notifyStaffAndCreateActionForDetailUpdate($clientId, 'Test score detail deleted');

            return response()->json([
                'success' => true,
                'message' => 'Test score deleted successfully',
                'data' => [
                    'id' => $testScoreId,
                    'type' => 'testscore',
                    'test_type' => $sourceTestScore->test_type ?? null,
                    'listening' => $sourceTestScore->listening ? (float) $sourceTestScore->listening : null,
                    'reading' => $sourceTestScore->reading ? (float) $sourceTestScore->reading : null,
                    'writing' => $sourceTestScore->writing ? (float) $sourceTestScore->writing : null,
                    'speaking' => $sourceTestScore->speaking ? (float) $sourceTestScore->speaking : null,
                    'overall_score' => $sourceTestScore->overall_score ? (float) $sourceTestScore->overall_score : null,
                    'test_date' => $testDate,
                    'reference_no' => $sourceTestScore->test_reference_no ?? null,
                    'relevant_test' => (($sourceTestScore->relevant_test == 1) || (strtolower($sourceTestScore->relevant_test ?? '') === 'yes')) ? true : false,
                    'action' => 'delete'
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete client passport detail - marks passport as deleted in clientportal_details_audit table
     * 
     * This API allows clients to delete their passport information.
     * If the passport exists in audit table, it updates the action to 'delete'.
     * If it doesn't exist in audit table but exists in source table, it creates audit entries with action='delete'.
     * 
     * @deprecated Use deleteClientTabDetail with type='passport' instead
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteClientPassportDetail(Request $request)
    {
        try {
            // Get authenticated client ID from token
            $admin = $request->user();
            $clientId = (int) $admin->id;

            // Verify the authenticated client (Admin model; Staff use Staff model)
            if (!$admin instanceof Admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. This endpoint is only available for clients.'
                ], 403);
            }

            // Validate the request
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'id' => 'required|integer',
            ], [
                'id.required' => 'Passport ID is required.',
                'id.integer' => 'Passport ID must be an integer.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $passportId = (int) $request->input('id');
            $userId = Auth::id() ?? $clientId;

            DB::beginTransaction();

            try {
                // Step 1: Check if record exists in audit table with meta_type matching id and client_id matching user_id
                $existingAuditEntry = ClientPortalDetailAudit::where('client_id', $clientId)
                    ->where('meta_key', 'passport')
                    ->where('meta_type', (string) $passportId)
                    ->first();

                if ($existingAuditEntry) {
                    // Record exists in audit table - update action to 'delete' for all related entries
                    $metaOrder = $existingAuditEntry->meta_order;

                    // Update all related passport entries to action='delete'
                    ClientPortalDetailAudit::where('client_id', $clientId)
                        ->whereIn('meta_key', ['passport', 'passport_country', 'passport_issue_date', 'passport_expiry_date'])
                        ->where('meta_order', $metaOrder)
                        ->update([
                            'action' => 'delete',
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);

                    DB::commit();

                    $this->notifyStaffAndCreateActionForDetailUpdate($clientId, 'Passport detail deleted');

                    return response()->json([
                        'success' => true,
                        'message' => 'Passport deleted successfully',
                        'data' => [
                            'id' => $passportId,
                            'action' => 'delete'
                        ]
                    ]);
                }

                // Step 2: Record doesn't exist in audit table - check source table
                $sourcePassport = DB::table('client_passport_informations')
                    ->where('client_id', $clientId)
                    ->where('id', $passportId)
                    ->first();

                if (!$sourcePassport) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Passport ID does not exist'
                    ], 404);
                }

                // Step 3: Record exists in source table - insert into audit table with action='delete'
                // Get the highest existing meta_order to continue from there
                $maxMetaOrder = ClientPortalDetailAudit::where('client_id', $clientId)
                    ->whereIn('meta_key', ['passport', 'passport_country', 'passport_issue_date', 'passport_expiry_date'])
                    ->max('meta_order') ?? -1;
                $metaOrder = $maxMetaOrder + 1;

                // Format dates from database format to dd/mm/yyyy
                $issueDate = $sourcePassport->passport_issue_date ? $this->formatDate($sourcePassport->passport_issue_date) : null;
                $expiryDate = $sourcePassport->passport_expiry_date ? $this->formatDate($sourcePassport->passport_expiry_date) : null;

                // Convert dates from dd/mm/yyyy to Y-m-d format for storage
                $issueDateDb = null;
                if (!empty($sourcePassport->passport_issue_date)) {
                    try {
                        $date = Carbon::createFromFormat('Y-m-d', $sourcePassport->passport_issue_date);
                        $issueDateDb = $date->format('Y-m-d');
                    } catch (\Exception $e) {
                        // If date is already in wrong format, try to parse it
                        try {
                            $date = Carbon::parse($sourcePassport->passport_issue_date);
                            $issueDateDb = $date->format('Y-m-d');
                        } catch (\Exception $e2) {
                            $issueDateDb = null;
                        }
                    }
                }

                $expiryDateDb = null;
                if (!empty($sourcePassport->passport_expiry_date)) {
                    try {
                        $date = Carbon::createFromFormat('Y-m-d', $sourcePassport->passport_expiry_date);
                        $expiryDateDb = $date->format('Y-m-d');
                    } catch (\Exception $e) {
                        // If date is already in wrong format, try to parse it
                        try {
                            $date = Carbon::parse($sourcePassport->passport_expiry_date);
                            $expiryDateDb = $date->format('Y-m-d');
                        } catch (\Exception $e2) {
                            $expiryDateDb = null;
                        }
                    }
                }

                // Insert passport number
                ClientPortalDetailAudit::create([
                    'client_id' => $clientId,
                    'meta_key' => 'passport',
                    'old_value' => null,
                    'new_value' => $sourcePassport->passport ?? '',
                    'meta_order' => $metaOrder,
                    'meta_type' => (string) $passportId,
                    'action' => 'delete',
                    'updated_by' => $userId,
                    'updated_at' => now(),
                ]);

                // Insert passport country if exists
                if ($sourcePassport->passport_country) {
                    ClientPortalDetailAudit::create([
                        'client_id' => $clientId,
                        'meta_key' => 'passport_country',
                        'old_value' => null,
                        'new_value' => $sourcePassport->passport_country,
                        'meta_order' => $metaOrder,
                        'meta_type' => (string) $passportId,
                        'action' => 'delete',
                        'updated_by' => $userId,
                        'updated_at' => now(),
                    ]);
                }

                // Insert issue date if exists
                if ($issueDateDb) {
                    ClientPortalDetailAudit::create([
                        'client_id' => $clientId,
                        'meta_key' => 'passport_issue_date',
                        'old_value' => null,
                        'new_value' => $issueDateDb,
                        'meta_order' => $metaOrder,
                        'meta_type' => (string) $passportId,
                        'action' => 'delete',
                        'updated_by' => $userId,
                        'updated_at' => now(),
                    ]);
                }

                // Insert expiry date if exists
                if ($expiryDateDb) {
                    ClientPortalDetailAudit::create([
                        'client_id' => $clientId,
                        'meta_key' => 'passport_expiry_date',
                        'old_value' => null,
                        'new_value' => $expiryDateDb,
                        'meta_order' => $metaOrder,
                        'meta_type' => (string) $passportId,
                        'action' => 'delete',
                        'updated_by' => $userId,
                        'updated_at' => now(),
                    ]);
                }

                DB::commit();

                $this->notifyStaffAndCreateActionForDetailUpdate($clientId, 'Passport detail deleted');

                return response()->json([
                    'success' => true,
                    'message' => 'Passport deleted successfully',
                    'data' => [
                        'id' => $passportId,
                        'passport_number' => $sourcePassport->passport ?? '',
                        'country' => $sourcePassport->passport_country ?? null,
                        'issue_date' => $issueDate,
                        'expiry_date' => $expiryDate,
                        'action' => 'delete'
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update client visa details - saves to clientportal_details_audit table
     * 
     * This API allows clients to update their visa information.
     * Visas are saved to clientportal_details_audit table with meta_key and meta_order.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateClientVisaDetail(Request $request)
    {
        try {
            // Get authenticated client ID from token
            $admin = $request->user();
            $clientId = (int) $admin->id;

            // Verify the authenticated client (Admin model; Staff use Staff model)
            if (!$admin instanceof Admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. This endpoint is only available for clients.'
                ], 403);
            }

            // Validate the request
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'visas' => 'required|array|min:1',
                'visas.*.id' => 'present|nullable|integer',
                'visas.*.visa_type' => 'nullable|integer',
                'visas.*.visa_description' => 'nullable|string|max:255',
                'visas.*.visa_expiry_date' => 'nullable|date_format:d/m/Y',
                'visas.*.visa_grant_date' => 'nullable|date_format:d/m/Y',
            ], [
                'visas.required' => 'At least one visa is required.',
                'visas.*.id.present' => 'Visa ID field is required for each visa. Use null for new visas or provide the existing visa ID for updates.',
                'visas.*.id.integer' => 'Visa ID must be an integer or null.',
                'visas.*.visa_type.integer' => 'Visa type must be a valid ID.',
                'visas.*.visa_expiry_date.date_format' => 'Visa expiry date must be in dd/mm/yyyy format.',
                'visas.*.visa_grant_date.date_format' => 'Visa grant date must be in dd/mm/yyyy format.',
            ]);

            // Custom validation: Ensure id field is always present and visa_type must be provided
            $validator->after(function ($validator) use ($request) {
                $visas = $request->input('visas', []);
                foreach ($visas as $index => $visa) {
                    // Ensure id field is always present (required field)
                    if (!array_key_exists('id', $visa)) {
                        $validator->errors()->add(
                            "visas.{$index}.id",
                            "Visa ID field is required. Use null for new visas or provide the existing visa ID for updates."
                        );
                    }
                    
                    if (empty($visa['visa_type'])) {
                        $validator->errors()->add(
                            "visas.{$index}.visa_type",
                            "Visa type must be provided for each visa entry."
                        );
                    }
                    // Validate: visa grant date cannot be a future date (expiry can be future)
                    if (!empty($visa['visa_grant_date'])) {
                        try {
                            $grantDate = Carbon::createFromFormat('d/m/Y', $visa['visa_grant_date']);
                            if ($grantDate->isFuture()) {
                                $validator->errors()->add(
                                    "visas.{$index}.visa_grant_date",
                                    "The visa grant date cannot be a future date."
                                );
                            }
                        } catch (\Exception $e) {}
                    }
                }
            });

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $userId = Auth::id() ?? $clientId;
            $visas = $request->input('visas');
            $updatedVisas = [];

            DB::beginTransaction();

            try {
                // Get existing visa IDs from request to identify which ones to update
                $visaIdsToUpdate = [];
                $visaIdToMetaOrderMap = []; // Map visa ID to its meta_order
                
                foreach ($visas as $visaData) {
                    // ID field is required - if it has a value (not null), it's an update; if null, it's a new record
                    if (isset($visaData['id']) && $visaData['id'] !== null && $visaData['id'] !== '') {
                        $visaIdsToUpdate[] = (int) $visaData['id'];
                    }
                }

                // Get the highest existing meta_order BEFORE deleting (to ensure unique values for new visas)
                $maxMetaOrder = ClientPortalDetailAudit::where('client_id', $clientId)
                    ->whereIn('meta_key', ['visa', 'visa_country', 'visa_type', 'visa_description', 'visa_expiry_date', 'visa_grant_date'])
                    ->max('meta_order') ?? -1;

                // Get meta_order values for existing visas BEFORE deleting (if IDs provided)
                if (!empty($visaIdsToUpdate)) {
                    $existingAuditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
                        ->where('meta_key', 'visa')
                        ->whereIn('meta_type', array_map('strval', $visaIdsToUpdate))
                        ->get();

                    foreach ($existingAuditEntries as $entry) {
                        $vid = (int) $entry->meta_type;
                        if (!isset($visaIdToMetaOrderMap[$vid])) {
                            $visaIdToMetaOrderMap[$vid] = $entry->meta_order;
                        }
                    }

                    // Delete audit entries for specific visas being updated
                    if (!empty($visaIdToMetaOrderMap)) {
                        $ordersToDelete = array_values($visaIdToMetaOrderMap);
                        ClientPortalDetailAudit::where('client_id', $clientId)
                            ->whereIn('meta_key', ['visa', 'visa_country', 'visa_type', 'visa_description', 'visa_expiry_date', 'visa_grant_date'])
                            ->whereIn('meta_order', $ordersToDelete)
                            ->delete();
                    }
                }
                // Note: If all visas have id: null (new visas), no deletion happens - safe behavior

                // Track which meta_order values are being reused (to avoid conflicts with new visas)
                $usedMetaOrders = array_values($visaIdToMetaOrderMap);

                // Process each visa
                foreach ($visas as $index => $visaData) {
                    // ID field is required: if null, it's a new record; if has value, it's an update
                    $visaId = null;
                    $isNewRecord = false;
                    if (isset($visaData['id']) && $visaData['id'] !== null && $visaData['id'] !== '') {
                        $visaId = (int) $visaData['id'];
                    } else {
                        // Generate timestamp-based 10-digit ID for new record
                        $visaId = $this->generateTimestampBasedId();
                        $isNewRecord = true;
                    }
                    
                    $visaType = $visaData['visa_type'] ?? null;
                    $visaDescription = $visaData['visa_description'] ?? null;
                    $visaExpiryDate = $visaData['visa_expiry_date'] ?? null;
                    $visaGrantDate = $visaData['visa_grant_date'] ?? null;

                    // Skip if visa_type is not provided
                    if (empty($visaType)) {
                        continue;
                    }

                    // Determine action: 'create' for new records, 'update' for existing ones
                    $action = $isNewRecord ? 'create' : 'update';

                    // Determine meta_order: use existing if updating by ID, otherwise use next available
                    if (!$isNewRecord && isset($visaIdToMetaOrderMap[$visaId])) {
                        // Use existing meta_order for this visa
                        $metaOrder = $visaIdToMetaOrderMap[$visaId];
                    } else {
                        // New visa - use next available meta_order that doesn't conflict with reused values
                        do {
                            $maxMetaOrder++;
                            $metaOrder = $maxMetaOrder;
                        } while (in_array($metaOrder, $usedMetaOrders));
                        // Track this meta_order as used to avoid conflicts with subsequent new visas
                        $usedMetaOrders[] = $metaOrder;
                    }

                    // Save visa marker
                    ClientPortalDetailAudit::create([
                        'client_id' => $clientId,
                        'meta_key' => 'visa',
                        'old_value' => null,
                        'new_value' => '1', // Marker value
                        'meta_order' => $metaOrder,
                        'meta_type' => (string) $visaId, // Store record ID (original or generated)
                        'action' => $action,
                        'updated_by' => $userId,
                        'updated_at' => now(),
                    ]);

                    // Save visa type if provided
                    if ($visaType) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'visa_type',
                            'old_value' => null,
                            'new_value' => (string) $visaType,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $visaId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save visa description if provided
                    if ($visaDescription) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'visa_description',
                            'old_value' => null,
                            'new_value' => $visaDescription,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $visaId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Convert and save expiry date if provided
                    if ($visaExpiryDate) {
                        try {
                            $date = Carbon::createFromFormat('d/m/Y', $visaExpiryDate);
                            $expiryDateDb = $date->format('Y-m-d');
                            
                            ClientPortalDetailAudit::create([
                                'client_id' => $clientId,
                                'meta_key' => 'visa_expiry_date',
                                'old_value' => null,
                                'new_value' => $expiryDateDb,
                                'meta_order' => $metaOrder,
                                'meta_type' => (string) $visaId, // Store record ID for consistency
                                'action' => $action,
                                'updated_by' => $userId,
                                'updated_at' => now(),
                            ]);
                        } catch (\Exception $e) {
                            DB::rollBack();
                            return response()->json([
                                'success' => false,
                                'message' => "Invalid date format for visa_expiry_date at index {$index}. Expected format: dd/mm/yyyy"
                            ], 422);
                        }
                    }

                    // Convert and save grant date if provided
                    if ($visaGrantDate) {
                        try {
                            $date = Carbon::createFromFormat('d/m/Y', $visaGrantDate);
                            $grantDateDb = $date->format('Y-m-d');
                            
                            ClientPortalDetailAudit::create([
                                'client_id' => $clientId,
                                'meta_key' => 'visa_grant_date',
                                'old_value' => null,
                                'new_value' => $grantDateDb,
                                'meta_order' => $metaOrder,
                                'meta_type' => (string) $visaId, // Store record ID for consistency
                                'action' => $action,
                                'updated_by' => $userId,
                                'updated_at' => now(),
                            ]);
                        } catch (\Exception $e) {
                            DB::rollBack();
                            return response()->json([
                                'success' => false,
                                'message' => "Invalid date format for visa_grant_date at index {$index}. Expected format: dd/mm/yyyy"
                            ], 422);
                        }
                    }

                    $client = Admin::find($clientId);
                    $updatedVisas[] = [
                        'id' => $visaId,
                        'visa_type' => $visaType,
                        'visa_country' => $client ? ($client->country_passport ?? null) : null,
                        'visa_description' => $visaDescription,
                        'visa_expiry_date' => $visaExpiryDate,
                        'visa_grant_date' => $visaGrantDate,
                    ];
                }

                DB::commit();

                $this->notifyStaffAndCreateActionForDetailUpdate($clientId, 'Visa detail updated');

                return response()->json([
                    'success' => true,
                    'message' => 'Visa details updated successfully',
                    'data' => [
                        'visas' => $updatedVisas
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update client email details - saves to clientportal_details_audit table
     * 
     * This API allows clients to update their email information.
     * Rules: 1) Only one Personal email is allowed, 2) Personal emails must be unique.
     * Emails are saved to clientportal_details_audit table with meta_key and meta_order.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateClientEmailDetail(Request $request)
    {
        try {
            // Get authenticated client ID from token
            $admin = $request->user();
            $clientId = (int) $admin->id;

            // Verify the authenticated client (Admin model; Staff use Staff model)
            if (!$admin instanceof Admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. This endpoint is only available for clients.'
                ], 403);
            }

            // Validate the request
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'emails' => 'required|array|min:1',
                'emails.*.id' => 'present|nullable|integer',
                'emails.*.email' => 'required|email|max:255',
                'emails.*.type' => 'required|string|in:Personal,Work,Business,Home,Other,Secondary,Additional,Sister,Brother,Father,Mother,Uncle,Auntie',
            ], [
                'emails.required' => 'At least one email is required.',
                'emails.*.id.present' => 'Email ID field is required for each email. Use null for new emails or provide the existing email ID for updates.',
                'emails.*.id.integer' => 'Email ID must be an integer or null.',
                'emails.*.email.required' => 'Email address is required for each entry.',
                'emails.*.email.email' => 'Each email must be a valid email address.',
                'emails.*.type.required' => 'Email type is required for each entry.',
                'emails.*.type.in' => 'Email type must be one of: Personal, Work, Business, Home, Other, Secondary, Additional, Sister, Brother, Father, Mother, Uncle, Auntie.',
            ]);

            // Custom validation: Ensure id field is always present and check Personal email restrictions
            $validator->after(function ($validator) use ($request, $clientId) {
                $emails = $request->input('emails', []);
                
                // Ensure id field is always present (required field)
                foreach ($emails as $index => $email) {
                    if (!array_key_exists('id', $email)) {
                        $validator->errors()->add(
                            "emails.{$index}.id",
                            "Email ID field is required. Use null for new emails or provide the existing email ID for updates."
                        );
                    }
                }
                
                $personalEmailCount = 0;
                $hasExistingPersonal = false;
                $existingPersonalEmailId = null;

                // Check if there's an existing Personal email in audit or source
                $existingEmails = $this->getEmailsData($clientId);
                foreach ($existingEmails as $existingEmail) {
                    if (strtolower($existingEmail['type'] ?? '') === 'personal') {
                        $hasExistingPersonal = true;
                        $existingPersonalEmailId = $existingEmail['id'] ?? null;
                        break;
                    }
                }

                // Count Personal emails in the request and validate
                foreach ($emails as $index => $email) {
                    $emailType = strtolower($email['type'] ?? '');
                    $emailAddress = strtolower(trim($email['email'] ?? ''));
                    
                    if ($emailType === 'personal') {
                        $personalEmailCount++;
                        
                        // Check if trying to update existing Personal email
                        if (isset($email['id']) && $email['id'] == $existingPersonalEmailId) {
                            // Get the existing email to check if values changed
                            $existingEmail = collect($existingEmails)->firstWhere('id', $email['id']);
                            if ($existingEmail) {
                                // Check if trying to change the email address
                                if (strtolower(trim($email['email'] ?? '')) !== strtolower(trim($existingEmail['email'] ?? ''))) {
                                    $validator->errors()->add(
                                        "emails.{$index}.email",
                                        "Personal email address cannot be updated. It is readonly."
                                    );
                                }
                            }
                        } else {
                            // For new Personal emails or updating to Personal type, check uniqueness
                            if (!empty($emailAddress)) {
                                // Check uniqueness in admins table (excluding current client)
                                $normalizedEmail = strtolower(trim($emailAddress));
                                $emailExistsInAdmins = DB::table('admins')
                                    ->where(DB::raw('LOWER(TRIM(email))'), '=', $normalizedEmail)
                                    ->where('id', '!=', $clientId)
                                    ->exists();
                                
                                // Check uniqueness in client_emails table (excluding current client's emails)
                                $emailExistsInEmails = DB::table('client_emails')
                                    ->where(DB::raw('LOWER(TRIM(email))'), '=', $normalizedEmail)
                                    ->where('client_id', '!=', $clientId)
                                    ->exists();
                                
                                if ($emailExistsInAdmins || $emailExistsInEmails) {
                                    $validator->errors()->add(
                                        "emails.{$index}.email",
                                        "This Personal email address already exists in the system. Personal emails must be unique."
                                    );
                                }
                            }
                        }
                        
                        // Check if trying to add a new Personal email when one already exists
                        if (!isset($email['id']) && $hasExistingPersonal) {
                            $validator->errors()->add(
                                "emails.{$index}.type",
                                "Cannot add another Personal email. Personal email already exists."
                            );
                        }
                    }
                }

                // Check if trying to add more than one Personal email in the same request
                if ($personalEmailCount > 1) {
                    $validator->errors()->add(
                        "emails",
                        "Cannot add another Personal email. Only one Personal email is allowed."
                    );
                }
            });

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $userId = Auth::id() ?? $clientId;
            $emails = $request->input('emails');
            $responseEmails = []; // Only emails from the original request

            DB::beginTransaction();

            try {
                // Get existing emails to check for Personal email protection
                $existingEmails = $this->getEmailsData($clientId);
                $existingPersonalEmailId = null;
                $existingPersonalEmailData = null;
                foreach ($existingEmails as $existingEmail) {
                    if (strtolower($existingEmail['type'] ?? '') === 'personal') {
                        $existingPersonalEmailId = $existingEmail['id'] ?? null;
                        $existingPersonalEmailData = $existingEmail;
                        break;
                    }
                }

                // Check if existing Personal email is included in the request
                $personalEmailInRequest = false;
                if ($existingPersonalEmailId) {
                    foreach ($emails as $emailData) {
                        if (isset($emailData['id']) && $emailData['id'] == $existingPersonalEmailId) {
                            $personalEmailInRequest = true;
                            break;
                        }
                    }
                }

                // Get existing email IDs from request to identify which ones to update
                $emailIdsToUpdate = [];
                $emailIdToMetaOrderMap = []; // Map email ID to its meta_order
                
                foreach ($emails as $emailData) {
                    // ID field is required - if it has a value (not null), it's an update; if null, it's a new record
                    if (isset($emailData['id']) && $emailData['id'] !== null && $emailData['id'] !== '') {
                        $emailIdsToUpdate[] = (int) $emailData['id'];
                    }
                }

                // Get the highest existing meta_order BEFORE deleting (to ensure unique values for new emails)
                $maxMetaOrder = ClientPortalDetailAudit::where('client_id', $clientId)
                    ->whereIn('meta_key', ['email', 'email_type'])
                    ->max('meta_order') ?? -1;

                // Get meta_order values for existing emails BEFORE deleting (if IDs provided)
                if (!empty($emailIdsToUpdate)) {
                    $existingAuditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
                        ->where('meta_key', 'email')
                        ->whereIn('meta_type', array_map('strval', $emailIdsToUpdate))
                        ->get();

                    foreach ($existingAuditEntries as $entry) {
                        $eid = (int) $entry->meta_type;
                        if (!isset($emailIdToMetaOrderMap[$eid])) {
                            $emailIdToMetaOrderMap[$eid] = $entry->meta_order;
                        }
                    }

                    // Delete existing email audit entries only for emails that are in the request
                    // This preserves the Personal email's audit entries if it wasn't in the request
                    if (!empty($emailIdToMetaOrderMap)) {
                        $ordersToDelete = array_values($emailIdToMetaOrderMap);
                        ClientPortalDetailAudit::where('client_id', $clientId)
                            ->whereIn('meta_key', ['email', 'email_type'])
                            ->whereIn('meta_order', $ordersToDelete)
                            ->delete();
                    }
                } else {
                    // If no email IDs in request (all are new), delete all email audit entries
                    ClientPortalDetailAudit::where('client_id', $clientId)
                        ->whereIn('meta_key', ['email', 'email_type'])
                        ->delete();
                }
                // Note: If all emails have id: null (new emails), no deletion happens - safe behavior

                // Track which meta_order values are being reused (to avoid conflicts with new emails)
                $usedMetaOrders = array_values($emailIdToMetaOrderMap);

                // Process only emails from the original request (don't add Personal email to processing)
                $emailsToProcess = $emails;

                // Process each email from the original request only
                foreach ($emailsToProcess as $index => $emailData) {
                    // ID field is required: if null, it's a new record; if has value, it's an update
                    $emailId = null;
                    $isNewRecord = false;
                    if (isset($emailData['id']) && $emailData['id'] !== null && $emailData['id'] !== '') {
                        $emailId = (int) $emailData['id'];
                    } else {
                        // Generate timestamp-based 10-digit ID for new record
                        $emailId = $this->generateTimestampBasedId();
                        $isNewRecord = true;
                    }
                    
                    $emailAddress = $emailData['email'] ?? null;
                    $emailType = $emailData['type'] ?? 'Personal';

                    if (empty($emailAddress)) {
                        continue; // Skip if email is empty
                    }

                    // Check if this is the existing Personal email - if so, use original data
                    $isExistingPersonal = false;
                    if (!$isNewRecord && $existingPersonalEmailId && $emailId == $existingPersonalEmailId && 
                        strtolower($emailType) === 'personal') {
                        $isExistingPersonal = true;
                        // Use original Personal email data (readonly)
                        if ($existingPersonalEmailData) {
                            $emailAddress = $existingPersonalEmailData['email'] ?? $emailAddress;
                            $emailType = $existingPersonalEmailData['type'] ?? $emailType;
                        }
                    }

                    // Determine action: 'create' for new records, 'update' for existing ones
                    $action = $isNewRecord ? 'create' : 'update';

                    // Determine meta_order: use existing if updating by ID, otherwise use next available
                    if (!$isNewRecord && isset($emailIdToMetaOrderMap[$emailId])) {
                        // Use existing meta_order for this email
                        $metaOrder = $emailIdToMetaOrderMap[$emailId];
                    } else {
                        // New email - use next available meta_order that doesn't conflict with reused values
                        do {
                            $maxMetaOrder++;
                            $metaOrder = $maxMetaOrder;
                        } while (in_array($metaOrder, $usedMetaOrders));
                        // Track this meta_order as used to avoid conflicts with subsequent new emails
                        $usedMetaOrders[] = $metaOrder;
                    }

                    // Save email address - store record ID in meta_type (original ID for updates, generated ID for new records)
                    ClientPortalDetailAudit::create([
                        'client_id' => $clientId,
                        'meta_key' => 'email',
                        'old_value' => null,
                        'new_value' => $emailAddress,
                        'meta_order' => $metaOrder,
                        'meta_type' => (string) $emailId, // Store record ID (original or generated)
                        'action' => $action,
                        'updated_by' => $userId,
                        'updated_at' => now(),
                    ]);

                    // Save email type
                    ClientPortalDetailAudit::create([
                        'client_id' => $clientId,
                        'meta_key' => 'email_type',
                        'old_value' => null,
                        'new_value' => $emailType,
                        'meta_order' => $metaOrder,
                        'meta_type' => (string) $emailId, // Store record ID for consistency
                        'action' => $action,
                        'updated_by' => $userId,
                        'updated_at' => now(),
                    ]);

                    // Add to response (all emails in $emailsToProcess are from original request)
                    $responseEmails[] = [
                        'id' => $emailId,
                        'email' => $emailAddress,
                        'type' => $emailType,
                    ];
                }

                DB::commit();

                $this->notifyStaffAndCreateActionForDetailUpdate($clientId, 'Email detail updated');

                return response()->json([
                    'success' => true,
                    'message' => 'Email details updated successfully',
                    'data' => [
                        'emails' => $responseEmails
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update client address details - saves to clientportal_details_audit table
     * 
     * This API allows clients to update their address information.
     * Addresses are saved to clientportal_details_audit table with meta_key and meta_order.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateClientAddressDetail(Request $request)
    {
        try {
            // Get authenticated client ID from token
            $admin = $request->user();
            $clientId = (int) $admin->id;

            // Verify the authenticated client (Admin model; Staff use Staff model)
            if (!$admin instanceof Admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. This endpoint is only available for clients.'
                ], 403);
            }

            // Validate the request
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'addresses' => 'required|array|min:1',
                'addresses.*.id' => 'present|nullable|integer',
                'addresses.*.search_address' => 'nullable|string|max:500',
                'addresses.*.address_line_1' => 'required|string|max:255',
                'addresses.*.address_line_2' => 'nullable|string|max:255',
                'addresses.*.suburb' => 'required|string|max:100',
                'addresses.*.state' => 'required|string|max:100',
                'addresses.*.postcode' => 'required|string|max:20',
                'addresses.*.country' => 'required|string|max:100',
                'addresses.*.regional_code' => 'nullable|string|max:255',
                'addresses.*.start_date' => 'nullable|date_format:d/m/Y',
                'addresses.*.end_date' => 'nullable|date_format:d/m/Y',
                'addresses.*.is_current' => 'nullable|boolean',
            ], [
                'addresses.required' => 'At least one address is required.',
                'addresses.*.id.present' => 'Address ID field is required for each address. Use null for new addresses or provide the existing address ID for updates.',
                'addresses.*.id.integer' => 'Address ID must be an integer or null.',
                'addresses.*.address_line_1.required' => 'Address Line 1 is required for each address.',
                'addresses.*.suburb.required' => 'Suburb is required for each address.',
                'addresses.*.state.required' => 'State is required for each address.',
                'addresses.*.postcode.required' => 'Postcode is required for each address.',
                'addresses.*.country.required' => 'Country is required for each address.',
                'addresses.*.start_date.date_format' => 'Start date must be in dd/mm/yyyy format.',
                'addresses.*.end_date.date_format' => 'End date must be in dd/mm/yyyy format.',
            ]);

            // Custom validation: Ensure id field is always present and check date logic
            $validator->after(function ($validator) use ($request) {
                $addresses = $request->input('addresses', []);
                foreach ($addresses as $index => $address) {
                    // Ensure id field is always present (required field)
                    if (!array_key_exists('id', $address)) {
                        $validator->errors()->add(
                            "addresses.{$index}.id",
                            "Address ID field is required. Use null for new addresses or provide the existing address ID for updates."
                        );
                    }
                    
                    // Validate date logic: end date must be after or equal to start date
                    if (!empty($address['start_date']) && !empty($address['end_date'])) {
                        try {
                            $startDate = Carbon::createFromFormat('d/m/Y', $address['start_date']);
                            $endDate = Carbon::createFromFormat('d/m/Y', $address['end_date']);
                            
                            if ($endDate->lt($startDate)) {
                                $validator->errors()->add(
                                    "addresses.{$index}.end_date",
                                    "End date must be after or equal to start date."
                                );
                            }
                        } catch (\Exception $e) {
                            // Date format validation already handled above
                        }
                    }
                    // Validate: start_date and end_date cannot be future dates
                    if (!empty($address['start_date'])) {
                        try {
                            $startDate = Carbon::createFromFormat('d/m/Y', $address['start_date']);
                            if ($startDate->isFuture()) {
                                $validator->errors()->add(
                                    "addresses.{$index}.start_date",
                                    "The address start date cannot be a future date."
                                );
                            }
                        } catch (\Exception $e) {}
                    }
                    if (!empty($address['end_date'])) {
                        try {
                            $endDate = Carbon::createFromFormat('d/m/Y', $address['end_date']);
                            if ($endDate->isFuture()) {
                                $validator->errors()->add(
                                    "addresses.{$index}.end_date",
                                    "The address end date cannot be a future date."
                                );
                            }
                        } catch (\Exception $e) {}
                    }
                }
            });

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $userId = Auth::id() ?? $clientId;
            $addresses = $request->input('addresses');
            $updatedAddresses = [];

            DB::beginTransaction();

            try {
                // Get existing address IDs from request to identify which ones to update
                $addressIdsToUpdate = [];
                $addressIdToMetaOrderMap = []; // Map address ID to its meta_order
                
                foreach ($addresses as $addressData) {
                    // ID field is required - if it has a value (not null), it's an update; if null, it's a new record
                    if (isset($addressData['id']) && $addressData['id'] !== null && $addressData['id'] !== '') {
                        $addressIdsToUpdate[] = (int) $addressData['id'];
                    }
                }

                // Get the highest existing meta_order BEFORE deleting (to ensure unique values for new addresses)
                $maxMetaOrder = ClientPortalDetailAudit::where('client_id', $clientId)
                    ->whereIn('meta_key', ['address', 'address_line_1', 'address_line_2', 'address_suburb', 'address_state', 'address_postcode', 'address_country', 'address_regional_code', 'address_start_date', 'address_end_date', 'address_is_current'])
                    ->max('meta_order') ?? -1;

                // Get meta_order values for existing addresses BEFORE deleting (if IDs provided)
                if (!empty($addressIdsToUpdate)) {
                    $existingAuditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
                        ->where('meta_key', 'address_line_1')
                        ->whereIn('meta_type', array_map('strval', $addressIdsToUpdate))
                        ->get();

                    foreach ($existingAuditEntries as $entry) {
                        $aid = (int) $entry->meta_type;
                        if (!isset($addressIdToMetaOrderMap[$aid])) {
                            $addressIdToMetaOrderMap[$aid] = $entry->meta_order;
                        }
                    }

                    // Delete audit entries for specific addresses being updated
                    if (!empty($addressIdToMetaOrderMap)) {
                        $ordersToDelete = array_values($addressIdToMetaOrderMap);
                        ClientPortalDetailAudit::where('client_id', $clientId)
                            ->whereIn('meta_key', ['address', 'address_line_1', 'address_line_2', 'address_suburb', 'address_state', 'address_postcode', 'address_country', 'address_regional_code', 'address_start_date', 'address_end_date', 'address_is_current'])
                            ->whereIn('meta_order', $ordersToDelete)
                            ->delete();
                    }
                }
                // Note: If all addresses have id: null (new addresses), no deletion happens - safe behavior

                // Track which meta_order values are being reused (to avoid conflicts with new addresses)
                $usedMetaOrders = array_values($addressIdToMetaOrderMap);

                // Process each address
                foreach ($addresses as $index => $addressData) {
                    $searchAddress = $addressData['search_address'] ?? null;
                    $addressLine1 = $addressData['address_line_1'] ?? null;
                    $addressLine2 = $addressData['address_line_2'] ?? null;
                    $suburb = $addressData['suburb'] ?? null;
                    $state = $addressData['state'] ?? null;
                    $postcode = $addressData['postcode'] ?? null;
                    $country = $addressData['country'] ?? null;
                    $regionalCode = $addressData['regional_code'] ?? null;
                    $startDate = $addressData['start_date'] ?? null;
                    $endDate = $addressData['end_date'] ?? null;
                    $isCurrent = isset($addressData['is_current']) ? (bool) $addressData['is_current'] : false;
                    $addressId = $addressData['id'] ?? null;

                    if (empty($addressLine1)) {
                        continue; // Skip if address_line_1 is empty
                    }

                    // Determine if this is a new record and set action
                    $isNewRecord = ($addressId === null || $addressId === '');
                    $action = $isNewRecord ? 'create' : 'update';
                    
                    // Generate timestamp-based ID for new records
                    if ($isNewRecord) {
                        $addressId = $this->generateTimestampBasedId();
                    } else {
                        $addressId = (int) $addressId;
                    }

                    // Determine meta_order: use existing if updating by ID, otherwise use next available
                    if (!$isNewRecord && isset($addressIdToMetaOrderMap[$addressId])) {
                        // Use existing meta_order for this address
                        $metaOrder = $addressIdToMetaOrderMap[$addressId];
                    } else {
                        // New address - use next available meta_order that doesn't conflict with reused values
                        do {
                            $maxMetaOrder++;
                            $metaOrder = $maxMetaOrder;
                        } while (in_array($metaOrder, $usedMetaOrders));
                        // Track this meta_order as used to avoid conflicts with subsequent new addresses
                        $usedMetaOrders[] = $metaOrder;
                    }

                    // Convert dates from dd/mm/yyyy to Y-m-d format
                    $startDateDb = null;
                    if ($startDate) {
                        try {
                            $date = Carbon::createFromFormat('d/m/Y', $startDate);
                            $startDateDb = $date->format('Y-m-d');
                        } catch (\Exception $e) {
                            DB::rollBack();
                            return response()->json([
                                'success' => false,
                                'message' => "Invalid date format for start_date at index {$index}. Expected format: dd/mm/yyyy"
                            ], 422);
                        }
                    }

                    $endDateDb = null;
                    if ($endDate) {
                        try {
                            $date = Carbon::createFromFormat('d/m/Y', $endDate);
                            $endDateDb = $date->format('Y-m-d');
                        } catch (\Exception $e) {
                            DB::rollBack();
                            return response()->json([
                                'success' => false,
                                'message' => "Invalid date format for end_date at index {$index}. Expected format: dd/mm/yyyy"
                            ], 422);
                        }
                    }

                    // Save search address if provided
                    if ($searchAddress) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'address',
                            'old_value' => null,
                            'new_value' => $searchAddress,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $addressId, // Store record ID (original or generated)
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save address_line_1 - store original record ID in meta_type
                    
                    ClientPortalDetailAudit::create([
                        'client_id' => $clientId,
                        'meta_key' => 'address_line_1',
                        'old_value' => null,
                        'new_value' => $addressLine1,
                        'meta_order' => $metaOrder,
                        'meta_type' => (string) $addressId, // Store original record ID or generated ID
                        'action' => $action,
                        'updated_by' => $userId,
                        'updated_at' => now(),
                    ]);

                    // Save address_line_2 if provided
                    if ($addressLine2) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'address_line_2',
                            'old_value' => null,
                            'new_value' => $addressLine2,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $addressId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save suburb
                    ClientPortalDetailAudit::create([
                        'client_id' => $clientId,
                        'meta_key' => 'address_suburb',
                        'old_value' => null,
                        'new_value' => $suburb,
                        'meta_order' => $metaOrder,
                        'meta_type' => (string) $addressId, // Store record ID for consistency
                        'action' => $action,
                        'updated_by' => $userId,
                        'updated_at' => now(),
                    ]);

                    // Save state
                    ClientPortalDetailAudit::create([
                        'client_id' => $clientId,
                        'meta_key' => 'address_state',
                        'old_value' => null,
                        'new_value' => $state,
                        'meta_order' => $metaOrder,
                        'meta_type' => (string) $addressId, // Store record ID for consistency
                        'action' => $action,
                        'updated_by' => $userId,
                        'updated_at' => now(),
                    ]);

                    // Save postcode
                    ClientPortalDetailAudit::create([
                        'client_id' => $clientId,
                        'meta_key' => 'address_postcode',
                        'old_value' => null,
                        'new_value' => $postcode,
                        'meta_order' => $metaOrder,
                        'meta_type' => (string) $addressId, // Store record ID for consistency
                        'action' => $action,
                        'updated_by' => $userId,
                        'updated_at' => now(),
                    ]);

                    // Save country
                    ClientPortalDetailAudit::create([
                        'client_id' => $clientId,
                        'meta_key' => 'address_country',
                        'old_value' => null,
                        'new_value' => $country,
                        'meta_order' => $metaOrder,
                        'meta_type' => (string) $addressId, // Store record ID for consistency
                        'action' => $action,
                        'updated_by' => $userId,
                        'updated_at' => now(),
                    ]);

                    // Save regional_code if provided
                    if ($regionalCode) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'address_regional_code',
                            'old_value' => null,
                            'new_value' => $regionalCode,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $addressId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save start_date if provided
                    if ($startDateDb) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'address_start_date',
                            'old_value' => null,
                            'new_value' => $startDateDb,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $addressId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save end_date if provided
                    if ($endDateDb) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'address_end_date',
                            'old_value' => null,
                            'new_value' => $endDateDb,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $addressId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save is_current
                    ClientPortalDetailAudit::create([
                        'client_id' => $clientId,
                        'meta_key' => 'address_is_current',
                        'old_value' => null,
                        'new_value' => $isCurrent ? '1' : '0',
                        'meta_order' => $metaOrder,
                        'meta_type' => (string) $addressId, // Store record ID for consistency
                        'action' => $action,
                        'updated_by' => $userId,
                        'updated_at' => now(),
                    ]);

                    $updatedAddresses[] = [
                        'id' => $addressId,
                        'search_address' => $searchAddress,
                        'address_line_1' => $addressLine1,
                        'address_line_2' => $addressLine2,
                        'suburb' => $suburb,
                        'state' => $state,
                        'postcode' => $postcode,
                        'country' => $country,
                        'regional_code' => $regionalCode,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'is_current' => $isCurrent,
                    ];
                }

                DB::commit();

                $this->notifyStaffAndCreateActionForDetailUpdate($clientId, 'Address detail updated');

                return response()->json([
                    'success' => true,
                    'message' => 'Address details updated successfully',
                    'data' => [
                        'addresses' => $updatedAddresses
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update client travel details - saves to clientportal_details_audit table
     * 
     * This API allows clients to update their travel information.
     * Travels are saved to clientportal_details_audit table with meta_key and meta_order.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateClientTravelDetail(Request $request)
    {
        try {
            // Get authenticated client ID from token
            $admin = $request->user();
            $clientId = (int) $admin->id;

            // Verify the authenticated client (Admin model; Staff use Staff model)
            if (!$admin instanceof Admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. This endpoint is only available for clients.'
                ], 403);
            }

            // Validate the request
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'travels' => 'required|array|min:1',
                'travels.*.id' => 'present|nullable|integer',
                'travels.*.country_visited' => 'required|string|max:255',
                'travels.*.arrival_date' => 'nullable|date_format:d/m/Y',
                'travels.*.departure_date' => 'nullable|date_format:d/m/Y',
                'travels.*.purpose' => 'nullable|string|max:500',
            ], [
                'travels.required' => 'At least one travel entry is required.',
                'travels.*.id.present' => 'Travel ID field is required for each travel. Use null for new travels or provide the existing travel ID for updates.',
                'travels.*.id.integer' => 'Travel ID must be an integer or null.',
                'travels.*.country_visited.required' => 'Country visited is required for each travel entry.',
                'travels.*.arrival_date.date_format' => 'Arrival date must be in dd/mm/yyyy format.',
                'travels.*.departure_date.date_format' => 'Departure date must be in dd/mm/yyyy format.',
            ]);

            // Custom validation: Ensure id field is always present and check date logic
            $validator->after(function ($validator) use ($request) {
                $travels = $request->input('travels', []);
                foreach ($travels as $index => $travel) {
                    // Ensure id field is always present (required field)
                    if (!array_key_exists('id', $travel)) {
                        $validator->errors()->add(
                            "travels.{$index}.id",
                            "Travel ID field is required. Use null for new travels or provide the existing travel ID for updates."
                        );
                    }
                    
                    if (!empty($travel['arrival_date']) && !empty($travel['departure_date'])) {
                        try {
                            $arrivalDate = Carbon::createFromFormat('d/m/Y', $travel['arrival_date']);
                            $departureDate = Carbon::createFromFormat('d/m/Y', $travel['departure_date']);
                            
                            if ($departureDate->lt($arrivalDate)) {
                                $validator->errors()->add(
                                    "travels.{$index}.departure_date",
                                    "Departure date must be after or equal to arrival date."
                                );
                            }
                        } catch (\Exception $e) {
                            // Date format validation already handled above
                        }
                    }
                    // Validate: travel dates cannot be future dates
                    if (!empty($travel['arrival_date'])) {
                        try {
                            $arrivalDate = Carbon::createFromFormat('d/m/Y', $travel['arrival_date']);
                            if ($arrivalDate->isFuture()) {
                                $validator->errors()->add(
                                    "travels.{$index}.arrival_date",
                                    "The travel arrival date cannot be a future date."
                                );
                            }
                        } catch (\Exception $e) {}
                    }
                    if (!empty($travel['departure_date'])) {
                        try {
                            $departureDate = Carbon::createFromFormat('d/m/Y', $travel['departure_date']);
                            if ($departureDate->isFuture()) {
                                $validator->errors()->add(
                                    "travels.{$index}.departure_date",
                                    "The travel departure date cannot be a future date."
                                );
                            }
                        } catch (\Exception $e) {}
                    }
                }
            });

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $userId = Auth::id() ?? $clientId;
            $travels = $request->input('travels');
            $updatedTravels = [];

            DB::beginTransaction();

            try {
                // Get existing travel IDs from request to identify which ones to update
                $travelIdsToUpdate = [];
                $travelIdToMetaOrderMap = []; // Map travel ID to its meta_order
                
                foreach ($travels as $travelData) {
                    // ID field is required - if it has a value (not null), it's an update; if null, it's a new record
                    if (isset($travelData['id']) && $travelData['id'] !== null && $travelData['id'] !== '') {
                        $travelIdsToUpdate[] = (int) $travelData['id'];
                    }
                }

                // Get the highest existing meta_order BEFORE deleting (to ensure unique values for new travels)
                $maxMetaOrder = ClientPortalDetailAudit::where('client_id', $clientId)
                    ->whereIn('meta_key', ['travel', 'travel_country_visited', 'travel_arrival_date', 'travel_departure_date', 'travel_purpose'])
                    ->max('meta_order') ?? -1;

                // Get meta_order values for existing travels BEFORE deleting (if IDs provided)
                if (!empty($travelIdsToUpdate)) {
                    $existingAuditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
                        ->where('meta_key', 'travel')
                        ->whereIn('meta_type', array_map('strval', $travelIdsToUpdate))
                        ->get();

                    foreach ($existingAuditEntries as $entry) {
                        $tid = (int) $entry->meta_type;
                        if (!isset($travelIdToMetaOrderMap[$tid])) {
                            $travelIdToMetaOrderMap[$tid] = $entry->meta_order;
                        }
                    }

                    // Delete audit entries for specific travels being updated
                    if (!empty($travelIdToMetaOrderMap)) {
                        $ordersToDelete = array_values($travelIdToMetaOrderMap);
                        ClientPortalDetailAudit::where('client_id', $clientId)
                            ->whereIn('meta_key', ['travel', 'travel_country_visited', 'travel_arrival_date', 'travel_departure_date', 'travel_purpose'])
                            ->whereIn('meta_order', $ordersToDelete)
                            ->delete();
                    }
                }
                // Note: If all travels have id: null (new travels), no deletion happens - safe behavior

                // Track which meta_order values are being reused (to avoid conflicts with new travels)
                $usedMetaOrders = array_values($travelIdToMetaOrderMap);

                // Process each travel
                foreach ($travels as $index => $travelData) {
                    // ID field is required: if null, it's a new record; if has value, it's an update
                    $travelId = null;
                    $isNewRecord = false;
                    if (isset($travelData['id']) && $travelData['id'] !== null && $travelData['id'] !== '') {
                        $travelId = (int) $travelData['id'];
                    } else {
                        // Generate timestamp-based 10-digit ID for new record
                        $travelId = $this->generateTimestampBasedId();
                        $isNewRecord = true;
                    }
                    
                    $countryVisited = $travelData['country_visited'] ?? null;
                    $arrivalDate = $travelData['arrival_date'] ?? null;
                    $departureDate = $travelData['departure_date'] ?? null;
                    $purpose = $travelData['purpose'] ?? null;

                    if (empty($countryVisited)) {
                        continue; // Skip if country_visited is empty
                    }

                    // Determine action: 'create' for new records, 'update' for existing ones
                    $action = $isNewRecord ? 'create' : 'update';

                    // Determine meta_order: use existing if updating by ID, otherwise use next available
                    if (!$isNewRecord && isset($travelIdToMetaOrderMap[$travelId])) {
                        // Use existing meta_order for this travel
                        $metaOrder = $travelIdToMetaOrderMap[$travelId];
                    } else {
                        // New travel - use next available meta_order that doesn't conflict with reused values
                        do {
                            $maxMetaOrder++;
                            $metaOrder = $maxMetaOrder;
                        } while (in_array($metaOrder, $usedMetaOrders));
                        // Track this meta_order as used to avoid conflicts with subsequent new travels
                        $usedMetaOrders[] = $metaOrder;
                    }

                    // Convert dates from dd/mm/yyyy to Y-m-d format
                    $arrivalDateDb = null;
                    if ($arrivalDate) {
                        try {
                            $date = Carbon::createFromFormat('d/m/Y', $arrivalDate);
                            $arrivalDateDb = $date->format('Y-m-d');
                        } catch (\Exception $e) {
                            DB::rollBack();
                            return response()->json([
                                'success' => false,
                                'message' => "Invalid date format for arrival_date at index {$index}. Expected format: dd/mm/yyyy"
                            ], 422);
                        }
                    }

                    $departureDateDb = null;
                    if ($departureDate) {
                        try {
                            $date = Carbon::createFromFormat('d/m/Y', $departureDate);
                            $departureDateDb = $date->format('Y-m-d');
                        } catch (\Exception $e) {
                            DB::rollBack();
                            return response()->json([
                                'success' => false,
                                'message' => "Invalid date format for departure_date at index {$index}. Expected format: dd/mm/yyyy"
                            ], 422);
                        }
                    }

                    // Save travel marker (to indicate existence of this travel entry)
                    ClientPortalDetailAudit::create([
                        'client_id' => $clientId,
                        'meta_key' => 'travel',
                        'old_value' => null,
                        'new_value' => '1', // Just a marker
                        'meta_order' => $metaOrder,
                        'meta_type' => (string) $travelId, // Store record ID (original or generated)
                        'action' => $action,
                        'updated_by' => $userId,
                        'updated_at' => now(),
                    ]);

                    // Save country visited - store record ID in meta_type
                    ClientPortalDetailAudit::create([
                        'client_id' => $clientId,
                        'meta_key' => 'travel_country_visited',
                        'old_value' => null,
                        'new_value' => $countryVisited,
                        'meta_order' => $metaOrder,
                        'meta_type' => (string) $travelId, // Store record ID (original or generated)
                        'action' => $action,
                        'updated_by' => $userId,
                        'updated_at' => now(),
                    ]);

                    // Save arrival date if provided
                    if ($arrivalDateDb) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'travel_arrival_date',
                            'old_value' => null,
                            'new_value' => $arrivalDateDb,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $travelId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save departure date if provided
                    if ($departureDateDb) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'travel_departure_date',
                            'old_value' => null,
                            'new_value' => $departureDateDb,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $travelId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save purpose if provided
                    if ($purpose) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'travel_purpose',
                            'old_value' => null,
                            'new_value' => $purpose,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $travelId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    $updatedTravels[] = [
                        'id' => $travelId,
                        'country_visited' => $countryVisited,
                        'arrival_date' => $arrivalDate,
                        'departure_date' => $departureDate,
                        'purpose' => $purpose,
                    ];
                }

                DB::commit();

                $this->notifyStaffAndCreateActionForDetailUpdate($clientId, 'Travel detail updated');

                return response()->json([
                    'success' => true,
                    'message' => 'Travel details updated successfully',
                    'data' => [
                        'travels' => $updatedTravels
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update client qualification details - saves to clientportal_details_audit table
     * 
     * This API allows clients to update their qualification information.
     * Qualifications are saved to clientportal_details_audit table with meta_key and meta_order.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateClientQualificationDetail(Request $request)
    {
        try {
            // Get authenticated client ID from token
            $admin = $request->user();
            $clientId = (int) $admin->id;

            // Verify the authenticated client (Admin model; Staff use Staff model)
            if (!$admin instanceof Admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. This endpoint is only available for clients.'
                ], 403);
            }

            // Validate the request
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'qualifications' => 'required|array|min:1',
                'qualifications.*.id' => 'present|nullable|integer',
                'qualifications.*.level' => 'nullable|string|max:255',
                'qualifications.*.name' => 'nullable|string|max:255',
                'qualifications.*.college_name' => 'nullable|string|max:255',
                'qualifications.*.campus' => 'nullable|string|max:255',
                'qualifications.*.country' => 'nullable|string|max:255',
                'qualifications.*.state' => 'nullable|string|max:255',
                'qualifications.*.start_date' => 'nullable|date_format:d/m/Y',
                'qualifications.*.finish_date' => 'nullable|date_format:d/m/Y',
                'qualifications.*.relevant_qualification' => 'nullable|boolean',
            ], [
                'qualifications.required' => 'At least one qualification entry is required.',
                'qualifications.*.id.present' => 'Qualification ID field is required for each qualification. Use null for new qualifications or provide the existing qualification ID for updates.',
                'qualifications.*.id.integer' => 'Qualification ID must be an integer or null.',
                'qualifications.*.start_date.date_format' => 'Start date must be in dd/mm/yyyy format.',
                'qualifications.*.finish_date.date_format' => 'Finish date must be in dd/mm/yyyy format.',
            ]);

            // Custom validation: Ensure id field is always present and check date logic
            $validator->after(function ($validator) use ($request) {
                $qualifications = $request->input('qualifications', []);
                foreach ($qualifications as $index => $qualification) {
                    // Ensure id field is always present (required field)
                    if (!array_key_exists('id', $qualification)) {
                        $validator->errors()->add(
                            "qualifications.{$index}.id",
                            "Qualification ID field is required. Use null for new qualifications or provide the existing qualification ID for updates."
                        );
                    }
                    
                    if (!empty($qualification['start_date']) && !empty($qualification['finish_date'])) {
                        try {
                            $startDate = Carbon::createFromFormat('d/m/Y', $qualification['start_date']);
                            $finishDate = Carbon::createFromFormat('d/m/Y', $qualification['finish_date']);
                            
                            if ($finishDate->lt($startDate)) {
                                $validator->errors()->add(
                                    "qualifications.{$index}.finish_date",
                                    "Finish date must be after or equal to start date."
                                );
                            }
                        } catch (\Exception $e) {
                            // Date format validation already handled above
                        }
                    }
                }
            });

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $userId = Auth::id() ?? $clientId;
            $qualifications = $request->input('qualifications');
            $updatedQualifications = [];

            DB::beginTransaction();

            try {
                // Get existing qualification IDs from request to identify which ones to update
                $qualificationIdsToUpdate = [];
                $qualificationIdToMetaOrderMap = []; // Map qualification ID to its meta_order
                
                foreach ($qualifications as $qualificationData) {
                    // ID field is required - if it has a value (not null), it's an update; if null, it's a new record
                    if (isset($qualificationData['id']) && $qualificationData['id'] !== null && $qualificationData['id'] !== '') {
                        $qualificationIdsToUpdate[] = (int) $qualificationData['id'];
                    }
                }

                // Get the highest existing meta_order BEFORE deleting (to ensure unique values for new qualifications)
                $maxMetaOrder = ClientPortalDetailAudit::where('client_id', $clientId)
                    ->whereIn('meta_key', ['qualification', 'qualification_level', 'qualification_name', 'qualification_college_name', 'qualification_campus', 'qualification_country', 'qualification_state', 'qualification_start_date', 'qualification_finish_date', 'qualification_relevant', 'qualification_specialist_education', 'qualification_stem', 'qualification_regional_study'])
                    ->max('meta_order') ?? -1;

                // Get meta_order values for existing qualifications BEFORE deleting (if IDs provided)
                if (!empty($qualificationIdsToUpdate)) {
                    $existingAuditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
                        ->where('meta_key', 'qualification')
                        ->whereIn('meta_type', array_map('strval', $qualificationIdsToUpdate))
                        ->get();

                    foreach ($existingAuditEntries as $entry) {
                        $qid = (int) $entry->meta_type;
                        if (!isset($qualificationIdToMetaOrderMap[$qid])) {
                            $qualificationIdToMetaOrderMap[$qid] = $entry->meta_order;
                        }
                    }

                    // Delete audit entries for specific qualifications being updated
                    if (!empty($qualificationIdToMetaOrderMap)) {
                        $ordersToDelete = array_values($qualificationIdToMetaOrderMap);
                        ClientPortalDetailAudit::where('client_id', $clientId)
                            ->whereIn('meta_key', ['qualification', 'qualification_level', 'qualification_name', 'qualification_college_name', 'qualification_campus', 'qualification_country', 'qualification_state', 'qualification_start_date', 'qualification_finish_date', 'qualification_relevant', 'qualification_specialist_education', 'qualification_stem', 'qualification_regional_study'])
                            ->whereIn('meta_order', $ordersToDelete)
                            ->delete();
                    }
                }
                // Note: If all qualifications have id: null (new qualifications), no deletion happens - safe behavior

                // Track which meta_order values are being reused (to avoid conflicts with new qualifications)
                $usedMetaOrders = array_values($qualificationIdToMetaOrderMap);

                // Process each qualification
                foreach ($qualifications as $index => $qualificationData) {
                    // ID field is required: if null, it's a new record; if has value, it's an update
                    $qualificationId = null;
                    $isNewRecord = false;
                    if (isset($qualificationData['id']) && $qualificationData['id'] !== null && $qualificationData['id'] !== '') {
                        $qualificationId = (int) $qualificationData['id'];
                    } else {
                        // Generate timestamp-based 10-digit ID for new record
                        $qualificationId = $this->generateTimestampBasedId();
                        $isNewRecord = true;
                    }
                    
                    $level = $qualificationData['level'] ?? null;
                    $name = $qualificationData['name'] ?? null;
                    $collegeName = $qualificationData['college_name'] ?? null;
                    $campus = $qualificationData['campus'] ?? null;
                    $country = $qualificationData['country'] ?? null;
                    $state = $qualificationData['state'] ?? null;
                    $startDate = $qualificationData['start_date'] ?? null;
                    $finishDate = $qualificationData['finish_date'] ?? null;
                    $relevantQualification = isset($qualificationData['relevant_qualification']) ? (bool) $qualificationData['relevant_qualification'] : false;

                    // Skip if both level and name are empty
                    if (empty($level) && empty($name)) {
                        continue;
                    }

                    // Determine action: 'create' for new records, 'update' for existing ones
                    $action = $isNewRecord ? 'create' : 'update';

                    // Determine meta_order: use existing if updating by ID, otherwise use next available
                    if (!$isNewRecord && isset($qualificationIdToMetaOrderMap[$qualificationId])) {
                        // Use existing meta_order for this qualification
                        $metaOrder = $qualificationIdToMetaOrderMap[$qualificationId];
                    } else {
                        // New qualification - use next available meta_order that doesn't conflict with reused values
                        do {
                            $maxMetaOrder++;
                            $metaOrder = $maxMetaOrder;
                        } while (in_array($metaOrder, $usedMetaOrders));
                        // Track this meta_order as used to avoid conflicts with subsequent new qualifications
                        $usedMetaOrders[] = $metaOrder;
                    }

                    // Convert dates from dd/mm/yyyy to Y-m-d format
                    $startDateDb = null;
                    if ($startDate) {
                        try {
                            $date = Carbon::createFromFormat('d/m/Y', $startDate);
                            $startDateDb = $date->format('Y-m-d');
                        } catch (\Exception $e) {
                            DB::rollBack();
                            return response()->json([
                                'success' => false,
                                'message' => "Invalid date format for start_date at index {$index}. Expected format: dd/mm/yyyy"
                            ], 422);
                        }
                    }

                    $finishDateDb = null;
                    if ($finishDate) {
                        try {
                            $date = Carbon::createFromFormat('d/m/Y', $finishDate);
                            $finishDateDb = $date->format('Y-m-d');
                        } catch (\Exception $e) {
                            DB::rollBack();
                            return response()->json([
                                'success' => false,
                                'message' => "Invalid date format for finish_date at index {$index}. Expected format: dd/mm/yyyy"
                            ], 422);
                        }
                    }

                    // Save qualification marker (to indicate existence of this qualification entry)
                    ClientPortalDetailAudit::create([
                        'client_id' => $clientId,
                        'meta_key' => 'qualification',
                        'old_value' => null,
                        'new_value' => '1', // Just a marker
                        'meta_order' => $metaOrder,
                        'meta_type' => (string) $qualificationId, // Store record ID (original or generated)
                        'action' => $action,
                        'updated_by' => $userId,
                        'updated_at' => now(),
                    ]);

                    // Save level if provided
                    if ($level) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'qualification_level',
                            'old_value' => null,
                            'new_value' => $level,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $qualificationId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save name if provided - store record ID in meta_type
                    if ($name) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'qualification_name',
                            'old_value' => null,
                            'new_value' => $name,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $qualificationId, // Store record ID (original or generated)
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save college_name if provided
                    if ($collegeName) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'qualification_college_name',
                            'old_value' => null,
                            'new_value' => $collegeName,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $qualificationId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save campus if provided
                    if ($campus) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'qualification_campus',
                            'old_value' => null,
                            'new_value' => $campus,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $qualificationId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save country if provided
                    if ($country) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'qualification_country',
                            'old_value' => null,
                            'new_value' => $country,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $qualificationId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save state if provided
                    if ($state) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'qualification_state',
                            'old_value' => null,
                            'new_value' => $state,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $qualificationId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save start_date if provided
                    if ($startDateDb) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'qualification_start_date',
                            'old_value' => null,
                            'new_value' => $startDateDb,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $qualificationId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save finish_date if provided
                    if ($finishDateDb) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'qualification_finish_date',
                            'old_value' => null,
                            'new_value' => $finishDateDb,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $qualificationId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save relevant_qualification
                    ClientPortalDetailAudit::create([
                        'client_id' => $clientId,
                        'meta_key' => 'qualification_relevant',
                        'old_value' => null,
                        'new_value' => $relevantQualification ? '1' : '0',
                        'meta_order' => $metaOrder,
                        'meta_type' => null,
                        'action' => 'update',
                        'updated_by' => $userId,
                        'updated_at' => now(),
                    ]);

                    $updatedQualifications[] = [
                        'id' => $qualificationId,
                        'level' => $level,
                        'name' => $name,
                        'college_name' => $collegeName,
                        'campus' => $campus,
                        'country' => $country,
                        'state' => $state,
                        'start_date' => $startDate,
                        'finish_date' => $finishDate,
                        'relevant_qualification' => $relevantQualification,
                    ];
                }

                DB::commit();

                $this->notifyStaffAndCreateActionForDetailUpdate($clientId, 'Qualification detail updated');

                return response()->json([
                    'success' => true,
                    'message' => 'Qualification details updated successfully',
                    'data' => [
                        'qualifications' => $updatedQualifications
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update client experience details - saves to clientportal_details_audit table
     * 
     * This API allows clients to update their work experience information.
     * Experiences are saved to clientportal_details_audit table with meta_key and meta_order.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateClientExperienceDetail(Request $request)
    {
        try {
            // Get authenticated client ID from token
            $admin = $request->user();
            $clientId = (int) $admin->id;

            // Verify the authenticated client (Admin model; Staff use Staff model)
            if (!$admin instanceof Admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. This endpoint is only available for clients.'
                ], 403);
            }

            // Validate the request
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'experiences' => 'required|array|min:1',
                'experiences.*.id' => 'present|nullable|integer',
                'experiences.*.job_title' => 'required|string|max:255',
                'experiences.*.job_code' => 'nullable|string|max:255',
                'experiences.*.employer_name' => 'nullable|string|max:255',
                'experiences.*.country' => 'nullable|string|max:255',
                'experiences.*.state' => 'nullable|string|max:255',
                'experiences.*.job_type' => 'nullable|string|max:255',
                'experiences.*.start_date' => 'nullable|date_format:d/m/Y',
                'experiences.*.finish_date' => 'nullable|date_format:d/m/Y',
                'experiences.*.relevant_experience' => 'nullable|boolean',
                'experiences.*.fte_multiplier' => 'nullable|numeric|min:0|max:1',
            ], [
                'experiences.required' => 'At least one experience entry is required.',
                'experiences.*.id.present' => 'Experience ID field is required for each experience. Use null for new experiences or provide the existing experience ID for updates.',
                'experiences.*.id.integer' => 'Experience ID must be an integer or null.',
                'experiences.*.job_title.required' => 'Job title is required for each experience entry.',
                'experiences.*.start_date.date_format' => 'Start date must be in dd/mm/yyyy format.',
                'experiences.*.finish_date.date_format' => 'Finish date must be in dd/mm/yyyy format.',
                'experiences.*.fte_multiplier.numeric' => 'FTE multiplier must be a number.',
                'experiences.*.fte_multiplier.min' => 'FTE multiplier must be between 0 and 1.',
                'experiences.*.fte_multiplier.max' => 'FTE multiplier must be between 0 and 1.',
            ]);

            // Custom validation: Ensure id field is always present and check date logic
            $validator->after(function ($validator) use ($request) {
                $experiences = $request->input('experiences', []);
                foreach ($experiences as $index => $experience) {
                    // Ensure id field is always present (required field)
                    if (!array_key_exists('id', $experience)) {
                        $validator->errors()->add(
                            "experiences.{$index}.id",
                            "Experience ID field is required. Use null for new experiences or provide the existing experience ID for updates."
                        );
                    }
                    
                    if (!empty($experience['start_date']) && !empty($experience['finish_date'])) {
                        try {
                            $startDate = Carbon::createFromFormat('d/m/Y', $experience['start_date']);
                            $finishDate = Carbon::createFromFormat('d/m/Y', $experience['finish_date']);
                            
                            if ($finishDate->lt($startDate)) {
                                $validator->errors()->add(
                                    "experiences.{$index}.finish_date",
                                    "Finish date must be after or equal to start date."
                                );
                            }
                        } catch (\Exception $e) {
                            // Date format validation already handled above
                        }
                    }
                    // Validate: employment dates cannot be future dates
                    if (!empty($experience['start_date'])) {
                        try {
                            $startDate = Carbon::createFromFormat('d/m/Y', $experience['start_date']);
                            if ($startDate->isFuture()) {
                                $validator->errors()->add(
                                    "experiences.{$index}.start_date",
                                    "The employment start date cannot be a future date."
                                );
                            }
                        } catch (\Exception $e) {}
                    }
                    if (!empty($experience['finish_date'])) {
                        try {
                            $finishDate = Carbon::createFromFormat('d/m/Y', $experience['finish_date']);
                            if ($finishDate->isFuture()) {
                                $validator->errors()->add(
                                    "experiences.{$index}.finish_date",
                                    "The employment finish date cannot be a future date."
                                );
                            }
                        } catch (\Exception $e) {}
                    }
                }
            });

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $userId = Auth::id() ?? $clientId;
            $experiences = $request->input('experiences');
            $updatedExperiences = [];

            DB::beginTransaction();

            try {
                // Get existing experience IDs from request to identify which ones to update
                $experienceIdsToUpdate = [];
                $experienceIdToMetaOrderMap = []; // Map experience ID to its meta_order
                
                foreach ($experiences as $experienceData) {
                    // ID field is required - if it has a value (not null), it's an update; if null, it's a new record
                    if (isset($experienceData['id']) && $experienceData['id'] !== null && $experienceData['id'] !== '') {
                        $experienceIdsToUpdate[] = (int) $experienceData['id'];
                    }
                }

                // Get the highest existing meta_order BEFORE deleting (to ensure unique values for new experiences)
                $maxMetaOrder = ClientPortalDetailAudit::where('client_id', $clientId)
                    ->whereIn('meta_key', ['experience', 'experience_job_title', 'experience_job_code', 'experience_country', 'experience_start_date', 'experience_finish_date', 'experience_relevant', 'experience_employer_name', 'experience_state', 'experience_job_type', 'experience_fte_multiplier'])
                    ->max('meta_order') ?? -1;

                // Get meta_order values for existing experiences BEFORE deleting (if IDs provided)
                if (!empty($experienceIdsToUpdate)) {
                    $existingAuditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
                        ->where('meta_key', 'experience')
                        ->whereIn('meta_type', array_map('strval', $experienceIdsToUpdate))
                        ->get();

                    foreach ($existingAuditEntries as $entry) {
                        $eid = (int) $entry->meta_type;
                        if (!isset($experienceIdToMetaOrderMap[$eid])) {
                            $experienceIdToMetaOrderMap[$eid] = $entry->meta_order;
                        }
                    }

                    // Delete audit entries for specific experiences being updated
                    if (!empty($experienceIdToMetaOrderMap)) {
                        $ordersToDelete = array_values($experienceIdToMetaOrderMap);
                        ClientPortalDetailAudit::where('client_id', $clientId)
                            ->whereIn('meta_key', ['experience', 'experience_job_title', 'experience_job_code', 'experience_country', 'experience_start_date', 'experience_finish_date', 'experience_relevant', 'experience_employer_name', 'experience_state', 'experience_job_type', 'experience_fte_multiplier'])
                            ->whereIn('meta_order', $ordersToDelete)
                            ->delete();
                    }
                }
                // Note: If all experiences have id: null (new experiences), no deletion happens - safe behavior

                // Track which meta_order values are being reused (to avoid conflicts with new experiences)
                $usedMetaOrders = array_values($experienceIdToMetaOrderMap);

                // Process each experience
                foreach ($experiences as $index => $experienceData) {
                    // ID field is required: if null, it's a new record; if has value, it's an update
                    $experienceId = null;
                    $isNewRecord = false;
                    if (isset($experienceData['id']) && $experienceData['id'] !== null && $experienceData['id'] !== '') {
                        $experienceId = (int) $experienceData['id'];
                    } else {
                        // Generate timestamp-based 10-digit ID for new record
                        $experienceId = $this->generateTimestampBasedId();
                        $isNewRecord = true;
                    }
                    
                    $jobTitle = $experienceData['job_title'] ?? null;
                    $jobCode = $experienceData['job_code'] ?? null;
                    $employerName = $experienceData['employer_name'] ?? null;
                    $country = $experienceData['country'] ?? null;
                    $state = $experienceData['state'] ?? null;
                    $jobType = $experienceData['job_type'] ?? null;
                    $startDate = $experienceData['start_date'] ?? null;
                    $finishDate = $experienceData['finish_date'] ?? null;
                    $relevantExperience = isset($experienceData['relevant_experience']) ? (bool) $experienceData['relevant_experience'] : false;
                    // Preserve original type (int or float) from input
                    $fteMultiplier = $experienceData['fte_multiplier'] ?? null;
                    $fteMultiplierForDb = isset($experienceData['fte_multiplier']) ? (float) $experienceData['fte_multiplier'] : null;

                    if (empty($jobTitle)) {
                        continue; // Skip if job_title is empty
                    }

                    // Determine action: 'create' for new records, 'update' for existing ones
                    $action = $isNewRecord ? 'create' : 'update';

                    // Determine meta_order: use existing if updating by ID, otherwise use next available
                    if (!$isNewRecord && isset($experienceIdToMetaOrderMap[$experienceId])) {
                        // Use existing meta_order for this experience
                        $metaOrder = $experienceIdToMetaOrderMap[$experienceId];
                    } else {
                        // New experience - use next available meta_order that doesn't conflict with reused values
                        do {
                            $maxMetaOrder++;
                            $metaOrder = $maxMetaOrder;
                        } while (in_array($metaOrder, $usedMetaOrders));
                        // Track this meta_order as used to avoid conflicts with subsequent new experiences
                        $usedMetaOrders[] = $metaOrder;
                    }

                    // Convert dates from dd/mm/yyyy to Y-m-d format
                    $startDateDb = null;
                    if ($startDate) {
                        try {
                            $date = Carbon::createFromFormat('d/m/Y', $startDate);
                            $startDateDb = $date->format('Y-m-d');
                        } catch (\Exception $e) {
                            DB::rollBack();
                            return response()->json([
                                'success' => false,
                                'message' => "Invalid date format for start_date at index {$index}. Expected format: dd/mm/yyyy"
                            ], 422);
                        }
                    }

                    $finishDateDb = null;
                    if ($finishDate) {
                        try {
                            $date = Carbon::createFromFormat('d/m/Y', $finishDate);
                            $finishDateDb = $date->format('Y-m-d');
                        } catch (\Exception $e) {
                            DB::rollBack();
                            return response()->json([
                                'success' => false,
                                'message' => "Invalid date format for finish_date at index {$index}. Expected format: dd/mm/yyyy"
                            ], 422);
                        }
                    }

                    // Save experience marker (to indicate existence of this experience entry)
                    ClientPortalDetailAudit::create([
                        'client_id' => $clientId,
                        'meta_key' => 'experience',
                        'old_value' => null,
                        'new_value' => '1', // Just a marker
                        'meta_order' => $metaOrder,
                        'meta_type' => (string) $experienceId, // Store record ID (original or generated)
                        'action' => $action,
                        'updated_by' => $userId,
                        'updated_at' => now(),
                    ]);

                    // Save job_title - store record ID in meta_type
                    ClientPortalDetailAudit::create([
                        'client_id' => $clientId,
                        'meta_key' => 'experience_job_title',
                        'old_value' => null,
                        'new_value' => $jobTitle,
                        'meta_order' => $metaOrder,
                        'meta_type' => (string) $experienceId, // Store record ID (original or generated)
                        'action' => $action,
                        'updated_by' => $userId,
                        'updated_at' => now(),
                    ]);

                    // Save job_code if provided
                    if ($jobCode) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'experience_job_code',
                            'old_value' => null,
                            'new_value' => $jobCode,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $experienceId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save employer_name if provided
                    if ($employerName) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'experience_employer_name',
                            'old_value' => null,
                            'new_value' => $employerName,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $experienceId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save country if provided
                    if ($country) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'experience_country',
                            'old_value' => null,
                            'new_value' => $country,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $experienceId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save state if provided
                    if ($state) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'experience_state',
                            'old_value' => null,
                            'new_value' => $state,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $experienceId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save job_type if provided
                    if ($jobType) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'experience_job_type',
                            'old_value' => null,
                            'new_value' => $jobType,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $experienceId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save start_date if provided
                    if ($startDateDb) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'experience_start_date',
                            'old_value' => null,
                            'new_value' => $startDateDb,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $experienceId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save finish_date if provided
                    if ($finishDateDb) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'experience_finish_date',
                            'old_value' => null,
                            'new_value' => $finishDateDb,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $experienceId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save relevant_experience
                    ClientPortalDetailAudit::create([
                        'client_id' => $clientId,
                        'meta_key' => 'experience_relevant',
                        'old_value' => null,
                        'new_value' => $relevantExperience ? '1' : '0',
                        'meta_order' => $metaOrder,
                        'meta_type' => null,
                        'action' => 'update',
                        'updated_by' => $userId,
                        'updated_at' => now(),
                    ]);

                    // Save fte_multiplier if provided (use float for database storage)
                    if ($fteMultiplierForDb !== null) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'experience_fte_multiplier',
                            'old_value' => null,
                            'new_value' => (string) $fteMultiplierForDb,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $experienceId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    $updatedExperiences[] = [
                        'id' => $experienceId,
                        'job_title' => $jobTitle,
                        'job_code' => $jobCode,
                        'employer_name' => $employerName,
                        'country' => $country,
                        'state' => $state,
                        'job_type' => $jobType,
                        'start_date' => $startDate,
                        'finish_date' => $finishDate,
                        'relevant_experience' => $relevantExperience,
                        'fte_multiplier' => $fteMultiplier, // Preserve original type (int or float) from input
                    ];
                }

                DB::commit();

                $this->notifyStaffAndCreateActionForDetailUpdate($clientId, 'Experience detail updated');

                $responseData = [
                    'success' => true,
                    'message' => 'Experience details updated successfully',
                    'data' => [
                        'experiences' => $updatedExperiences
                    ]
                ];

                return response(
                    json_encode($responseData, JSON_PRESERVE_ZERO_FRACTION),
                    200,
                    ['Content-Type' => 'application/json']
                );

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update client occupation details - saves to clientportal_details_audit table
     * 
     * This API allows clients to update their occupation information.
     * Occupations are saved to clientportal_details_audit table with meta_key and meta_order.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateClientOccupationDetail(Request $request)
    {
        try {
            // Get authenticated client ID from token
            $admin = $request->user();
            $clientId = (int) $admin->id;

            // Verify the authenticated client (Admin model; Staff use Staff model)
            if (!$admin instanceof Admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. This endpoint is only available for clients.'
                ], 403);
            }

            // Validate the request
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'occupations' => 'required|array|min:1',
                'occupations.*.id' => 'present|nullable|integer',
                'occupations.*.skill_assessment' => 'nullable|string|max:255',
                'occupations.*.nominated_occupation' => 'nullable|string|max:255',
                'occupations.*.occupation_code' => 'nullable|string|max:255',
                'occupations.*.assessing_authority' => 'nullable|string|max:255',
                'occupations.*.visa_subclass' => 'nullable|string|max:255',
                'occupations.*.assessment_date' => 'nullable|date_format:d/m/Y',
                'occupations.*.expiry_date' => 'nullable|date_format:d/m/Y',
                'occupations.*.reference_no' => 'nullable|string|max:255',
                'occupations.*.relevant_occupation' => 'nullable|boolean',
                'occupations.*.anzsco_occupation_id' => 'nullable|integer',
            ], [
                'occupations.required' => 'At least one occupation entry is required.',
                'occupations.*.id.present' => 'Occupation ID field is required for each occupation. Use null for new occupations or provide the existing occupation ID for updates.',
                'occupations.*.id.integer' => 'Occupation ID must be an integer or null.',
                'occupations.*.assessment_date.date_format' => 'Assessment date must be in dd/mm/yyyy format.',
                'occupations.*.expiry_date.date_format' => 'Expiry date must be in dd/mm/yyyy format.',
                'occupations.*.anzsco_occupation_id.integer' => 'ANZSCO occupation ID must be an integer.',
            ]);

            // Custom validation: Ensure id field is always present and check date logic
            $validator->after(function ($validator) use ($request) {
                $occupations = $request->input('occupations', []);
                foreach ($occupations as $index => $occupation) {
                    // Ensure id field is always present (required field)
                    if (!array_key_exists('id', $occupation)) {
                        $validator->errors()->add(
                            "occupations.{$index}.id",
                            "Occupation ID field is required. Use null for new occupations or provide the existing occupation ID for updates."
                        );
                    }
                    
                    if (!empty($occupation['assessment_date']) && !empty($occupation['expiry_date'])) {
                        try {
                            $assessmentDate = Carbon::createFromFormat('d/m/Y', $occupation['assessment_date']);
                            $expiryDate = Carbon::createFromFormat('d/m/Y', $occupation['expiry_date']);
                            
                            if ($expiryDate->lt($assessmentDate)) {
                                $validator->errors()->add(
                                    "occupations.{$index}.expiry_date",
                                    "Expiry date must be after or equal to assessment date."
                                );
                            }
                        } catch (\Exception $e) {
                            // Date format validation already handled above
                        }
                    }
                }
            });

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $userId = Auth::id() ?? $clientId;
            $occupations = $request->input('occupations');
            $updatedOccupations = [];

            DB::beginTransaction();

            try {
                // Get existing occupation IDs from request to identify which ones to update
                $occupationIdsToUpdate = [];
                $occupationIdToMetaOrderMap = []; // Map occupation ID to its meta_order
                
                foreach ($occupations as $occupationData) {
                    // ID field is required - if it has a value (not null), it's an update; if null, it's a new record
                    if (isset($occupationData['id']) && $occupationData['id'] !== null && $occupationData['id'] !== '') {
                        $occupationIdsToUpdate[] = (int) $occupationData['id'];
                    }
                }

                // Get the highest existing meta_order BEFORE deleting (to ensure unique values for new occupations)
                $maxMetaOrder = ClientPortalDetailAudit::where('client_id', $clientId)
                    ->whereIn('meta_key', ['occupation', 'occupation_skill_assessment', 'occupation_nominated', 'occupation_code', 'occupation_assessing_authority', 'occupation_visa_subclass', 'occupation_assessment_date', 'occupation_expiry_date', 'occupation_reference_no', 'occupation_relevant', 'occupation_anzsco_id'])
                    ->max('meta_order') ?? -1;

                // Get meta_order values for existing occupations BEFORE deleting (if IDs provided)
                if (!empty($occupationIdsToUpdate)) {
                    $existingAuditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
                        ->where('meta_key', 'occupation')
                        ->whereIn('meta_type', array_map('strval', $occupationIdsToUpdate))
                        ->get();

                    foreach ($existingAuditEntries as $entry) {
                        $oid = (int) $entry->meta_type;
                        if (!isset($occupationIdToMetaOrderMap[$oid])) {
                            $occupationIdToMetaOrderMap[$oid] = $entry->meta_order;
                        }
                    }

                    // Delete audit entries for specific occupations being updated
                    if (!empty($occupationIdToMetaOrderMap)) {
                        $ordersToDelete = array_values($occupationIdToMetaOrderMap);
                        ClientPortalDetailAudit::where('client_id', $clientId)
                            ->whereIn('meta_key', ['occupation', 'occupation_skill_assessment', 'occupation_nominated', 'occupation_code', 'occupation_assessing_authority', 'occupation_visa_subclass', 'occupation_assessment_date', 'occupation_expiry_date', 'occupation_reference_no', 'occupation_relevant', 'occupation_anzsco_id'])
                            ->whereIn('meta_order', $ordersToDelete)
                            ->delete();
                    }
                }
                // Note: If all occupations have id: null (new occupations), no deletion happens - safe behavior

                // Track which meta_order values are being reused (to avoid conflicts with new occupations)
                $usedMetaOrders = array_values($occupationIdToMetaOrderMap);

                // Process each occupation
                foreach ($occupations as $index => $occupationData) {
                    // ID field is required: if null, it's a new record; if has value, it's an update
                    $occupationId = null;
                    $isNewRecord = false;
                    if (isset($occupationData['id']) && $occupationData['id'] !== null && $occupationData['id'] !== '') {
                        $occupationId = (int) $occupationData['id'];
                    } else {
                        // Generate timestamp-based 10-digit ID for new record
                        $occupationId = $this->generateTimestampBasedId();
                        $isNewRecord = true;
                    }
                    
                    $skillAssessment = $occupationData['skill_assessment'] ?? null;
                    $nominatedOccupation = $occupationData['nominated_occupation'] ?? null;
                    $occupationCode = $occupationData['occupation_code'] ?? null;
                    $assessingAuthority = $occupationData['assessing_authority'] ?? null;
                    $visaSubclass = $occupationData['visa_subclass'] ?? null;
                    $assessmentDate = $occupationData['assessment_date'] ?? null;
                    $expiryDate = $occupationData['expiry_date'] ?? null;
                    $referenceNo = $occupationData['reference_no'] ?? null;
                    $relevantOccupation = isset($occupationData['relevant_occupation']) ? (bool) $occupationData['relevant_occupation'] : false;
                    $anzscoOccupationId = isset($occupationData['anzsco_occupation_id']) ? (int) $occupationData['anzsco_occupation_id'] : null;

                    // Skip if both nominated_occupation and occupation_code are empty
                    if (empty($nominatedOccupation) && empty($occupationCode)) {
                        continue;
                    }

                    // Determine action: 'create' for new records, 'update' for existing ones
                    $action = $isNewRecord ? 'create' : 'update';

                    // Determine meta_order: use existing if updating by ID, otherwise use next available
                    if (!$isNewRecord && isset($occupationIdToMetaOrderMap[$occupationId])) {
                        // Use existing meta_order for this occupation
                        $metaOrder = $occupationIdToMetaOrderMap[$occupationId];
                    } else {
                        // New occupation - use next available meta_order that doesn't conflict with reused values
                        do {
                            $maxMetaOrder++;
                            $metaOrder = $maxMetaOrder;
                        } while (in_array($metaOrder, $usedMetaOrders));
                        // Track this meta_order as used to avoid conflicts with subsequent new occupations
                        $usedMetaOrders[] = $metaOrder;
                    }

                    // Convert dates from dd/mm/yyyy to Y-m-d format
                    $assessmentDateDb = null;
                    if ($assessmentDate) {
                        try {
                            $date = Carbon::createFromFormat('d/m/Y', $assessmentDate);
                            $assessmentDateDb = $date->format('Y-m-d');
                        } catch (\Exception $e) {
                            DB::rollBack();
                            return response()->json([
                                'success' => false,
                                'message' => "Invalid date format for assessment_date at index {$index}. Expected format: dd/mm/yyyy"
                            ], 422);
                        }
                    }

                    $expiryDateDb = null;
                    if ($expiryDate) {
                        try {
                            $date = Carbon::createFromFormat('d/m/Y', $expiryDate);
                            $expiryDateDb = $date->format('Y-m-d');
                        } catch (\Exception $e) {
                            DB::rollBack();
                            return response()->json([
                                'success' => false,
                                'message' => "Invalid date format for expiry_date at index {$index}. Expected format: dd/mm/yyyy"
                            ], 422);
                        }
                    }

                    // Save occupation marker (to indicate existence of this occupation entry)
                    ClientPortalDetailAudit::create([
                        'client_id' => $clientId,
                        'meta_key' => 'occupation',
                        'old_value' => null,
                        'new_value' => '1', // Just a marker
                        'meta_order' => $metaOrder,
                        'meta_type' => (string) $occupationId, // Store record ID (original or generated)
                        'action' => $action,
                        'updated_by' => $userId,
                        'updated_at' => now(),
                    ]);

                    // Save skill_assessment if provided
                    if ($skillAssessment) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'occupation_skill_assessment',
                            'old_value' => null,
                            'new_value' => $skillAssessment,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $occupationId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save nominated_occupation if provided
                    if ($nominatedOccupation) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'occupation_nominated',
                            'old_value' => null,
                            'new_value' => $nominatedOccupation,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $occupationId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save occupation_code if provided
                    if ($occupationCode) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'occupation_code',
                            'old_value' => null,
                            'new_value' => $occupationCode,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $occupationId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save assessing_authority if provided
                    if ($assessingAuthority) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'occupation_assessing_authority',
                            'old_value' => null,
                            'new_value' => $assessingAuthority,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $occupationId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save visa_subclass if provided
                    if ($visaSubclass) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'occupation_visa_subclass',
                            'old_value' => null,
                            'new_value' => $visaSubclass,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $occupationId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save assessment_date if provided
                    if ($assessmentDateDb) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'occupation_assessment_date',
                            'old_value' => null,
                            'new_value' => $assessmentDateDb,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $occupationId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save expiry_date if provided
                    if ($expiryDateDb) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'occupation_expiry_date',
                            'old_value' => null,
                            'new_value' => $expiryDateDb,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $occupationId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save reference_no if provided
                    if ($referenceNo) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'occupation_reference_no',
                            'old_value' => null,
                            'new_value' => $referenceNo,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $occupationId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save relevant_occupation
                    ClientPortalDetailAudit::create([
                        'client_id' => $clientId,
                        'meta_key' => 'occupation_relevant',
                        'old_value' => null,
                        'new_value' => $relevantOccupation ? '1' : '0',
                        'meta_order' => $metaOrder,
                        'meta_type' => (string) $occupationId, // Store record ID for consistency
                        'action' => $action,
                        'updated_by' => $userId,
                        'updated_at' => now(),
                    ]);

                    // Save anzsco_occupation_id if provided
                    if ($anzscoOccupationId) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'occupation_anzsco_id',
                            'old_value' => null,
                            'new_value' => (string) $anzscoOccupationId,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $occupationId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    $updatedOccupations[] = [
                        'id' => $occupationId,
                        'skill_assessment' => $skillAssessment,
                        'nominated_occupation' => $nominatedOccupation,
                        'occupation_code' => $occupationCode,
                        'assessing_authority' => $assessingAuthority,
                        'visa_subclass' => $visaSubclass,
                        'assessment_date' => $assessmentDate,
                        'expiry_date' => $expiryDate,
                        'reference_no' => $referenceNo,
                        'relevant_occupation' => $relevantOccupation,
                        'anzsco_occupation_id' => $anzscoOccupationId,
                    ];
                }

                DB::commit();

                $this->notifyStaffAndCreateActionForDetailUpdate($clientId, 'Occupation detail updated');

                return response()->json([
                    'success' => true,
                    'message' => 'Occupation details updated successfully',
                    'data' => [
                        'occupations' => $updatedOccupations
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update client test score details - saves to clientportal_details_audit table
     * 
     * This API allows clients to update their English test score information.
     * Test scores are saved to clientportal_details_audit table with meta_key and meta_order.
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateClientTestScoreDetail(Request $request)
    {
        try {
            // Get authenticated client ID from token
            $admin = $request->user();
            $clientId = (int) $admin->id;

            // Verify the authenticated client (Admin model; Staff use Staff model)
            if (!$admin instanceof Admin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. This endpoint is only available for clients.'
                ], 403);
            }

            // Validate the request
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'test_scores' => 'required|array|min:1',
                'test_scores.*.id' => 'present|nullable|integer',
                'test_scores.*.test_type' => 'required|string|max:255',
                'test_scores.*.listening' => 'nullable|numeric|min:0|max:9',
                'test_scores.*.reading' => 'nullable|numeric|min:0|max:9',
                'test_scores.*.writing' => 'nullable|numeric|min:0|max:9',
                'test_scores.*.speaking' => 'nullable|numeric|min:0|max:9',
                'test_scores.*.overall_score' => 'nullable|numeric|min:0|max:9',
                'test_scores.*.test_date' => 'nullable|date_format:d/m/Y',
                'test_scores.*.reference_no' => 'nullable|string|max:255',
                'test_scores.*.relevant_test' => 'nullable|boolean',
            ], [
                'test_scores.required' => 'At least one test score entry is required.',
                'test_scores.*.id.present' => 'Test score ID field is required for each test score. Use null for new test scores or provide the existing test score ID for updates.',
                'test_scores.*.id.integer' => 'Test score ID must be an integer or null.',
                'test_scores.*.test_type.required' => 'Test type is required for each test score entry.',
                'test_scores.*.listening.numeric' => 'Listening score must be a number.',
                'test_scores.*.listening.min' => 'Listening score must be between 0 and 9.',
                'test_scores.*.listening.max' => 'Listening score must be between 0 and 9.',
                'test_scores.*.reading.numeric' => 'Reading score must be a number.',
                'test_scores.*.reading.min' => 'Reading score must be between 0 and 9.',
                'test_scores.*.reading.max' => 'Reading score must be between 0 and 9.',
                'test_scores.*.writing.numeric' => 'Writing score must be a number.',
                'test_scores.*.writing.min' => 'Writing score must be between 0 and 9.',
                'test_scores.*.writing.max' => 'Writing score must be between 0 and 9.',
                'test_scores.*.speaking.numeric' => 'Speaking score must be a number.',
                'test_scores.*.speaking.min' => 'Speaking score must be between 0 and 9.',
                'test_scores.*.speaking.max' => 'Speaking score must be between 0 and 9.',
                'test_scores.*.overall_score.numeric' => 'Overall score must be a number.',
                'test_scores.*.overall_score.min' => 'Overall score must be between 0 and 9.',
                'test_scores.*.overall_score.max' => 'Overall score must be between 0 and 9.',
                'test_scores.*.test_date.date_format' => 'Test date must be in dd/mm/yyyy format.',
            ]);

            // Custom validation: Ensure id field is always present
            $validator->after(function ($validator) use ($request) {
                $testScores = $request->input('test_scores', []);
                foreach ($testScores as $index => $testScore) {
                    // Ensure id field is always present (required field)
                    if (!array_key_exists('id', $testScore)) {
                        $validator->errors()->add(
                            "test_scores.{$index}.id",
                            "Test score ID field is required. Use null for new test scores or provide the existing test score ID for updates."
                        );
                    }
                }
            });

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $userId = Auth::id() ?? $clientId;
            $testScores = $request->input('test_scores');
            $updatedTestScores = [];

            DB::beginTransaction();

            try {
                // Get existing test score IDs from request to identify which ones to update
                $testScoreIdsToUpdate = [];
                $testScoreIdToMetaOrderMap = []; // Map test score ID to its meta_order
                
                foreach ($testScores as $testScoreData) {
                    // ID field is required - if it has a value (not null), it's an update; if null, it's a new record
                    if (isset($testScoreData['id']) && $testScoreData['id'] !== null && $testScoreData['id'] !== '') {
                        $testScoreIdsToUpdate[] = (int) $testScoreData['id'];
                    }
                }

                // Get the highest existing meta_order BEFORE deleting (to ensure unique values for new test scores)
                $maxMetaOrder = ClientPortalDetailAudit::where('client_id', $clientId)
                    ->whereIn('meta_key', ['test_score', 'test_score_test_type', 'test_score_listening', 'test_score_reading', 'test_score_writing', 'test_score_speaking', 'test_score_overall_score', 'test_score_test_date', 'test_score_reference_no', 'test_score_relevant'])
                    ->max('meta_order') ?? -1;

                // Get meta_order values for existing test scores BEFORE deleting (if IDs provided)
                if (!empty($testScoreIdsToUpdate)) {
                    $existingAuditEntries = ClientPortalDetailAudit::where('client_id', $clientId)
                        ->where('meta_key', 'test_score')
                        ->whereIn('meta_type', array_map('strval', $testScoreIdsToUpdate))
                        ->get();

                    foreach ($existingAuditEntries as $entry) {
                        $tsid = (int) $entry->meta_type;
                        if (!isset($testScoreIdToMetaOrderMap[$tsid])) {
                            $testScoreIdToMetaOrderMap[$tsid] = $entry->meta_order;
                        }
                    }

                    // Delete audit entries for specific test scores being updated
                    if (!empty($testScoreIdToMetaOrderMap)) {
                        $ordersToDelete = array_values($testScoreIdToMetaOrderMap);
                        ClientPortalDetailAudit::where('client_id', $clientId)
                            ->whereIn('meta_key', ['test_score', 'test_score_test_type', 'test_score_listening', 'test_score_reading', 'test_score_writing', 'test_score_speaking', 'test_score_overall_score', 'test_score_test_date', 'test_score_reference_no', 'test_score_relevant'])
                            ->whereIn('meta_order', $ordersToDelete)
                            ->delete();
                    }
                }
                // Note: If all test scores have id: null (new test scores), no deletion happens - safe behavior

                // Track which meta_order values are being reused (to avoid conflicts with new test scores)
                $usedMetaOrders = array_values($testScoreIdToMetaOrderMap);

                // Process each test score
                foreach ($testScores as $index => $testScoreData) {
                    // ID field is required: if null, it's a new record; if has value, it's an update
                    $testScoreId = null;
                    $isNewRecord = false;
                    if (isset($testScoreData['id']) && $testScoreData['id'] !== null && $testScoreData['id'] !== '') {
                        $testScoreId = (int) $testScoreData['id'];
                    } else {
                        // Generate timestamp-based 10-digit ID for new record
                        $testScoreId = $this->generateTimestampBasedId();
                        $isNewRecord = true;
                    }
                    
                    $testType = $testScoreData['test_type'] ?? null;
                    $listening = isset($testScoreData['listening']) ? (float) $testScoreData['listening'] : null;
                    $reading = isset($testScoreData['reading']) ? (float) $testScoreData['reading'] : null;
                    $writing = isset($testScoreData['writing']) ? (float) $testScoreData['writing'] : null;
                    $speaking = isset($testScoreData['speaking']) ? (float) $testScoreData['speaking'] : null;
                    $overallScore = isset($testScoreData['overall_score']) ? (float) $testScoreData['overall_score'] : null;
                    $testDate = $testScoreData['test_date'] ?? null;
                    $referenceNo = $testScoreData['reference_no'] ?? null;
                    $relevantTest = isset($testScoreData['relevant_test']) ? (bool) $testScoreData['relevant_test'] : false;

                    if (empty($testType)) {
                        continue; // Skip if test_type is empty
                    }

                    // Determine action: 'create' for new records, 'update' for existing ones
                    $action = $isNewRecord ? 'create' : 'update';

                    // Determine meta_order: use existing if updating by ID, otherwise use next available
                    if (!$isNewRecord && isset($testScoreIdToMetaOrderMap[$testScoreId])) {
                        // Use existing meta_order for this test score
                        $metaOrder = $testScoreIdToMetaOrderMap[$testScoreId];
                    } else {
                        // New test score - use next available meta_order that doesn't conflict with reused values
                        do {
                            $maxMetaOrder++;
                            $metaOrder = $maxMetaOrder;
                        } while (in_array($metaOrder, $usedMetaOrders));
                        // Track this meta_order as used to avoid conflicts with subsequent new test scores
                        $usedMetaOrders[] = $metaOrder;
                    }

                    // Convert date from dd/mm/yyyy to Y-m-d format
                    $testDateDb = null;
                    if ($testDate) {
                        try {
                            $date = Carbon::createFromFormat('d/m/Y', $testDate);
                            $testDateDb = $date->format('Y-m-d');
                        } catch (\Exception $e) {
                            DB::rollBack();
                            return response()->json([
                                'success' => false,
                                'message' => "Invalid date format for test_date at index {$index}. Expected format: dd/mm/yyyy"
                            ], 422);
                        }
                    }

                    // Save test score marker (to indicate existence of this test score entry)
                    ClientPortalDetailAudit::create([
                        'client_id' => $clientId,
                        'meta_key' => 'test_score',
                        'old_value' => null,
                        'new_value' => '1', // Just a marker
                        'meta_order' => $metaOrder,
                        'meta_type' => (string) $testScoreId, // Store record ID (original or generated)
                        'action' => $action,
                        'updated_by' => $userId,
                        'updated_at' => now(),
                    ]);

                    // Save test_type - store record ID in meta_type
                    ClientPortalDetailAudit::create([
                        'client_id' => $clientId,
                        'meta_key' => 'test_score_test_type',
                        'old_value' => null,
                        'new_value' => $testType,
                        'meta_order' => $metaOrder,
                        'meta_type' => (string) $testScoreId, // Store record ID (original or generated)
                        'action' => $action,
                        'updated_by' => $userId,
                        'updated_at' => now(),
                    ]);

                    // Save listening if provided
                    if ($listening !== null) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'test_score_listening',
                            'old_value' => null,
                            'new_value' => (string) $listening,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $testScoreId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save reading if provided
                    if ($reading !== null) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'test_score_reading',
                            'old_value' => null,
                            'new_value' => (string) $reading,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $testScoreId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save writing if provided
                    if ($writing !== null) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'test_score_writing',
                            'old_value' => null,
                            'new_value' => (string) $writing,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $testScoreId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save speaking if provided
                    if ($speaking !== null) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'test_score_speaking',
                            'old_value' => null,
                            'new_value' => (string) $speaking,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $testScoreId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save overall_score if provided
                    if ($overallScore !== null) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'test_score_overall_score',
                            'old_value' => null,
                            'new_value' => (string) $overallScore,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $testScoreId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save test_date if provided
                    if ($testDateDb) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'test_score_test_date',
                            'old_value' => null,
                            'new_value' => $testDateDb,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $testScoreId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save reference_no if provided
                    if ($referenceNo) {
                        ClientPortalDetailAudit::create([
                            'client_id' => $clientId,
                            'meta_key' => 'test_score_reference_no',
                            'old_value' => null,
                            'new_value' => $referenceNo,
                            'meta_order' => $metaOrder,
                            'meta_type' => (string) $testScoreId, // Store record ID for consistency
                            'action' => $action,
                            'updated_by' => $userId,
                            'updated_at' => now(),
                        ]);
                    }

                    // Save relevant_test
                    ClientPortalDetailAudit::create([
                        'client_id' => $clientId,
                        'meta_key' => 'test_score_relevant',
                        'old_value' => null,
                        'new_value' => $relevantTest ? '1' : '0',
                        'meta_order' => $metaOrder,
                        'meta_type' => (string) $testScoreId, // Store record ID for consistency
                        'action' => $action,
                        'updated_by' => $userId,
                        'updated_at' => now(),
                    ]);

                    $updatedTestScores[] = [
                        'id' => $testScoreId,
                        'test_type' => $testType,
                        'listening' => $listening,
                        'reading' => $reading,
                        'writing' => $writing,
                        'speaking' => $speaking,
                        'overall_score' => $overallScore,
                        'test_date' => $testDate,
                        'reference_no' => $referenceNo,
                        'relevant_test' => $relevantTest,
                    ];
                }

                DB::commit();

                $this->notifyStaffAndCreateActionForDetailUpdate($clientId, 'Test score detail updated');

                return response()->json([
                    'success' => true,
                    'message' => 'Test score details updated successfully',
                    'data' => [
                        'test_scores' => $updatedTestScores
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Notify PERSON ASSISTING and super admins once, and create Client Portal action note(s) for PR + PA.
     * Message format: "Client name updated detail with message - \"{detailMessage}\"." (no matter name, show once)
     *
     * @param int $clientId
     * @param string $detailMessage e.g. "Basic detail updated", "Visa detail updated"
     */
    private function notifyStaffAndCreateActionForDetailUpdate(int $clientId, string $detailMessage): void
    {
        $clientRow = DB::table('admins')->where('id', $clientId)->select('first_name', 'last_name')->first();
        $clientName = $clientRow ? trim(($clientRow->first_name ?? '') . ' ' . ($clientRow->last_name ?? '')) : 'Client';

        $matters = DB::table('client_matters')->where('client_id', $clientId)->get();
        if ($matters->isEmpty()) {
            return;
        }

        $actionMessage = $clientName . ' updated detail with message - "' . $detailMessage . '".';

        $superAdminIds = Staff::where('role', 1)->where('status', 1)->pluck('id')->toArray();
        $personAssistingIds = $matters->pluck('sel_person_assisting')->filter()->unique()->values()->all();
        $recipientIds = array_values(array_unique(array_filter(array_merge($superAdminIds, $personAssistingIds))));
        $recipientIds = array_values(array_filter($recipientIds, function ($id) use ($clientId) {
            return $id && $id != $clientId;
        }));

        $firstMatter = $matters->first();
        $clientMatterId = (int) $firstMatter->id;
        $notificationUrl = url('/clients/detail/' . base64_encode(convert_uuencode($firstMatter->client_id)));
        if (!empty($firstMatter->client_unique_matter_no)) {
            $notificationUrl .= '/' . $firstMatter->client_unique_matter_no;
        }
        $notificationUrl .= '/client_portal';

        foreach ($recipientIds as $receiverStaffId) {
            if (!$receiverStaffId || !Staff::where('id', $receiverStaffId)->exists()) {
                continue;
            }
            try {
                Notification::create([
                    'sender_id' => $clientId,
                    'receiver_id' => $receiverStaffId,
                    'module_id' => $clientMatterId,
                    'url' => $notificationUrl,
                    'notification_type' => 'client_detail_update',
                    'message' => $actionMessage,
                    'receiver_status' => 0,
                    'seen' => 0,
                ]);
                $unreadCount = (int) DB::table('notifications')->where('receiver_id', $receiverStaffId)->where('receiver_status', 0)->count();
                broadcast(new NotificationCountUpdated($receiverStaffId, $unreadCount, $actionMessage, $notificationUrl));
            } catch (\Exception $e) {
                Log::warning('Detail update: failed to notify staff', ['receiver_id' => $receiverStaffId, 'error' => $e->getMessage()]);
            }
        }

        try {
            $clientMatterModel = ClientMatter::find($clientMatterId);
            if ($clientMatterModel) {
                $fallback = isset($recipientIds[0]) ? (int) $recipientIds[0] : null;
                $fallback = $fallback && $fallback > 0 ? $fallback : null;
                ClientPortalActionNoteService::createGroupedForMatter(
                    (int) $clientMatterModel->client_id,
                    $clientMatterId,
                    $actionMessage,
                    $clientId,
                    $clientMatterModel,
                    $fallback
                );
            }
        } catch (\Exception $e) {
            Log::warning('Detail update: failed to create Action page entry', ['client_matter_id' => $clientMatterId, 'error' => $e->getMessage()]);
        }
    }
}
