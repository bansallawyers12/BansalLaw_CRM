<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Concerns\EnsuresCrmRecordAccess;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use App\Models\Admin;
use App\Models\ClientAddress;
use App\Models\ClientContact;
use App\Models\ClientEmail;
use App\Models\ClientMatter;
use App\Models\ClientMatterOpposingParty;
use App\Models\ClientConflictParty;
use App\Models\ConflictPartyContact;
use App\Models\ConflictPartyEmail;
use App\Models\ClientConflictCheck;
use App\Models\Matter;
use App\Support\MatterStreamHelper;
use App\Models\Company;
use App\Services\ConflictCheckService;
use App\Services\ConflictCheckStalenessService;
use App\Models\CompanyDirector;
use App\Models\ActivitiesLog;
use App\Models\Lead;
use App\Models\Staff;
use App\Services\LeadFollowUpNoteService;
use App\Services\CompanyDirectorEmailService;
use App\Traits\LogsClientActivity;

/**
 * ClientPersonalDetailsController
 * 
 * Handles personal information, company details, and contact data for clients.
 * 
 * Maps to: resources/views/Admin/clients/tabs/personal_details.blade.php
 */
class ClientPersonalDetailsController extends Controller
{
    use EnsuresCrmRecordAccess;
    use LogsClientActivity;
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    public function getVisaTypes()
    {
        $visaTypes = \App\Models\Matter::select('id', 'title', 'nick_name')
            ->where('title', 'not like', '%skill assessment%')
            ->where('status', 1)
            ->orderBy('title', 'ASC')
            ->get();

        return response()->json($visaTypes);
    }

    public function getCountries()
    {
        $countries = \App\Models\Country::all()->pluck('name')->toArray();

        // Ensure "India" and "Australia" are at the top of the list
        $priorityCountries = ['Australia','India'];
        $otherCountries = array_diff($countries, $priorityCountries);
        $sortedCountries = array_merge($priorityCountries, $otherCountries);

        return response()->json($sortedCountries);
    }

      //Fetch all contact list of any client at create note popup
      public function fetchClientContactNo(Request $request){ //dd($request->all());
        $this->ensureCrmRecordAccessFromRequest($request, ['client_id']);
        $clientId = (int) $request->client_id;

        // Same source as the Client Phone field on the profile: usable client_contacts
        // first, then the profile phone on admins when no contact rows exist.
        $clientContacts = ClientContact::select('phone', 'country_code', 'contact_type')
            ->where('client_id', $clientId)
            ->where(function ($query) {
                $query->whereNull('contact_type')
                    ->orWhere('contact_type', '!=', 'Not In Use');
            })
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get();

        if ($clientContacts->isEmpty()) {
            $profile = Admin::select('phone', 'country_code', 'contact_type')
                ->where('id', $clientId)
                ->first();

            if ($profile && !empty($profile->phone)) {
                $clientContacts = collect([(object) [
                    'phone' => $profile->phone,
                    'country_code' => $profile->country_code ?: '',
                    'contact_type' => $profile->contact_type ?: 'Phone',
                ]]);
            }
        }

        if ($clientContacts->isNotEmpty()) {
            $response['status'] 	= 	true;
            $response['message']	=	'Client contact is successfully fetched.';
            $response['clientContacts']	=	$clientContacts->values();
        } else {
            $response['status'] 	= 	false;
            $response['message']	=	'Please try again';
            $response['clientContacts']	=	array();
        }
        echo json_encode($response);
	}

    public function updateAddress(Request $request)
    {
        $postcode = $request->input('postcode');
        // Fetch data based on the postcode
        // Replace this with your actual API call to get address details
        $apiKey = config('services.auspost.auth_key') ?? env('AUSPOST_AUTH_KEY', '');
        $urlPrefix = 'digitalapi.auspost.com.au';
        $url = 'https://' . $urlPrefix . '/postcode/search.json?q=' . $postcode;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['AUTH-KEY: ' . $apiKey]);
        $response = curl_exec($ch);  //dd($response);
        // curl handles are closed automatically in recent PHP versions.
        if (!$response) {
            return response()->json(['localities' => []]);
        }
        $data = json_decode($response, true); //dd($data);
        return response()->json($data);
    }

    // Method 1: Search address using Google Places with fallback
    public function searchAddressFull(Request $request)
    {
        $query = $request->input('query');
        $apiKey = env('GOOGLE_MAPS_API_KEY');
        
        // Try Google Places API first
        if ($apiKey) {
            $url = 'https://maps.googleapis.com/maps/api/place/autocomplete/json';
            
            // Determine the best search strategy based on the query
            $searchParams = $this->getOptimalSearchParams($query);
            
            $params = http_build_query([
                'input' => $query,
                'key' => $apiKey,
                'types' => $searchParams['types'],
                'components' => 'country:au', // Restrict to Australia
                'fields' => 'place_id,description,structured_formatting'
            ]);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url . '?' . $params);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Increased to 30 seconds
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Connection timeout
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local dev
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            // curl handles are closed automatically in recent PHP versions.
            
            // Log curl errors for debugging
            if ($curlError) {
                Log::error('Google Places Autocomplete API CURL Error: ' . $curlError);
            }
            
            $data = json_decode($response, true);
            
            // Check if Google API is working
            if ($httpCode === 200 && isset($data['status']) && $data['status'] !== 'REQUEST_DENIED') {
                // Post-process the results to ensure house numbers are preserved
                $data = $this->postProcessAddressResults($data, $query);
                return response()->json($data);
            }
        }
        
        // Fallback: Use free geocoding service for basic suggestions
        return $this->getFallbackAddressSuggestions($query);
    }
    
    /**
     * Get optimal search parameters based on query content
     */
    private function getOptimalSearchParams($query)
    {
        // Check if query contains a house number (starts with digits)
        if (preg_match('/^\d+[A-Za-z]?\s+/', $query)) {
            // Query has house number with street name, use geocode for better results
            return [
                'types' => 'geocode'
            ];
        } elseif (preg_match('/^\d+[A-Za-z]?$/', $query)) {
            // Only house number is typed, use establishment type to get more relevant results
            return [
                'types' => 'establishment'
            ];
        } else {
            // No house number, use address type
            return [
                'types' => 'address'
            ];
        }
    }
    
    /**
     * Post-process address results to ensure house numbers are preserved and match the query
     */
    private function postProcessAddressResults($data, $originalQuery)
    {
        if (!isset($data['predictions']) || !is_array($data['predictions'])) {
            return $data;
        }
        
        $filteredPredictions = [];
        $queryLower = strtolower(trim($originalQuery));
        
        foreach ($data['predictions'] as $prediction) {
            $description = $prediction['description'];
            $descriptionLower = strtolower($description);
            
            // Check if the prediction starts with or contains the query text
            if (strpos($descriptionLower, $queryLower) === 0 || 
                strpos($descriptionLower, $queryLower) !== false) {
                
                // Extract house number from original query if present
                preg_match('/^(\d+[A-Za-z]?)\s*(.*)/', $originalQuery, $matches);
                if (count($matches) >= 2) {
                    $houseNumber = $matches[1];
                    
                    // If the description doesn't start with the house number, prepend it
                    if (!preg_match('/^' . preg_quote($houseNumber, '/') . '/i', $description)) {
                        $prediction['description'] = $houseNumber . ' ' . $description;
                    }
                }
                
                $filteredPredictions[] = $prediction;
            }
        }
        
        // If no matching results, try a more flexible approach
        if (empty($filteredPredictions)) {
            foreach ($data['predictions'] as $prediction) {
                $description = $prediction['description'];
                
                // Extract house number from original query if present
                preg_match('/^(\d+[A-Za-z]?)\s*(.*)/', $originalQuery, $matches);
                if (count($matches) >= 2) {
                    $houseNumber = $matches[1];
                    $streetName = trim($matches[2]);
                    
                    // If we have a street name, check if the description contains it
                    if (!empty($streetName) && stripos($description, $streetName) !== false) {
                        // Ensure the description starts with the house number
                        if (!preg_match('/^' . preg_quote($houseNumber, '/') . '/i', $description)) {
                            $prediction['description'] = $houseNumber . ' ' . $description;
                        }
                        $filteredPredictions[] = $prediction;
                    } elseif (empty($streetName)) {
                        // If only house number is typed, prepend it to any address
                        if (!preg_match('/^' . preg_quote($houseNumber, '/') . '/i', $description)) {
                            $prediction['description'] = $houseNumber . ' ' . $description;
                        }
                        $filteredPredictions[] = $prediction;
                    }
                }
            }
        }
        
        // Limit to 5 results and use filtered results if available
        if (!empty($filteredPredictions)) {
            $data['predictions'] = array_slice($filteredPredictions, 0, 5);
        }
        
        return $data;
    }
    
    /**
     * Fallback address suggestions using free service
     */
    private function getFallbackAddressSuggestions($query)
    {
        try {
            // Use OpenStreetMap Nominatim API (free)
            $url = 'https://nominatim.openstreetmap.org/search';
            $params = http_build_query([
                'q' => $query . ', Australia',
                'format' => 'json',
                'limit' => 5,
                'addressdetails' => 1,
                'countrycodes' => 'au'
            ]);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url . '?' . $params);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_USERAGENT, config('app.name') . ' CRM');
            $response = curl_exec($ch);
            // curl handles are closed automatically in recent PHP versions.
            
            $results = json_decode($response, true);
            
            // Convert to Google Places API format for compatibility
            $predictions = [];
            if (is_array($results)) {
                foreach ($results as $result) {
                    // Format the display name to be more consistent with Google Places format
                    $formattedDescription = $this->formatFallbackAddress($result, $query);
                    
                    $predictions[] = [
                        'place_id' => 'fallback_' . md5($result['display_name']),
                        'description' => $formattedDescription,
                        'formatted_address' => $formattedDescription
                    ];
                }
            }
            
            // Post-process fallback results to ensure house numbers are preserved
            $predictions = $this->postProcessFallbackResults($predictions, $query);
            
            return response()->json([
                'status' => 'OK',
                'predictions' => $predictions
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'ERROR',
                'error_message' => 'Address service temporarily unavailable. Please enter address manually.',
                'predictions' => []
            ]);
        }
    }
    
    /**
     * Format fallback address to match Google Places format
     */
    private function formatFallbackAddress($result, $originalQuery)
    {
        $address = $result['address'] ?? [];
        $displayName = $result['display_name'] ?? '';
        
        // Extract house number from original query if present
        preg_match('/^(\d+[A-Za-z]?)\s+(.+)/', $originalQuery, $matches);
        
        if (count($matches) >= 3) {
            $houseNumber = $matches[1];
            $streetName = trim($matches[2], ', ');
            
            // Build a cleaner address format
            $parts = [];
            
            // Add house number and street
            $parts[] = $houseNumber . ' ' . $streetName;
            
            // Add suburb if available
            if (isset($address['suburb'])) {
                $parts[] = $address['suburb'];
            } elseif (isset($address['village'])) {
                $parts[] = $address['village'];
            }
            
            // Add state abbreviation
            if (isset($address['state'])) {
                $state = $address['state'];
                // Convert full state names to abbreviations
                $stateAbbr = $this->getStateAbbreviation($state);
                $parts[] = $stateAbbr;
            }
            
            // Add country
            $parts[] = 'Australia';
            
            return implode(', ', $parts);
        }
        
        return $displayName;
    }
    
    /**
     * Get state abbreviation from full state name
     */
    private function getStateAbbreviation($state)
    {
        $stateMap = [
            'New South Wales' => 'NSW',
            'Victoria' => 'VIC',
            'Queensland' => 'QLD',
            'South Australia' => 'SA',
            'Western Australia' => 'WA',
            'Tasmania' => 'TAS',
            'Northern Territory' => 'NT',
            'Australian Capital Territory' => 'ACT'
        ];
        
        return $stateMap[$state] ?? $state;
    }
    
    /**
     * Post-process fallback results to ensure house numbers are preserved and match the query
     */
    private function postProcessFallbackResults($predictions, $originalQuery)
    {
        $filteredPredictions = [];
        $queryLower = strtolower(trim($originalQuery));
        
        // Extract house number from original query if present
        preg_match('/^(\d+[A-Za-z]?)\s*(.*)/', $originalQuery, $matches);
        if (count($matches) >= 2) {
            $houseNumber = $matches[1];
            $streetName = trim($matches[2]);
            
            foreach ($predictions as $prediction) {
                $description = $prediction['description'];
                $descriptionLower = strtolower($description);
                
                // Check if the prediction starts with or contains the query text
                if (strpos($descriptionLower, $queryLower) === 0 || 
                    strpos($descriptionLower, $queryLower) !== false) {
                    
                    // Ensure the prediction starts with the house number
                    if (!preg_match('/^' . preg_quote($houseNumber, '/') . '/i', $description)) {
                        $prediction['description'] = $houseNumber . ' ' . $description;
                    }
                    
                    $filteredPredictions[] = $prediction;
                }
            }
            
            // If no matching results, try to prepend house number to relevant addresses
            if (empty($filteredPredictions)) {
                foreach ($predictions as $prediction) {
                    $description = $prediction['description'];
                    
                    if (!empty($streetName) && stripos($description, $streetName) !== false) {
                        // Ensure the description starts with the house number
                        if (!preg_match('/^' . preg_quote($houseNumber, '/') . '/i', $description)) {
                            $prediction['description'] = $houseNumber . ' ' . $description;
                        }
                        $filteredPredictions[] = $prediction;
                    } elseif (empty($streetName)) {
                        // If only house number is typed, prepend it to any address
                        if (!preg_match('/^' . preg_quote($houseNumber, '/') . '/i', $description)) {
                            $prediction['description'] = $houseNumber . ' ' . $description;
                        }
                        $filteredPredictions[] = $prediction;
                    }
                }
            }
            
            return !empty($filteredPredictions) ? array_slice($filteredPredictions, 0, 5) : $predictions;
        }
        
        return $predictions;
    }

    // Method 2: Get place details with fallback
    public function getPlaceDetails(Request $request)
    {
        $placeId = $request->input('place_id');
        $apiKey = env('GOOGLE_MAPS_API_KEY');
        
        // Handle fallback place IDs
        if (strpos($placeId, 'fallback_') === 0) {
            return $this->getFallbackPlaceDetails($request);
        }
        
        // Try Google Places API
        if ($apiKey) {
            $url = 'https://maps.googleapis.com/maps/api/place/details/json';
            
            // Request all address fields including postal_code
            $params = http_build_query([
                'place_id' => $placeId,
                'key' => $apiKey,
                'fields' => 'address_components,formatted_address,name'
            ]);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url . '?' . $params);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Increased to 30 seconds
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Connection timeout
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local dev
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            // curl handles are closed automatically in recent PHP versions.
            
            // Log curl errors for debugging
            if ($curlError) {
                Log::error('Google Places Details API CURL Error: ' . $curlError);
            }
            
            $data = json_decode($response, true);
            
            if ($httpCode === 200 && isset($data['status']) && $data['status'] !== 'REQUEST_DENIED') {
                return response()->json($data);
            }
        }
        
        // Fallback: Return basic structure for manual entry
        return response()->json([
            'status' => 'OK',
            'result' => [
                'formatted_address' => $request->input('description', ''),
                'address_components' => []
            ]
        ]);
    }
    
    /**
     * Handle fallback place details
     */
    private function getFallbackPlaceDetails($request)
    {
        $description = $request->input('description', '');
        
        // Try to extract basic address components from the description
        $parts = explode(', ', $description);
        $addressComponents = [];
        
        if (count($parts) >= 3) {
            // More intelligent parsing for Australian addresses
            $addressComponents[] = [
                'long_name' => $parts[0],
                'short_name' => $parts[0],
                'types' => ['establishment', 'point_of_interest'] // Mark as establishment for airports/POIs
            ];
            
            // Find suburb (usually one of the middle parts that's not a number)
            $suburb = '';
            for ($i = 1; $i < count($parts) - 2; $i++) {
                if (!is_numeric($parts[$i]) && !in_array($parts[$i], ['NSW', 'VIC', 'QLD', 'SA', 'WA', 'TAS', 'NT', 'ACT'])) {
                    $suburb = $parts[$i];
                    break;
                }
            }
            
            if ($suburb) {
                $addressComponents[] = [
                    'long_name' => $suburb,
                    'short_name' => $suburb,
                    'types' => ['locality']
                ];
            }
            
            // Find state
            $state = '';
            foreach ($parts as $part) {
                if (in_array($part, ['NSW', 'VIC', 'QLD', 'SA', 'WA', 'TAS', 'NT', 'ACT'])) {
                    $state = $part;
                    break;
                }
            }
            
            if ($state) {
                $addressComponents[] = [
                    'long_name' => $state,
                    'short_name' => $state,
                    'types' => ['administrative_area_level_1']
                ];
            }
            
            // Enhanced: Find postcode (4-digit number anywhere in description)
            $postcode = '';
            // First, search through all parts for a 4-digit number
            foreach ($parts as $part) {
                $part = trim($part);
                if (preg_match('/\b(\d{4})\b/', $part, $matches)) {
                    $postcode = $matches[1];
                    break;
                }
            }
            
            // If not found in parts, search the entire description
            if (!$postcode && preg_match('/\b(\d{4})\b/', $description, $matches)) {
                $postcode = $matches[1];
            }
            
            if ($postcode) {
                $addressComponents[] = [
                    'long_name' => $postcode,
                    'short_name' => $postcode,
                    'types' => ['postal_code']
                ];
            }
            
            $addressComponents[] = [
                'long_name' => 'Australia',
                'short_name' => 'AU',
                'types' => ['country']
            ];
        }
        
        return response()->json([
            'status' => 'OK',
            'result' => [
                'formatted_address' => $description,
                'address_components' => $addressComponents
            ]
        ]);
    }

    // Method 3: Helper to combine address
    private function combineAddress($parts)
    {
        $addressParts = array_filter([
            $parts['line1'] ?? null,
            $parts['line2'] ?? null,
            $parts['suburb'] ?? null,
            $parts['state'] ?? null,
            $parts['postcode'] ?? null,
            (($parts['country'] ?? 'Australia') !== 'Australia' ? $parts['country'] : null)
        ]);
        
        return implode(', ', $addressParts);
    }

    public function fetchClientMatterAssignee(Request $request)
    {
        $requestData = $request->all();
        $cmId = (int) ($requestData['client_matter_id'] ?? 0);
        if ($cmId > 0) {
            $clientId = DB::table('client_matters')->where('id', $cmId)->value('client_id');
            if ($clientId) {
                $this->ensureCrmRecordAccess((int) $clientId);
            }
        }
        $matter_info = DB::table('client_matters')->where('id', $cmId)->first();
        if (! empty($matter_info)) {
            $response = [
                'matter_info' => $matter_info,
                'status' => true,
                'message' => 'Record is exist',
                'opposing_parties' => [],
                'matter_stream' => null,
            ];
            if (Schema::hasTable('client_matter_opposing_parties')) {
                $response['opposing_parties'] = ClientMatterOpposingParty::query()
                    ->where('client_matter_id', $cmId)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get(\App\Support\OpposingPartyHelper::opposingPartySelectColumns())
                    ->toArray();
            }
            $selMatterId = (int) ($matter_info->sel_matter_id ?? 0);
            if ($selMatterId > 0) {
                $m = Matter::query()->find($selMatterId);
                $response['matter_stream'] = $m && $m->stream ? (string) $m->stream : 'general';
            }
            $clientAdmin = Admin::query()->find((int) $matter_info->client_id);
            $response['matter_options'] = [];
            if ($clientAdmin) {
                $response['matter_options'] = Matter::query()
                    ->select('id', 'title', 'stream')
                    ->where('status', 1)
                    ->forClientType((bool) $clientAdmin->is_company)
                    ->orderBy('title')
                    ->get()
                    ->toArray();
            }

            $assigneeIds = array_values(array_filter(array_unique([
                (int) ($matter_info->sel_legal_practitioner ?? 0),
                (int) ($matter_info->sel_person_responsible ?? 0),
                (int) ($matter_info->sel_person_assisting ?? 0),
            ]), static fn (int $id): bool => $id > 0));

            $response['assignee_staff_for_modal'] = [];
            if ($assigneeIds !== []) {
                $response['assignee_staff_for_modal'] = Staff::query()
                    ->whereIn('id', $assigneeIds)
                    ->get(['id', 'first_name', 'last_name', 'email'])
                    ->keyBy('id')
                    ->map(static fn (Staff $s): array => [
                        'id' => $s->id,
                        'first_name' => $s->first_name,
                        'last_name' => $s->last_name,
                        'email' => $s->email,
                    ])
                    ->all();
            }

            return response()->json($response);
        }

        return response()->json([
            'matter_info' => [],
            'status' => false,
            'message' => 'Record is not exist.Please try again',
            'opposing_parties' => [],
            'matter_options' => [],
        ]);
    }

    public function updateClientMatterAssignee(Request $request){
        $response = ['status' => false, 'message' => 'Invalid request. Please try again.'];
        $requstData = $request->all();

        if (!empty($requstData['client_id'])) {
            $this->ensureCrmRecordAccess((int) $requstData['client_id']);
        }

        if (empty($requstData['selectedMatterLM'])) {
            $response['message'] = 'Please select a matter first.';
            return response()->json($response);
        }

        if (! ClientMatter::where('id', '=', $requstData['selectedMatterLM'])->exists()) {
            $response['message'] = 'Matter not found. Please try again.';

            return response()->json($response);
        }

        $obj = ClientMatter::find($requstData['selectedMatterLM']);
        $postedClientId = (int) ($requstData['client_id'] ?? 0);
        if ((int) $obj->client_id !== $postedClientId) {
            return response()->json([
                'status' => false,
                'message' => 'Matter does not belong to this client.',
            ], 403);
        }

        $clientAdmin = Admin::query()->find($postedClientId);

        $newMatterTypeId = isset($requstData['sel_matter_id']) ? (int) $requstData['sel_matter_id'] : 0;
        if ($newMatterTypeId > 0 && $newMatterTypeId !== (int) $obj->sel_matter_id) {
            if (! Matter::query()->whereKey($newMatterTypeId)->exists()) {
                $response['message'] = 'Invalid law matter type.';

                return response()->json($response, 422);
            }
            if (! $clientAdmin || ! Matter::allowedForClientIsCompany($newMatterTypeId, (bool) $clientAdmin->is_company)) {
                $response['message'] = 'That law matter type is not valid for this client.';

                return response()->json($response, 422);
            }
            $obj->sel_matter_id = $newMatterTypeId;
        }

        $obj->sel_legal_practitioner = $requstData['legal_practitioner'];
        $obj->sel_person_responsible = $requstData['person_responsible'];
        $obj->sel_person_assisting = $requstData['person_assisting'];
        $obj->user_id = $requstData['user_id'];

        if (isset($requstData['office_id']) && $requstData['office_id'] !== '') {
            $obj->office_id = $requstData['office_id'];
        }

        if (Schema::hasColumn('client_matters', 'incidence_type')) {
            $incidenceType = isset($requstData['incidence_type']) ? trim((string) $requstData['incidence_type']) : '';
            $obj->incidence_type = $incidenceType !== '' ? $incidenceType : null;
        }
        if (Schema::hasColumn('client_matters', 'date_of_incidence')) {
            $doi = $requstData['date_of_incidence'] ?? null;
            $obj->date_of_incidence = ($doi !== null && $doi !== '') ? $doi : null;
        }
        if (Schema::hasColumn('client_matters', 'case_detail')) {
            $caseDetail = isset($requstData['case_detail']) ? trim((string) $requstData['case_detail']) : '';
            $obj->case_detail = $caseDetail !== '' ? $caseDetail : null;
        }

        if (Schema::hasColumn('client_matters', 'our_party_role')) {
            $ourRole = isset($requstData['our_party_role']) ? trim((string) $requstData['our_party_role']) : '';
            $matterForStream = Matter::query()->find((int) $obj->sel_matter_id);
            $stream = $matterForStream && $matterForStream->stream
                ? (string) $matterForStream->stream
                : 'general';
            if ($ourRole === '') {
                $response['message'] = 'Our client\'s role is required.';

                return response()->json($response, 422);
            }
            if (! MatterStreamHelper::isValidPartyRole($stream, $ourRole)) {
                $response['message'] = 'Invalid party role for this matter stream.';

                return response()->json($response, 422);
            }
            $obj->our_party_role = $ourRole;
        }

        $decodedOpposing = [];
        if (Schema::hasTable('client_matter_opposing_parties')) {
            $rawOpp = isset($requstData['opposing_parties_json']) ? trim((string) $requstData['opposing_parties_json']) : '';
            if ($rawOpp !== '') {
                try {
                    $decodedOpposing = \App\Support\OpposingPartyHelper::parseJsonPayload($rawOpp);
                } catch (\InvalidArgumentException $e) {
                    return response()->json([
                        'status' => false,
                        'message' => $e->getMessage(),
                    ], 422);
                }
            }
        }

        $saved = false;
        try {
            DB::transaction(function () use ($obj, $decodedOpposing, $postedClientId) {
                $obj->save();
                \App\Support\OpposingPartyHelper::syncForMatter((int) $obj->id, $decodedOpposing);
                \App\Support\MatterOtherPartiesHelper::syncConflictPartiesAfterMatterSave(
                    (int) $postedClientId,
                    (int) $obj->id,
                    $decodedOpposing
                );
            });
            $saved = true;
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'status' => false,
                'message' => 'Could not save matter details.',
            ], 500);
        }

        if ($saved) {
            $wasLeadBefore = false;
            $adminCheck = \App\Models\Admin::query()->find($postedClientId);
            if ($adminCheck) {
                $typeNorm = mb_strtolower(trim((string) $adminCheck->type));
                if ($typeNorm === 'lead' || in_array($typeNorm, \App\Models\Lead::LEAD_TYPE_VALUES, true)) {
                    $wasLeadBefore = true;
                }
            }

            \App\Services\LeadMatterAssignedConversion::applyForAdminId($postedClientId);

            $adminAfter = \App\Models\Admin::query()->find($postedClientId);
            $wasConverted = $wasLeadBefore && $adminAfter && $adminAfter->type === 'client';

            $objs = new \App\Models\ActivitiesLog;
            $objs->client_id = $requstData['client_id'];
            $objs->created_by = Auth::user()->id;
            $objs->description = '';
            $objs->subject = 'updated client matter details';
            $objs->task_status = 0;
            $objs->pin = 0;
            $objs->save();

            $response['status'] = true;
            $response['message'] = $wasConverted
                ? 'Matter details updated successfully. Lead was automatically converted to a client.'
                : 'Matter details updated successfully.';
        } else {
            $response['message'] = 'Record could not be updated. Please try again.';
        }

        return response()->json($response);
    }

    /**
     * Save section data via AJAX
     */
    public function saveSection(Request $request)
    {
        try {
            $section = $request->input('section');
            $clientId = $request->input('id'); // Use 'id' instead of 'client_id' - 'id' is the database ID
            
            // Validate client exists (same eligibility as matter add / legacy rows)
            $client = Admin::query()->find($clientId);
            if (! $client || ! $client->isCrmClientOrLeadSubject()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Client not found',
                ], 404);
            }

            if (! \App\Support\StaffClientVisibility::canAccessClientOrLead((int) $clientId, Auth::user())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            switch ($section) {
                case 'basicInfo':
                    return $this->saveBasicInfoSection($request, $client);
                case 'phoneNumbers':
                    return $this->savePhoneNumbersSection($request, $client);
                case 'emailAddresses':
                    return $this->saveEmailAddressesSection($request, $client);
                case 'addressInfo':
                    return $this->saveAddressInfoSection($request, $client);
                case 'companyInfo':
                    return $this->saveCompanySection($request, $client);
                case 'contactPersonInfo':
                    return $this->saveContactPersonSection($request, $client);
                case 'trust':
                    return $this->saveTrustSection($request, $client);
                case 'directors':
                    return $this->saveDirectorsSection($request, $client);
                case 'leadPipeline':
                    return $this->saveLeadPipelineSection($request, $client);
                case 'conflictParties':
                    return $this->saveConflictPartiesSection($request, $client);
                case 'conflictCheckOutcome':
                    return $this->saveConflictCheckOutcomeSection($request, $client);
                case 'referBy':
                    return $this->saveReferBySection($request, $client);
                case 'leadSource':
                    return $this->saveLeadSourceSection($request, $client);
                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid section specified'
                    ], 400);
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while saving: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save company info section (company_name, has_trading_name, trading_names[], ABN, ACN, company_type, website, trust fields).
     * Only for company clients (is_company = true).
     */
    private function saveCompanySection($request, $client)
    {
        if (!$client->is_company) {
            return response()->json(['success' => false, 'message' => 'Not a company client'], 400);
        }

        // Normalize empty company_website to null (avoids url validation failure on empty string)
        $request->merge(['company_website' => $request->filled('company_website') ? trim($request->company_website) : null]);

        $validated = $request->validate([
            'company_name' => 'required|max:255',
            'has_trading_name' => 'nullable|in:0,1',
            'trading_names' => 'nullable|array',
            'trading_names.*' => 'nullable|string|max:255',
            'trading_name_primary' => 'nullable|integer|min:0',
            'ABN_number' => 'nullable|string|max:20',
            'ACN' => 'nullable|string|max:20',
            'company_type' => 'nullable|string|max:50',
            'company_website' => 'nullable|url|max:255',
            'trust_name' => 'nullable|string|max:255',
            'trust_abn' => 'nullable|string|max:64',
        ]);

        $company = Company::firstOrCreate(['admin_id' => $client->id], ['company_name' => $validated['company_name']]);
        $company->company_name = $validated['company_name'];
        $company->ABN_number = !empty($validated['ABN_number']) ? preg_replace('/\D/', '', $validated['ABN_number']) : null;
        $company->ACN = !empty($validated['ACN']) ? preg_replace('/\D/', '', $validated['ACN']) : null;
        $company->company_type = Company::normalizeBusinessType($validated['company_type'] ?? null);
        $company->company_website = $validated['company_website'] ?? null;

        if (Company::isTrusteeBusinessType($company->company_type)) {
            $tnm = $validated['trust_name'] ?? null;
            $company->trust_name = ($tnm !== null && trim((string) $tnm) !== '') ? trim((string) $tnm) : null;
            $tn = $validated['trust_abn'] ?? null;
            $company->trust_abn = ($tn !== null && trim((string) $tn) !== '') ? trim((string) $tn) : null;
        } else {
            $company->trust_name = null;
            $company->trust_abn = null;
            $company->trustee_name = null;
            $company->trustee_details = null;
        }

        $hasTradingName = (int) ($validated['has_trading_name'] ?? 0) === 1;
        $company->has_trading_name = $hasTradingName;

        if ($hasTradingName && !empty($validated['trading_names'])) {
            $tradingNames = array_values(array_filter(array_map('trim', $validated['trading_names'])));
            $primaryIdx = min((int) ($validated['trading_name_primary'] ?? 0), max(0, count($tradingNames) - 1));

            $company->tradingNames()->delete();
            foreach ($tradingNames as $idx => $name) {
                if ($name !== '') {
                    $company->tradingNames()->create([
                        'trading_name' => $name,
                        'is_primary' => ($idx === $primaryIdx),
                        'sort_order' => $idx,
                    ]);
                }
            }
            $company->trading_name = $tradingNames[$primaryIdx] ?? $tradingNames[0] ?? null;
        } else {
            $company->tradingNames()->delete();
            $company->trading_name = null;
        }

        $company->save();

        return response()->json([
            'success' => true,
            'message' => 'Company information updated successfully',
        ]);
    }

    /**
     * Save contact person section (contact_person_id, contact_person_position).
     * Only for company clients (is_company = true).
     */
    private function saveContactPersonSection($request, $client)
    {
        if (!$client->is_company) {
            return response()->json(['success' => false, 'message' => 'Not a company client'], 400);
        }

        $validated = $request->validate([
            'contact_person_id' => 'nullable|exists:admins,id',
            'contact_person_position' => 'nullable|string|max:255',
        ]);

        $company = Company::firstOrCreate(['admin_id' => $client->id], ['company_name' => 'Unnamed Company']);
        $company->contact_person_id = $validated['contact_person_id'] ?? null;
        $company->contact_person_position = $validated['contact_person_position'] ?? null;
        $company->save();

        return response()->json([
            'success' => true,
            'message' => 'Contact person updated successfully',
        ]);
    }

    private function saveTrustSection($request, $client)
    {
        if (!$client->is_company) {
            return response()->json(['success' => false, 'message' => 'Not a company client'], 400);
        }
        $company = Company::firstOrCreate(['admin_id' => $client->id], ['company_name' => 'Unnamed Company']);
        if (! Company::isTrusteeBusinessType($company->company_type)) {
            return response()->json(['success' => false, 'message' => 'Trust details only apply when Business Type is Trustee'], 400);
        }
        $validated = $request->validate([
            'trust_name' => 'nullable|string|max:255',
            'trust_abn' => 'nullable|string|max:64',
            'trustee_name' => 'nullable|string|max:255',
            'trustee_details' => 'nullable|string',
        ]);
        $tnm = $validated['trust_name'] ?? null;
        $company->trust_name = ($tnm !== null && trim((string) $tnm) !== '') ? trim((string) $tnm) : null;
        $ta = $validated['trust_abn'] ?? null;
        $company->trust_abn = ($ta !== null && trim((string) $ta) !== '') ? trim((string) $ta) : null;
        $tn = $validated['trustee_name'] ?? null;
        $company->trustee_name = ($tn !== null && trim((string) $tn) !== '') ? trim((string) $tn) : null;
        $td = $validated['trustee_details'] ?? null;
        $company->trustee_details = ($td !== null && trim((string) $td) !== '') ? trim((string) $td) : null;
        $company->save();
        return response()->json(['success' => true, 'message' => 'Trust details updated successfully']);
    }

    private function saveDirectorsSection($request, $client)
    {
        if (!$client->is_company) {
            return response()->json(['success' => false, 'message' => 'Not a company client'], 400);
        }
        $company = Company::firstOrCreate(['admin_id' => $client->id], ['company_name' => 'Unnamed Company']);
        $modes = $request->input('director_modes', []);
        $clientIds = $request->input('director_client_ids', []);
        $names = $request->input('director_names', []);
        $firstNames = $request->input('director_first_names', []);
        $lastNames = $request->input('director_last_names', []);
        $emails = $request->input('director_emails', []);
        $dobs = $request->input('director_dobs', []);
        $roles = $request->input('director_roles', []);
        $primaryIdx = (int) $request->input('director_primary', 0);
        $emailService = app(CompanyDirectorEmailService::class);

        $count = max(count($modes), count($clientIds), count($names), count($firstNames));

        try {
            DB::transaction(function () use ($company, $count, $modes, $clientIds, $names, $firstNames, $lastNames, $emails, $dobs, $roles, $primaryIdx, $client, $emailService) {
                $company->directors()->delete();

                for ($i = 0; $i < $count; $i++) {
                    $mode = strtolower(trim((string) ($modes[$i] ?? 'name_only')));
                    $clientId = $clientIds[$i] ?? null;
                    $name = trim((string) ($names[$i] ?? ''));
                    $firstName = trim((string) ($firstNames[$i] ?? ''));
                    $lastName = trim((string) ($lastNames[$i] ?? ''));
                    $personalEmail = trim((string) ($emails[$i] ?? ''));

                    if ($mode === '' && $clientId) {
                        $mode = 'link';
                    }
                    if ($mode === '' && ($firstName !== '' || $lastName !== '')) {
                        $mode = $emailService->resolveCompanyPrimaryEmail($client) ? 'company_email' : 'name_only';
                    }
                    if ($mode === '' && $name !== '') {
                        $mode = 'name_only';
                    }
                    if ($mode === '' || ($mode === 'name_only' && $name === '' && $firstName === '' && $lastName === '' && ! $clientId)) {
                        continue;
                    }

                    $directorClient = null;

                    if ($mode === 'link') {
                        if (! $clientId) {
                            throw new \InvalidArgumentException('Director ' . ($i + 1) . ': Select an existing person or choose another option.');
                        }
                        if ($name !== '' || $firstName !== '' || $lastName !== '') {
                            throw new \InvalidArgumentException('Director ' . ($i + 1) . ': Provide either a searchable client/lead OR a new/name-only director, not both.');
                        }
                        $directorClient = Admin::where('id', $clientId)
                            ->whereIn('type', ['client', 'lead'])
                            ->where('is_company', false)
                            ->first();
                        if (! $directorClient) {
                            throw new \InvalidArgumentException('Director ' . ($i + 1) . ': Selected person not found or invalid (must be client/lead, not company).');
                        }
                    } elseif ($mode === 'name_only') {
                        if ($clientId) {
                            throw new \InvalidArgumentException('Director ' . ($i + 1) . ': Name-only directors cannot also be linked to an existing record.');
                        }
                        if ($name === '' && $firstName === '' && $lastName === '') {
                            continue;
                        }
                        if ($name === '') {
                            $name = trim($firstName . ' ' . $lastName);
                        }
                    } elseif (in_array($mode, ['company_email', 'personal'], true)) {
                        if ($clientId) {
                            throw new \InvalidArgumentException('Director ' . ($i + 1) . ': Cannot link and create a new director in the same row.');
                        }
                        if ($firstName === '' && $lastName === '' && $name !== '') {
                            $parts = preg_split('/\s+/', $name, 2);
                            $firstName = $parts[0] ?? '';
                            $lastName = $parts[1] ?? '';
                        }
                        $directorClient = $emailService->createDirectorPerson(
                            $client,
                            $firstName,
                            $lastName,
                            $mode,
                            $mode === 'personal' ? $personalEmail : null
                        );
                    } else {
                        throw new \InvalidArgumentException('Director ' . ($i + 1) . ': Invalid director contact option.');
                    }

                    $linkedDob = $directorClient && $directorClient->dob
                        ? Carbon::parse($directorClient->dob)->format('Y-m-d')
                        : null;
                    $directorDob = ! empty($dobs[$i]) ? $dobs[$i] : $linkedDob;

                    $company->directors()->create([
                        'director_client_id' => $directorClient ? $directorClient->id : null,
                        'director_name' => $directorClient ? null : $name,
                        'director_dob' => $directorDob,
                        'director_role' => $roles[$i] ?? null,
                        'is_primary' => ($i === $primaryIdx),
                        'sort_order' => $i,
                    ]);
                }
            });
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => ['director_emails' => [$e->getMessage()]],
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Could not save directors. Please try again.',
            ], 500);
        }

        $directors = $company->directors()->orderBy('sort_order')->get();
        if ($directors->isNotEmpty() && ! $directors->contains('is_primary', true)) {
            $directors->first()->update(['is_primary' => true]);
        }

        return response()->json(['success' => true, 'message' => 'Directors updated successfully']);
    }

    /**
     * Lead pipeline (stage, follow-up date, assignee) from client detail Personal tab — AJAX only.
     */
    private function saveLeadPipelineSection($request, $client)
    {
        if ($client->type !== 'lead') {
            return response()->json(['success' => false, 'message' => 'Lead pipeline applies to leads only.'], 400);
        }

        // Only normalise assignee when the client actually sent the field (avoid clearing user_id on partial requests).
        if ($request->has('assigned_staff_id')) {
            $request->merge([
                'assigned_staff_id' => ($request->input('assigned_staff_id') === '' || $request->input('assigned_staff_id') === null)
                    ? null
                    : (int) $request->input('assigned_staff_id'),
            ]);
        }

        $pipelineStages = [
            'new', 'initial_consultation', 'conflict_check',
            'engaged', 'retained', 'follow_up',
            'not_proceeding', 'declined',
            'not_qualified', 'hostile',
        ];
        $allowedStages = $pipelineStages;
        $currentStage = (string) ($client->lead_status ?? '');
        if ($currentStage !== '' && ! in_array($currentStage, $pipelineStages, true)) {
            $allowedStages[] = $currentStage;
        }

        try {
            $validated = $request->validate([
                'lead_status' => ['required', Rule::in($allowedStages)],
                'followup_date' => 'nullable|date',
                'assigned_staff_id' => 'nullable|exists:staff,id',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        $previousLeadStatus = $client->lead_status;

        $before = [
            'Stage' => $client->lead_status,
            'Follow-up date' => $client->followup_date ? $client->followup_date->format('Y-m-d') : null,
            'Assigned to' => $client->user_id,
        ];

        $client->lead_status = $validated['lead_status'];

        $conflictWarning = null;
        if (in_array($validated['lead_status'], ['engaged', 'retained'], true)) {
            $pipelineMatterId = $request->filled('client_matter_id')
                ? (int) $request->input('client_matter_id')
                : null;
            if (! $pipelineMatterId) {
                $pipelineMatterId = \App\Support\MatterOtherPartiesHelper::resolveClientMatterId(
                    (int) $client->id,
                    null,
                    null
                );
            }

            $matterCheckQuery = ClientConflictCheck::query()
                ->where('client_id', $client->id)
                ->forPipelineMatter($pipelineMatterId);

            $hasCheck = (clone $matterCheckQuery)
                ->whereIn('outcome', ['clear', 'waived'])
                ->exists();

            if (! $hasCheck) {
                $conflictWarning = 'Warning: No conflict check has been recorded as Clear or Waived for this matter. Complete the conflict check on Personal Details before engaging/retaining.';
            } else {
                $latestClear = (clone $matterCheckQuery)
                    ->whereIn('outcome', ['clear', 'waived'])
                    ->orderByDesc('checked_at')
                    ->orderByDesc('id')
                    ->first();

                if ($latestClear) {
                    $staleness = app(ConflictCheckStalenessService::class)
                        ->evaluateStaleness($client, $pipelineMatterId, $latestClear);
                    if ($staleness['is_stale']) {
                        $conflictWarning = 'Warning: Other parties or client details changed since the last conflict check. Re-run the conflict search and save a new outcome before engaging/retaining.';
                    }
                }

                if ($conflictWarning === null) {
                    $latest = (clone $matterCheckQuery)
                        ->orderByDesc('checked_at')
                        ->orderByDesc('id')
                        ->first();
                    if ($latest && in_array($latest->outcome, ['pending', 'conflict_found'], true)) {
                        $conflictWarning = 'Warning: The latest conflict check outcome is "'
                            . str_replace('_', ' ', $latest->outcome)
                            . '". Confirm Clear or Waived with consent before engaging/retaining.';
                    }
                }
            }
        }

        if ($request->has('followup_date')) {
            $rawFd = $request->input('followup_date');
            if ($rawFd === '' || $rawFd === null) {
                if ($client->lead_status !== 'follow_up') {
                    $client->followup_date = null;
                }
            } else {
                $client->followup_date = Carbon::parse($rawFd)->format('Y-m-d H:i:s');
            }
        }

        if ($client->lead_status !== 'follow_up') {
            $client->followup_date = null;
        }

        $client->status = LeadFollowUpNoteService::adminsStatusForLeadStatus($client->lead_status);

        if (array_key_exists('assigned_staff_id', $validated)) {
            $client->user_id = $validated['assigned_staff_id'];
        }

        $client->save();

        $lead = Lead::find($client->id);
        if ($lead) {
            app(LeadFollowUpNoteService::class)->syncNotesForLead($lead, $previousLeadStatus);
        }

        $after = [
            'Stage' => $client->lead_status,
            'Follow-up date' => $client->followup_date ? $client->followup_date->format('Y-m-d') : null,
            'Assigned to' => $client->user_id,
        ];

        $changedFields = [];
        foreach ($before as $label => $oldVal) {
            $newVal = $after[$label] ?? null;
            if ($label === 'Assigned to') {
                if ((int) $oldVal !== (int) $newVal) {
                    $changedFields[$label] = ['old' => $oldVal, 'new' => $newVal];
                }
            } elseif ($oldVal !== $newVal) {
                $changedFields[$label] = ['old' => $oldVal, 'new' => $newVal];
            }
        }

        if (! empty($changedFields)) {
            $this->logClientActivityWithChanges(
                $client->id,
                'updated lead pipeline',
                $changedFields,
                'activity'
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Pipeline updated successfully.',
            'lead_status' => $client->lead_status,
            'followup_date' => $client->followup_date ? $client->followup_date->format('Y-m-d') : null,
            'assigned_staff_id' => $client->user_id,
            'record_status' => (int) $client->status,
            'conflict_warning' => $conflictWarning,
        ]);
    }

    /**
     * Save other parties for the active matter (or client-level when no matter exists).
     */
    private function saveConflictPartiesSection(Request $request, Admin $client)
    {
        $raw = $request->input('conflict_parties_json', '[]');

        try {
            $parties = \App\Support\OpposingPartyHelper::parseJsonPayload((string) $raw);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $requestedMatterId = $request->filled('client_matter_id')
            ? (int) $request->input('client_matter_id')
            : null;
        $matterRef = trim((string) ($request->input('client_unique_matter_no', '')));
        $explicitMatterRequested = ($requestedMatterId !== null && $requestedMatterId > 0) || $matterRef !== '';
        $clientMatterId = \App\Support\MatterOtherPartiesHelper::resolveClientMatterId(
            (int) $client->id,
            $requestedMatterId,
            $matterRef !== '' ? $matterRef : null
        );

        if ($explicitMatterRequested && ! $clientMatterId) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or missing matter. Refresh the page and try again.',
            ], 422);
        }

        if (! $clientMatterId && Schema::hasTable('client_matters')) {
            $hasActiveMatter = \App\Models\ClientMatter::query()
                ->where('client_id', (int) $client->id)
                ->where('matter_status', 1)
                ->exists();
            if ($hasActiveMatter) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active matter selected. Open a matter, then save other parties.',
                ], 422);
            }
        }

        try {
            $count = \App\Support\MatterOtherPartiesHelper::saveParties((int) $client->id, $clientMatterId, $parties);
            $scopeLabel = $clientMatterId ? 'for this matter' : 'for this client';
            $this->logClientActivity($client->id, 'updated other parties', $count . ' party record(s) saved ' . $scopeLabel, 'activity');

            return response()->json([
                'success' => true,
                'message' => $count === 0
                    ? 'Other parties cleared for this matter.'
                    : ($count . ' other part' . ($count === 1 ? 'y' : 'ies') . ' saved for this matter.'),
                'count' => $count,
                'client_matter_id' => $clientMatterId,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error saving other parties: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Resolve matter id for conflict check run/save (strict when explicitly requested).
     *
     * @return array{client_matter_id: ?int, explicit_requested: bool}
     */
    private function resolveConflictCheckMatterFromRequest(Request $request, Admin $client): array
    {
        $requestedMatterId = $request->filled('client_matter_id')
            ? (int) $request->input('client_matter_id')
            : null;
        $matterRef = trim((string) ($request->input('client_unique_matter_no', '')));
        $explicitMatterRequested = ($requestedMatterId !== null && $requestedMatterId > 0) || $matterRef !== '';
        $clientMatterId = \App\Support\MatterOtherPartiesHelper::resolveClientMatterId(
            (int) $client->id,
            $requestedMatterId,
            $matterRef !== '' ? $matterRef : null
        );

        return [
            'client_matter_id' => $clientMatterId,
            'explicit_requested' => $explicitMatterRequested,
        ];
    }

    private function conflictCheckMatterLabel(?int $clientMatterId): ?string
    {
        if (! $clientMatterId) {
            return null;
        }

        return ClientMatter::query()
            ->where('id', $clientMatterId)
            ->value('client_unique_matter_no');
    }

    private function partiesSnapshotAtForMatter(?int $clientMatterId): ?Carbon
    {
        if (! $clientMatterId) {
            return null;
        }

        $clientId = (int) (ClientMatter::query()->where('id', $clientMatterId)->value('client_id') ?? 0);
        if ($clientId <= 0) {
            return null;
        }

        return app(ConflictCheckStalenessService::class)
            ->partiesUpdatedAtForMatter($clientId, $clientMatterId);
    }

    /**
     * Save conflict check outcome — server re-runs search; client matches are not trusted.
     */
    private function saveConflictCheckOutcomeSection(Request $request, Admin $client)
    {
        $allowed = ['pending', 'clear', 'conflict_found', 'waived'];
        $outcome = $request->input('outcome', 'pending');

        if (! in_array($outcome, $allowed, true)) {
            return response()->json(['success' => false, 'message' => 'Invalid outcome value.'], 422);
        }

        $outcomeNotes = trim((string) ($request->input('outcome_notes', '')));
        $consentObtained = filter_var($request->input('consent_obtained', false), FILTER_VALIDATE_BOOLEAN)
            || $request->input('consent_obtained') === '1'
            || $request->input('consent_obtained') === 1;
        $consentNotes = trim((string) ($request->input('consent_notes', '')));
        $forceClear = filter_var($request->input('force_clear', false), FILTER_VALIDATE_BOOLEAN)
            || $request->input('force_clear') === '1'
            || $request->input('force_clear') === 1;

        $matterContext = $this->resolveConflictCheckMatterFromRequest($request, $client);
        $clientMatterId = $matterContext['client_matter_id'];

        if ($matterContext['explicit_requested'] && ! $clientMatterId) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or missing matter. Refresh the page and try again.',
                'error_type' => 'validation',
            ], 422);
        }

        /** @var ConflictCheckService $service */
        $service = app(ConflictCheckService::class);

        try {
            $client->loadMissing('company');
            $result = $service->run($client, $clientMatterId);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_type' => 'validation',
            ], 422);
        }

        $clientMatches = $this->decodeJsonField($request->input('matches'));
        if ($clientMatches !== null && $clientMatches !== ($result['matches'] ?? [])) {
            Log::warning('Conflict check outcome save: client matches differ from server re-run', [
                'client_id' => $client->id,
                'client_matter_id' => $clientMatterId,
                'client_count' => is_array($clientMatches) ? count($clientMatches) : 0,
                'server_count' => (int) ($result['match_count'] ?? 0),
            ]);
        }

        if (in_array($outcome, ['clear', 'waived'], true)) {
            /** @var ConflictCheckStalenessService $stalenessService */
            $stalenessService = app(ConflictCheckStalenessService::class);
            $ackHash = trim((string) ($request->input('acknowledged_search_hash', '')));
            $staleness = $stalenessService->evaluateAgainstPreviousCheck(
                $client,
                $clientMatterId,
                $result,
                $ackHash !== '' ? $ackHash : null
            );

            if ($staleness['is_stale']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parties or client details changed since the last saved check. Run conflict search again before saving Clear or Waived.',
                    'error_type' => 'stale',
                    'staleness' => $staleness,
                ], 422);
            }
        }

        $validationError = $service->validateOutcomeAgainstResults($outcome, $result, [
            'outcome_notes' => $outcomeNotes,
            'consent_obtained' => $consentObtained,
            'consent_notes' => $consentNotes,
            'force_clear' => $forceClear,
        ]);

        if ($validationError !== null) {
            return response()->json([
                'success' => false,
                'message' => $validationError,
                'error_type' => 'validation',
                'match_count' => (int) ($result['match_count'] ?? 0),
                'informational_count' => (int) ($result['informational_count'] ?? 0),
            ], 422);
        }

        $hardCount = (int) ($result['match_count'] ?? 0);
        $infoCount = (int) ($result['informational_count'] ?? 0);
        $matterLabel = $this->conflictCheckMatterLabel($clientMatterId);

        try {
            $check = ClientConflictCheck::create([
                'client_id'             => $client->id,
                'client_matter_id'      => $clientMatterId,
                'checked_by'            => Auth::id(),
                'checked_at'            => now(),
                'search_terms'          => $result['search_terms'],
                'matches'               => $result['matches'],
                'informational_matches' => $result['informational_matches'] ?? [],
                'match_count'           => $hardCount,
                'informational_count'   => $infoCount,
                'parties_snapshot_at'   => $this->partiesSnapshotAtForMatter($clientMatterId),
                'search_hash'           => $service->buildSearchHash($result['search_terms']),
                'outcome'               => $outcome,
                'outcome_notes'         => $outcomeNotes !== '' ? $outcomeNotes : null,
                'consent_obtained'      => $consentObtained,
                'consent_notes'         => $consentNotes !== '' ? $consentNotes : null,
            ]);

            $outcomeLabels = [
                'clear'          => 'Clear — no conflict found',
                'conflict_found' => 'Conflict found',
                'waived'         => 'Waived with consent',
                'pending'        => 'Pending',
            ];

            $detail = 'Outcome: ' . ($outcomeLabels[$outcome] ?? $outcome);
            if ($matterLabel) {
                $detail .= ' · Matter ' . $matterLabel;
            }
            $detail .= ' · ' . $hardCount . ' conflict' . ($hardCount === 1 ? '' : 's');
            if ($infoCount > 0) {
                $detail .= ' · ' . $infoCount . ' informational';
            }

            $this->logClientActivity(
                $client->id,
                'conflict check recorded',
                $detail,
                'activity'
            );

            return response()->json([
                'success'             => true,
                'message'             => ($outcome === 'clear' && $forceClear && $hardCount > 0)
                    ? 'Conflict check outcome saved with documented override (Clear despite matches).'
                    : 'Conflict check outcome saved.',
                'check_id'            => $check->id,
                'outcome'             => $outcome,
                'checked_at'          => $check->checked_at->format('d M Y H:i'),
                'outcome_notes'       => $check->outcome_notes,
                'consent_obtained'    => (bool) $check->consent_obtained,
                'consent_notes'       => $check->consent_notes,
                'match_count'         => $hardCount,
                'informational_count' => $infoCount,
                'client_matter_id'    => $clientMatterId,
                'matter_label'        => $matterLabel,
                'force_clear_applied' => $outcome === 'clear' && $forceClear && $hardCount > 0,
            ]);
        } catch (\Exception $e) {
            Log::error('Conflict check outcome save failed', [
                'client_id' => $client->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Could not save conflict check outcome. Please try again.',
            ], 500);
        }
    }

    /**
     * Run automated conflict search and return candidate matches (solicitor still decides outcome).
     */
    public function runConflictCheck(Request $request, ConflictCheckService $service)
    {
        $clientId = (int) $request->input('id', $request->route('id'));
        $client = Admin::find($clientId);

        if (! $client || ! $client->isCrmClientOrLeadSubject()) {
            return response()->json(['success' => false, 'message' => 'Invalid client.'], 422);
        }

        if (! \App\Support\StaffClientVisibility::canAccessClientOrLead((int) $client->id, Auth::user())) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $client->loadMissing('company');
            $matterContext = $this->resolveConflictCheckMatterFromRequest($request, $client);
            $clientMatterId = $matterContext['client_matter_id'];

            if ($matterContext['explicit_requested'] && ! $clientMatterId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or missing matter. Refresh the page and try again.',
                    'error_type' => 'validation',
                ], 422);
            }

            $result = $service->run($client, $clientMatterId);

            $hardCount = (int) ($result['match_count'] ?? 0);
            $infoCount = (int) ($result['informational_count'] ?? 0);
            $matterLabel = $this->conflictCheckMatterLabel($clientMatterId);

            $activityDetail = $hardCount === 0
                ? 'Automated search completed — no conflicts'
                : 'Automated search completed — ' . $hardCount . ' conflict(s)';
            if ($matterLabel) {
                $activityDetail = 'Matter ' . $matterLabel . ' — ' . $activityDetail;
            }
            if ($infoCount > 0) {
                $activityDetail .= ' · ' . $infoCount . ' informational note(s)';
            }
            if (! empty($result['warnings'])) {
                $activityDetail .= ' · ' . count($result['warnings']) . ' note(s)';
            }

            $this->logClientActivity(
                $client->id,
                'conflict check search run',
                $activityDetail,
                'activity'
            );

            if ($hardCount === 0 && $infoCount === 0) {
                $message = 'No potential conflicts found. Review and save outcome as Clear if appropriate.';
            } elseif ($hardCount === 0) {
                $message = 'No potential conflicts found. ' . $infoCount . ' informational note(s) listed for awareness.';
            } else {
                $message = $hardCount . ' potential conflict(s) found. Review carefully before saving an outcome.';
            }

            $currentSearchHash = $service->buildSearchHash($result['search_terms']);
            $referenceClear = app(ConflictCheckStalenessService::class)
                ->findLatestClearOrWaived((int) $client->id, $clientMatterId);
            $staleness = app(ConflictCheckStalenessService::class)
                ->evaluateStaleness($client, $clientMatterId, $referenceClear);

            return response()->json([
                'success' => true,
                'message' => $message,
                'search_terms' => $result['search_terms'],
                'search_hash' => $currentSearchHash,
                'matches' => $result['matches'],
                'informational_matches' => $result['informational_matches'] ?? [],
                'suggested_outcome' => $result['suggested_outcome'],
                'match_count' => $hardCount,
                'informational_count' => $infoCount,
                'warnings' => $result['warnings'] ?? [],
                'party_count' => $result['party_count'] ?? 0,
                'client_matter_id' => $clientMatterId,
                'staleness' => [
                    'is_stale' => $staleness['is_stale'],
                    'reason' => $staleness['reason'],
                ],
                'latest_check_id' => $referenceClear?->id,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_type' => 'validation',
            ], 422);
        } catch (QueryException $e) {
            Log::error('Conflict check database error', [
                'client_id' => $client->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Conflict search could not complete due to a database error. Please try again or contact support if this continues.',
                'error_type' => 'database',
            ], 500);
        } catch (\Exception $e) {
            Log::error('Conflict check failed', [
                'client_id' => $client->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Conflict search could not be completed. Please try again.',
                'error_type' => 'general',
            ], 500);
        }
    }

    /**
     * Return stored conflict-check snapshot for history detail (matches + metadata).
     */
    public function getConflictCheckDetail(Request $request, ConflictCheckService $service, int $checkId)
    {
        $clientId = (int) $request->input('client_id', $request->input('id', 0));
        $check = ClientConflictCheck::query()
            ->with(['checkedBy', 'clientMatter'])
            ->find($checkId);

        if (! $check) {
            return response()->json(['success' => false, 'message' => 'Conflict check not found.'], 404);
        }

        if ($clientId > 0 && (int) $check->client_id !== $clientId) {
            return response()->json(['success' => false, 'message' => 'Conflict check not found.'], 404);
        }

        $viewer = Auth::guard('admin')->user() ?? Auth::user();
        if (! \App\Support\StaffClientVisibility::canAccessClientOrLead((int) $check->client_id, $viewer)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $matches = $service->sanitizeStoredMatchesForViewer(is_array($check->matches) ? $check->matches : []);
        $informational = $service->sanitizeStoredMatchesForViewer(
            is_array($check->informational_matches) ? $check->informational_matches : []
        );

        $checkedBy = $check->checkedBy;
        $checkedByName = $checkedBy
            ? trim(($checkedBy->first_name ?? '') . ' ' . ($checkedBy->last_name ?? ''))
            : null;

        return response()->json([
            'success' => true,
            'check' => [
                'id' => $check->id,
                'outcome' => $check->outcome,
                'checked_at' => $check->checked_at?->format('d M Y H:i'),
                'checked_by' => $checkedByName ?: null,
                'match_count' => (int) ($check->match_count ?? count($matches)),
                'informational_count' => (int) ($check->informational_count ?? count($informational)),
                'search_hash' => $check->search_hash ? substr((string) $check->search_hash, 0, 12) . '…' : null,
                'matter_label' => $check->clientMatter?->client_unique_matter_no,
                'outcome_notes' => $check->outcome_notes,
                'matches' => $matches,
                'informational_matches' => $informational,
            ],
        ]);
    }

    /**
     * Decode JSON array/object from request (FormData may send a JSON string).
     */
    private function decodeJsonField(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function saveBasicInfoSection($request, $client)
    {
        try {
            $validated = $request->validate([
                'first_name' => 'required|max:255',
                'last_name' => 'nullable|max:255',
                'client_id' => 'required|max:255|unique:admins,client_id,' . $client->id,
                'dob' => [
                    'nullable',
                    'date_format:d/m/Y',
                    function ($attribute, $value, $fail) {
                        if (empty($value)) return;
                        try {
                            $date = Carbon::createFromFormat('d/m/Y', $value);
                            if ($date->isFuture()) {
                                $fail('The date of birth cannot be a future date.');
                            }
                        } catch (\Exception $e) {}
                    }
                ],
                'age' => 'nullable|string',
                'gender' => 'nullable|in:Male,Female,Other',
                'marital_status' => 'nullable|in:Never Married,Engaged,Married,De Facto,Defacto,Separated,Divorced,Widowed,Single'
            ]);

            // Convert DOB format and calculate age (like the working methods)
            $dob = null;
            $age = null;
            if (!empty($validated['dob'])) {
                try {
                    $dobDate = \Carbon\Carbon::createFromFormat('d/m/Y', $validated['dob']);
                    $dob = $dobDate->format('Y-m-d');
                    $age = $dobDate->diff(\Carbon\Carbon::now())->format('%y years %m months');
                } catch (\Exception $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid date format. Must be dd/mm/yyyy.'
                    ], 422);
                }
            }

            // Map marital status values for backward compatibility
            $maritalStatus = $validated['marital_status'] ?? null;
            if ($maritalStatus === 'Defacto') {
                $maritalStatus = 'De Facto';
            }
            if ($maritalStatus === 'Single') {
                $maritalStatus = 'Never Married';
            }

            $previousLeadStatus = ($client->type === 'lead') ? $client->lead_status : null;

            if ($client->type === 'lead') {
                $pipelineStages = LeadFollowUpNoteService::pipelineStatuses();
                $currentStage = (string) ($client->lead_status ?? '');
                if ($currentStage !== '' && ! in_array($currentStage, $pipelineStages, true)) {
                    $pipelineStages[] = $currentStage;
                }

                $request->validate([
                    'lead_status' => ['sometimes', Rule::in($pipelineStages)],
                    'followup_date' => 'nullable|date',
                    'assigned_staff_id' => 'nullable|exists:staff,id',
                ]);

                if ($request->has('lead_status')) {
                    $client->lead_status = (string) $request->input('lead_status');
                }

                if ($request->has('followup_date')) {
                    $rawFd = $request->input('followup_date');
                    if ($rawFd === '' || $rawFd === null) {
                        if ($client->lead_status !== 'follow_up') {
                            $client->followup_date = null;
                        }
                    } else {
                        $client->followup_date = Carbon::parse($rawFd)->format('Y-m-d H:i:s');
                    }
                }

                if ($client->lead_status !== 'follow_up') {
                    $client->followup_date = null;
                }

                $client->status = LeadFollowUpNoteService::adminsStatusForLeadStatus($client->lead_status);

                if ($request->filled('assigned_staff_id')) {
                    $client->user_id = (int) $request->input('assigned_staff_id');
                }
            }

            // Track changed fields for activity log with old and new values
            $changedFields = [];
            $fieldLabels = [
                'first_name' => 'First Name',
                'last_name' => 'Last Name',
                'client_id' => 'Client ID',
                'dob' => 'Date of Birth',
                'gender' => 'Gender',
                'marital_status' => 'Marital Status'
            ];

            // Compare and track changes with old and new values
            if ($client->first_name !== $validated['first_name']) {
                $changedFields[$fieldLabels['first_name']] = [
                    'old' => $client->first_name,
                    'new' => $validated['first_name']
                ];
            }
            if ($client->last_name !== ($validated['last_name'] ?? null)) {
                $changedFields[$fieldLabels['last_name']] = [
                    'old' => $client->last_name,
                    'new' => $validated['last_name'] ?? null
                ];
            }
            if ($client->client_id !== $validated['client_id']) {
                $changedFields[$fieldLabels['client_id']] = [
                    'old' => $client->client_id,
                    'new' => $validated['client_id']
                ];
            }
            if ($client->dob !== $dob) {
                $changedFields[$fieldLabels['dob']] = [
                    'old' => $client->dob,
                    'new' => $dob
                ];
            }
            if ($client->gender !== ($validated['gender'] ?? null)) {
                $changedFields[$fieldLabels['gender']] = [
                    'old' => $client->gender,
                    'new' => $validated['gender'] ?? null
                ];
            }
            if ($client->marital_status !== $maritalStatus) {
                $changedFields[$fieldLabels['marital_status']] = [
                    'old' => $client->marital_status,
                    'new' => $maritalStatus
                ];
            }

            // Use direct assignment pattern (like the working old methods)
            $client->first_name = $validated['first_name'];
            $client->last_name = $validated['last_name'] ?? null;
            $client->client_id = $validated['client_id'];
            $client->dob = $dob;
            $client->age = $age;
            $client->gender = $validated['gender'] ?? null;
            $client->marital_status = $maritalStatus;
            $client->save();

            if ($client->type === 'lead') {
                $lead = Lead::find($client->id);
                if ($lead) {
                    app(LeadFollowUpNoteService::class)->syncNotesForLead($lead, $previousLeadStatus);
                }
            }

            // Log activity with specific changed fields
            if (!empty($changedFields)) {
                $this->logClientActivityWithChanges(
                    $client->id,
                    'updated basic information',
                    $changedFields,
                    'activity'
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Basic information updated successfully'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }
    }

    private function savePhoneNumbersSection($request, $client)
    {
        try {
            $phoneNumbers = json_decode($request->input('phone_numbers'), true);
            
            if (!is_array($phoneNumbers)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid phone numbers data'
                ], 400);
            }

            // Validate that at least one phone number is provided
            if (empty($phoneNumbers) || !array_filter(array_column($phoneNumbers, 'phone'))) {
                return response()->json([
                    'success' => false,
                    'message' => 'At least one phone number is required'
                ], 422);
            }

            // Check for duplicate Personal phone types (only one Personal phone allowed)
            $personalPhoneCount = 0;
            foreach ($phoneNumbers as $phoneData) {
                if (isset($phoneData['contact_type']) && $phoneData['contact_type'] === 'Personal') {
                    $personalPhoneCount++;
                }
            }
            if ($personalPhoneCount > 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only one phone number can be marked as Personal'
                ], 422);
            }

            // Validate each phone number and check for duplicates within the same client
            foreach ($phoneNumbers as $index => $phoneData) {
                if (!empty($phoneData['phone'])) {
                    $contactType = $phoneData['contact_type'] ?? null;
                    $phone = $phoneData['phone'];
                    $countryCode = $phoneData['country_code'] ?? '';

                    // Use centralized validation
                    $validation = \App\Helpers\PhoneValidationHelper::validatePhoneNumber($phone);
                    if (!$validation['valid']) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Phone number ' . ($index + 1) . ': ' . $validation['message']
                        ], 422);
                    }

                    // Skip duplicate check for placeholder numbers
                    if (!$validation['is_placeholder']) {
                        // Check for duplicate phone numbers within the same client
                        // Convert empty string to null for proper handling
                        $contactIdForCheck = !empty($phoneData['id']) ? $phoneData['id'] : null;
                        
                        $duplicatePhoneQuery = ClientContact::where('phone', $phone)
                            ->where('country_code', $countryCode)
                            ->where('client_id', $client->id);
                        
                        // Only exclude current contact ID if it's a valid ID (not empty/null)
                        if ($contactIdForCheck) {
                            $duplicatePhoneQuery->where('id', '!=', $contactIdForCheck);
                        }
                        
                        $duplicatePhone = $duplicatePhoneQuery->first();

                        if ($duplicatePhone) {
                            return response()->json([
                                'success' => false,
                                'message' => 'This phone number is already taken for this client: ' . $countryCode . $phone
                            ], 422);
                        }
                    }
                }
            }

            // Get existing phone numbers before update for change tracking
            $existingPhones = ClientContact::where('client_id', $client->id)->get()->keyBy('id');
            $oldPhoneDisplay = [];
            foreach ($existingPhones as $existing) {
                $display = ($existing->country_code ? $existing->country_code : '') . $existing->phone;
                if ($existing->contact_type) {
                    $display .= ' (' . $existing->contact_type . ')';
                }
                $oldPhoneDisplay[] = $display;
            }
            $oldPhoneDisplayStr = !empty($oldPhoneDisplay) ? implode(', ', $oldPhoneDisplay) : '(empty)';

            // Handle special cases for duplicate phone (Option 2: Add timestamp only when duplicate exists)
            $timestamp = time();

            // Process phone numbers with proper update/insert logic (like the old working system)
            $processedPhones = [];
            foreach ($phoneNumbers as $phoneData) {
                if (!empty($phoneData['phone'])) {
                    // Convert empty string to null for proper handling
                    $contactId = !empty($phoneData['id']) ? $phoneData['id'] : null;
                    $contactType = $phoneData['contact_type'] ?? null;
                    $phone = $phoneData['phone'];
                    $countryCode = $phoneData['country_code'] ?? '';
                    
                    // Check for duplicates across all clients and handle universal number (4444444444)
                    // Check in admins table (excluding current client) when column exists
                    $existingPhoneInAdmins = Schema::hasColumn('admins', 'phone')
                        ? Admin::where('phone', $phone)
                            ->where('id', '!=', $client->id)
                            ->first()
                        : null;
                    
                    // Check in client_contacts table (excluding current client and current contact)
                    $existingPhoneInContacts = ClientContact::where('phone', $phone)
                        ->where('country_code', $countryCode)
                        ->where('client_id', '!=', $client->id)
                        ->when($contactId, function($q) use ($contactId) {
                            return $q->where('id', '!=', $contactId);
                        })
                        ->first();
                    
                    // If duplicate exists and it's a universal number, add timestamp
                    if (($existingPhoneInAdmins || $existingPhoneInContacts) && $phone === '4444444444') {
                        $phone = $phone . '_' . $timestamp;
                        Log::info('Phone number modified to: ' . $phone);
                    } else if ($existingPhoneInAdmins || $existingPhoneInContacts) {
                        // Non-universal duplicate - check if it's within the same client (allowed)
                        $duplicateInSameClient = ClientContact::where('phone', $phone)
                            ->where('country_code', $countryCode)
                            ->where('client_id', $client->id)
                            ->when($contactId, function($q) use ($contactId) {
                                return $q->where('id', '!=', $contactId);
                            })
                            ->first();
                        
                        // Only error if duplicate is in a different client
                        if (!$duplicateInSameClient) {
                            return response()->json([
                                'success' => false,
                                'message' => "Phone number '{$countryCode}{$phoneData['phone']}' is already registered for another client."
                            ], 422);
                        }
                    }

                    if ($contactId) {
                        // Update existing contact if ID is provided
                        $existingContact = ClientContact::find($contactId);
                        if ($existingContact && $existingContact->client_id == $client->id) {
                            $existingContact->update([
                                'admin_id' => Auth::user()->id,
                                'contact_type' => $contactType,
                                'phone' => $phone,
                                'country_code' => $countryCode
                            ]);
                            $processedPhones[] = $existingContact->id;
                        }
                    } else {
                        // Insert new contact if no ID is provided
                        $newContact = ClientContact::create([
                            'admin_id' => Auth::user()->id,
                            'client_id' => $client->id,
                            'contact_type' => $contactType,
                            'phone' => $phone,
                            'country_code' => $countryCode,
                            'is_verified' => false
                        ]);
                        $processedPhones[] = $newContact->id;
                    }
                }
            }

            // Remove any phone numbers that were not in the processed list (like the old system)
            if (!empty($processedPhones)) {
                ClientContact::where('client_id', $client->id)
                    ->whereNotIn('id', $processedPhones)
                    ->delete();
            }

            // Update client's primary phone info (like the old system)
            // Get the last phone from processed contacts (to ensure we use modified values if any)
            $lastPhone = null;
            $lastContactType = null;
            $lastCountryCode = null;
            
            if (!empty($processedPhones)) {
                // Get the last processed phone contact to use its values (which may have been modified)
                $lastContact = ClientContact::where('client_id', $client->id)
                    ->whereIn('id', $processedPhones)
                    ->orderBy('id', 'desc')
                    ->first();
                
                if ($lastContact) {
                    $lastPhone = $lastContact->phone;
                    $lastContactType = $lastContact->contact_type;
                    $lastCountryCode = $lastContact->country_code;
                }
            }
            
            // Fallback to last phone in array if no processed phones found
            if (!$lastPhone && !empty($phoneNumbers)) {
                $lastPhoneData = end($phoneNumbers);
                if (!empty($lastPhoneData['phone'])) {
                    $lastPhone = $lastPhoneData['phone'];
                    $lastContactType = $lastPhoneData['contact_type'] ?? null;
                    $lastCountryCode = $lastPhoneData['country_code'] ?? '';
                }
            }

            if ($lastPhone) {
                if (Schema::hasColumn('admins', 'phone')) {
                    $client->phone = $lastPhone;
                }
                if (Schema::hasColumn('admins', 'contact_type')) {
                    $client->contact_type = $lastContactType;
                }
                if (Schema::hasColumn('admins', 'country_code')) {
                    $client->country_code = $lastCountryCode;
                }
                if ($client->isDirty()) {
                    $client->save();
                }
            }

            // Get new phone numbers for change tracking
            $newPhones = ClientContact::where('client_id', $client->id)->get();
            $newPhoneDisplay = [];
            foreach ($newPhones as $newPhone) {
                $display = ($newPhone->country_code ? $newPhone->country_code : '') . $newPhone->phone;
                if ($newPhone->contact_type) {
                    $display .= ' (' . $newPhone->contact_type . ')';
                }
                $newPhoneDisplay[] = $display;
            }
            $newPhoneDisplayStr = !empty($newPhoneDisplay) ? implode(', ', $newPhoneDisplay) : '(empty)';

            // Log activity with before/after values
            $changedFields = [];
            if ($oldPhoneDisplayStr !== $newPhoneDisplayStr) {
                $changedFields['Phone Numbers'] = [
                    'old' => $oldPhoneDisplayStr,
                    'new' => $newPhoneDisplayStr
                ];
            }

            if (!empty($changedFields)) {
                $this->logClientActivityWithChanges(
                    $client->id,
                    'updated phone numbers',
                    $changedFields,
                    'activity'
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Phone numbers updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving phone numbers: ' . $e->getMessage()
            ], 500);
        }
    }

    private function saveEmailAddressesSection($request, $client)
    {
        try {
            $emails = json_decode($request->input('emails'), true);
            
            if (!is_array($emails)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid email addresses data'
                ], 400);
            }

            // Get existing emails before update for change tracking
            $existingEmails = ClientEmail::where('client_id', $client->id)->get();
            $oldEmailDisplay = [];
            foreach ($existingEmails as $existing) {
                $display = $existing->email;
                if ($existing->email_type) {
                    $display .= ' (' . $existing->email_type . ')';
                }
                $oldEmailDisplay[] = $display;
            }
            $oldEmailDisplayStr = !empty($oldEmailDisplay) ? implode(', ', $oldEmailDisplay) : '(empty)';

            // Handle special cases for duplicate email (Option 2: Add timestamp only when duplicate exists)
            $timestamp = time();

            // Track which email IDs should be kept (both updated and newly created)
            $emailIdsToKeep = [];
            $primaryEmail = null;
            $primaryEmailType = 'Personal';

            // Process each email record (update existing or create new)
            foreach ($emails as $emailData) {
                if (!empty($emailData['email'])) {
                    $email = $emailData['email'];
                    $emailId = $emailData['id'] ?? null;
                    $emailId = !empty($emailId) ? (int)$emailId : null;
                    
                    // Check for duplicates and handle universal number (demo@gmail.com)
                    // Check in admins table (excluding current client) when column exists
                    $existingEmailInAdmins = Schema::hasColumn('admins', 'email')
                        ? Admin::where('email', $email)
                            ->where('id', '!=', $client->id)
                            ->first()
                        : null;
                    
                    // Check in client_emails table (excluding current client and current email)
                    $existingEmailInClientEmails = ClientEmail::where('email', $email)
                        ->where('client_id', '!=', $client->id)
                        ->when($emailId, function($q) use ($emailId) {
                            return $q->where('id', '!=', $emailId);
                        })
                        ->first();
                    
                    // If duplicate exists and it's a universal number, add timestamp
                    if (($existingEmailInAdmins || $existingEmailInClientEmails) && $email === 'demo@gmail.com') {
                        $emailParts = explode('@', $email);
                        $localPart = $emailParts[0];
                        $domainPart = $emailParts[1];
                        $email = $localPart . '_' . $timestamp . '@' . $domainPart;
                        Log::info('Email address modified to: ' . $email);
                    } else if ($existingEmailInAdmins || $existingEmailInClientEmails) {
                        // Non-universal duplicate - check if it's within the same client (allowed)
                        $duplicateInSameClient = ClientEmail::where('email', $email)
                            ->where('client_id', $client->id)
                            ->when($emailId, function($q) use ($emailId) {
                                return $q->where('id', '!=', $emailId);
                            })
                            ->first();
                        
                        // Only error if duplicate is in a different client
                        if (!$duplicateInSameClient) {
                            $allowSharedCompanyEmail = $client->is_company
                                && app(CompanyDirectorEmailService::class)->canReuseEmailForCompanyDirector(
                                    $email,
                                    (int) $client->id,
                                    (int) $client->id
                                );
                            if (! $allowSharedCompanyEmail) {
                                return response()->json([
                                    'success' => false,
                                    'message' => "Email address '{$emailData['email']}' is already registered for another client."
                                ], 422);
                            }
                        }
                    }
                    
                    if ($emailId) {
                        // Update existing email if ID is provided
                        $existingEmail = ClientEmail::find($emailId);
                        if ($existingEmail && $existingEmail->client_id == $client->id) {
                            $existingEmail->update([
                                'admin_id' => Auth::user()->id,
                                'email_type' => $emailData['email_type'],
                                'email' => $email // Use potentially modified email
                            ]);
                            $emailIdsToKeep[] = $emailId;
                        } else {
                            // ID provided but doesn't exist, create new
                            $newEmail = ClientEmail::create([
                                'client_id' => $client->id,
                                'admin_id' => Auth::user()->id,
                                'email_type' => $emailData['email_type'],
                                'email' => $email, // Use potentially modified email
                                'is_verified' => false
                            ]);
                            $emailIdsToKeep[] = $newEmail->id;
                        }
                    } else {
                        // Create new email
                        $newEmail = ClientEmail::create([
                            'client_id' => $client->id,
                            'admin_id' => Auth::user()->id,
                            'email_type' => $emailData['email_type'],
                            'email' => $email, // Use potentially modified email
                            'is_verified' => false
                        ]);
                        $emailIdsToKeep[] = $newEmail->id;
                    }
                    
                    // Set primary email for admins table update (use potentially modified email)
                    if ($emailData['email_type'] === 'Personal' || empty($primaryEmail)) {
                        $primaryEmail = $email;
                        $primaryEmailType = $emailData['email_type'];
                    }
                }
            }
            
            // Delete email records that were not in the request
            if (!empty($emailIdsToKeep)) {
                ClientEmail::where('client_id', $client->id)
                    ->whereNotIn('id', $emailIdsToKeep)
                    ->delete();
            }
            
            // Update admins table with primary email (only columns that exist)
            if (!empty($primaryEmail)) {
                if (Schema::hasColumn('admins', 'email')) {
                    $client->email = $primaryEmail;
                }
                if (Schema::hasColumn('admins', 'email_type')) {
                    $client->email_type = $primaryEmailType;
                }
                if ($client->isDirty()) {
                    $client->save();
                }
            }

            // Get new emails for change tracking
            $newEmails = ClientEmail::where('client_id', $client->id)->get();

            // Log activity with intelligent diff showing only actual changes
            try {
                $diffResult = $this->buildEmailDiff($existingEmails, $newEmails);
                
                if (!empty($diffResult['added']) || !empty($diffResult['removed']) || !empty($diffResult['modified'])) {
                    $description = $this->formatEmailDiffForActivityLog($diffResult);
                    
                    $this->logClientActivity(
                        $client->id,
                        'updated email addresses',
                        $description,
                        'activity'
                    );
                }
            } catch (\Exception $e) {
                // Fallback to simple comparison if diff fails
                Log::warning('Email diff failed, using simple comparison', [
                    'error' => $e->getMessage()
                ]);
                
                $newEmailDisplay = [];
                foreach ($newEmails as $newEmail) {
                    $display = $newEmail->email;
                    if ($newEmail->email_type) {
                        $display .= ' (' . $newEmail->email_type . ')';
                    }
                    $newEmailDisplay[] = $display;
                }
                $newEmailDisplayStr = !empty($newEmailDisplay) ? implode(', ', $newEmailDisplay) : '(empty)';
                
                if ($oldEmailDisplayStr !== $newEmailDisplayStr) {
                    $this->logClientActivityWithChanges(
                        $client->id,
                        'updated email addresses',
                        ['Email Addresses' => [
                            'old' => $oldEmailDisplayStr,
                            'new' => $newEmailDisplayStr
                        ]],
                        'activity'
                    );
                }
            }

            // Get the newly saved emails with their IDs
            $savedEmails = ClientEmail::where('client_id', $client->id)
                ->orderBy('id', 'asc')
                ->get()
                ->map(function($email) {
                    return [
                        'id' => $email->id,
                        'email' => $email->email,
                        'email_type' => $email->email_type,
                        'is_verified' => $email->is_verified,
                        'verified_at' => $email->verified_at
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Email addresses updated successfully',
                'emails' => $savedEmails
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving email addresses: ' . $e->getMessage()
            ], 500);
        }
    }

    private function saveAddressInfoSection($request, $client)
    {
        try {
            $requestData = $request->all();
            
            Log::info('Address save request data:', $requestData);

            // Handle explicit address deletion request
            if ($request->boolean('delete_address')) {
                $existingAddresses = ClientAddress::where('client_id', $client->id)->get();

                if ($existingAddresses->isEmpty()) {
                    return response()->json([
                        'success' => true,
                        'message' => 'No address to delete',
                    ]);
                }

                $addressId = $request->input('address_id');
                if ($addressId) {
                    $addressToDelete = ClientAddress::where('id', $addressId)
                        ->where('client_id', $client->id)
                        ->first();

                    if (!$addressToDelete) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Address not found',
                        ], 404);
                    }
                }

                $oldAddressDisplay = [];
                foreach ($existingAddresses as $existing) {
                    $display = [];
                    if ($existing->address_line_1) {
                        $display[] = $existing->address_line_1;
                    }
                    if ($existing->suburb) {
                        $display[] = $existing->suburb;
                    }
                    if ($existing->state) {
                        $display[] = $existing->state;
                    }
                    if ($existing->zip) {
                        $display[] = $existing->zip;
                    }
                    if ($existing->country) {
                        $display[] = $existing->country;
                    }
                    $oldAddressDisplay[] = !empty($display) ? implode(', ', $display) : 'Address record';
                }
                $oldAddressDisplayStr = !empty($oldAddressDisplay) ? implode(' | ', $oldAddressDisplay) : '(empty)';

                ClientAddress::where('client_id', $client->id)->delete();

                try {
                    $this->logClientActivityWithChanges(
                        $client->id,
                        'deleted current address',
                        ['Address Information' => [
                            'old' => $oldAddressDisplayStr,
                            'new' => '(empty)',
                        ]],
                        'activity'
                    );
                } catch (\Exception $e) {
                    Log::warning('Failed to log address deletion activity', [
                        'error' => $e->getMessage(),
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Current address deleted successfully',
                ]);
            }
            
            // Get existing addresses before update for change tracking
            $existingAddresses = ClientAddress::where('client_id', $client->id)->get();
            $existingAddressCount = $existingAddresses->count(); // Track count for safety check
            $oldAddressDisplay = [];
            foreach ($existingAddresses as $existing) {
                $display = [];
                if ($existing->address_line_1) {
                    $display[] = $existing->address_line_1;
                }
                if ($existing->suburb) {
                    $display[] = $existing->suburb;
                }
                if ($existing->state) {
                    $display[] = $existing->state;
                }
                if ($existing->zip) {
                    $display[] = $existing->zip;
                }
                if ($existing->country) {
                    $display[] = $existing->country;
                }
                if ($existing->start_date) {
                    $display[] = 'From: ' . date('d/m/Y', strtotime($existing->start_date));
                }
                if ($existing->end_date) {
                    $display[] = 'To: ' . date('d/m/Y', strtotime($existing->end_date));
                }
                $oldAddressDisplay[] = !empty($display) ? implode(', ', $display) : 'Address record';
            }
            $oldAddressDisplayStr = !empty($oldAddressDisplay) ? implode(' | ', $oldAddressDisplay) : '(empty)';
            
            if (isset($requestData['zip']) && is_array($requestData['zip'])) {
                // Track which address IDs should be kept (both updated and newly created)
                $addressIdsToKeep = [];
                
                // Single current address per client — only process the first submitted entry
                foreach ([
                    'zip', 'address_id', 'address_line_1', 'address_line_2',
                    'suburb', 'state', 'country', 'regional_code',
                    'address_start_date', 'address_end_date',
                ] as $addressField) {
                    if (isset($requestData[$addressField]) && is_array($requestData[$addressField])) {
                        $requestData[$addressField] = array_slice($requestData[$addressField], 0, 1);
                    }
                }
                
                // Process each address in the request
                foreach ($requestData['zip'] as $key => $zip) {
                    $address_line_1 = $requestData['address_line_1'][$key] ?? null;
                    $address_line_2 = $requestData['address_line_2'][$key] ?? null;
                    $suburb = $requestData['suburb'][$key] ?? null;
                    $state = $requestData['state'][$key] ?? null;
                    $country = $requestData['country'][$key] ?? 'Australia';
                    $regional_code = $requestData['regional_code'][$key] ?? null;
                    $start_date = $requestData['address_start_date'][$key] ?? null;
                    $end_date = $requestData['address_end_date'][$key] ?? null;
                    $address_id = $requestData['address_id'][$key] ?? null;
                    
                    // Clean up address_id - it might be empty string, null, or actual ID
                    $address_id = !empty($address_id) ? (int)$address_id : null;
                    
                    Log::info("Processing address entry $key:", [
                        'address_id' => $address_id ?: '(new)',
                        'zip' => $zip,
                        'address_line_1' => $address_line_1,
                        'suburb' => $suburb,
                        'state' => $state,
                        'country' => $country,
                        'regional_code' => $regional_code,
                        'start_date' => $start_date,
                        'end_date' => $end_date
                    ]);
                    
                    // Skip empty addresses (no address_line_1 and no zip)
                    if (empty($address_line_1) && empty($zip)) {
                        continue;
                    }
                    
                    // Date conversion
                    $formatted_start_date = null;
                    if (!empty($start_date)) {
                        try {
                            $date = Carbon::createFromFormat('d/m/Y', $start_date);
                            if ($date->isFuture()) {
                                return response()->json([
                                    'success' => false,
                                    'message' => 'The address start date cannot be a future date.'
                                ], 422);
                            }
                            $formatted_start_date = $date->format('Y-m-d');
                        } catch (\Exception $e) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Invalid Start Date format: ' . $start_date
                            ], 422);
                        }
                    }
                    
                    $formatted_end_date = null;
                    if (!empty($end_date)) {
                        try {
                            $date = Carbon::createFromFormat('d/m/Y', $end_date);
                            if ($date->isFuture()) {
                                return response()->json([
                                    'success' => false,
                                    'message' => 'The address end date cannot be a future date.'
                                ], 422);
                            }
                            $formatted_end_date = $date->format('Y-m-d');
                        } catch (\Exception $e) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Invalid End Date format: ' . $end_date
                            ], 422);
                        }
                    }
                    
                    // Create combined address for backward compatibility
                    $combined_address = $this->combineAddress([
                        'line1' => $address_line_1,
                        'line2' => $address_line_2,
                        'suburb' => $suburb,
                        'state' => $state,
                        'postcode' => $zip,
                        'country' => $country
                    ]);
                    
                    if ($address_id) {
                        // Update existing address
                        $existingAddress = ClientAddress::find($address_id);
                        if ($existingAddress && $existingAddress->client_id == $client->id) {
                            $existingAddress->update([
                                'admin_id' => Auth::user()->id,
                                'address' => $combined_address,
                                'address_line_1' => $address_line_1,
                                'address_line_2' => $address_line_2,
                                'suburb' => $suburb,
                                'state' => $state,
                                'country' => $country,
                                'zip' => $zip,
                                'regional_code' => $regional_code,
                                'start_date' => $formatted_start_date,
                                'end_date' => $formatted_end_date,
                                'is_current' => true,
                            ]);
                            // Track this ID to keep it
                            $addressIdsToKeep[] = $address_id;
                            Log::info("Updated address ID: $address_id");
                        } else {
                            // Address ID provided but doesn't exist or doesn't belong to client
                            // Create as new address instead
                            $newAddress = ClientAddress::create([
                                'admin_id' => Auth::user()->id,
                                'client_id' => $client->id,
                                'address' => $combined_address,
                                'address_line_1' => $address_line_1,
                                'address_line_2' => $address_line_2,
                                'suburb' => $suburb,
                                'state' => $state,
                                'country' => $country,
                                'zip' => $zip,
                                'regional_code' => $regional_code,
                                'start_date' => $formatted_start_date,
                                'end_date' => $formatted_end_date,
                                'is_current' => true,
                            ]);
                            // Track newly created ID to keep it
                            $addressIdsToKeep[] = $newAddress->id;
                            Log::info("Created new address (invalid ID provided), new ID: {$newAddress->id}");
                        }
                    } else {
                        // Create new address (no ID provided)
                        $newAddress = ClientAddress::create([
                            'admin_id' => Auth::user()->id,
                            'client_id' => $client->id,
                            'address' => $combined_address,
                            'address_line_1' => $address_line_1,
                            'address_line_2' => $address_line_2,
                            'suburb' => $suburb,
                            'state' => $state,
                            'country' => $country,
                            'zip' => $zip,
                            'regional_code' => $regional_code,
                            'start_date' => $formatted_start_date,
                            'end_date' => $formatted_end_date,
                            'is_current' => true,
                        ]);
                        // Track newly created ID to keep it
                        $addressIdsToKeep[] = $newAddress->id;
                        Log::info("Created new address, ID: {$newAddress->id}");
                    }
                }
                
                // Delete addresses that exist in DB but were not processed/created
                // This handles the case where user removes an address from the form
                Log::info('Address IDs to keep:', $addressIdsToKeep);
                
                // CRITICAL SAFETY CHECK: Prevent accidental deletion of all addresses
                // If there were existing addresses but $addressIdsToKeep is empty after processing,
                // this indicates all submitted addresses were empty (skipped). This is suspicious
                // and could indicate a bug or accidental empty form submission - prevent deletion.
                if (!empty($addressIdsToKeep)) {
                    $deletedCount = ClientAddress::where('client_id', $client->id)
                        ->whereNotIn('id', $addressIdsToKeep)
                        ->delete();
                    Log::info("Deleted $deletedCount addresses that were not in the request");
                } elseif ($existingAddressCount > 0) {
                    // Security safeguard: If there were existing addresses but none to keep,
                    // this is suspicious - log warning and prevent deletion to avoid data loss
                    Log::warning("SECURITY: Prevented deletion of all {$existingAddressCount} addresses for client {$client->id}. " .
                        "No valid addresses in request - this may indicate an empty form submission or bug.");
                }
            }
            
            // Get new addresses for change tracking
            $newAddresses = ClientAddress::where('client_id', $client->id)->get();
            $newAddressDisplay = [];
            foreach ($newAddresses as $newAddress) {
                $display = [];
                if ($newAddress->address_line_1) {
                    $display[] = $newAddress->address_line_1;
                }
                if ($newAddress->suburb) {
                    $display[] = $newAddress->suburb;
                }
                if ($newAddress->state) {
                    $display[] = $newAddress->state;
                }
                if ($newAddress->zip) {
                    $display[] = $newAddress->zip;
                }
                if ($newAddress->country) {
                    $display[] = $newAddress->country;
                }
                if ($newAddress->start_date) {
                    $display[] = 'From: ' . date('d/m/Y', strtotime($newAddress->start_date));
                }
                if ($newAddress->end_date) {
                    $display[] = 'To: ' . date('d/m/Y', strtotime($newAddress->end_date));
                }
                $newAddressDisplay[] = !empty($display) ? implode(', ', $display) : 'Address record';
            }
            $newAddressDisplayStr = !empty($newAddressDisplay) ? implode(' | ', $newAddressDisplay) : '(empty)';

            // Log activity with intelligent diff showing only actual changes
            try {
                $diffResult = $this->buildAddressDiff($existingAddresses, $newAddresses);
                
                if (!empty($diffResult['added']) || !empty($diffResult['removed']) || !empty($diffResult['modified'])) {
                    // Build HTML directly with proper formatting
                    $description = $this->formatAddressDiffForActivityLog($diffResult);
                    
                    Log::info('Creating activity log for address change', [
                        'added' => count($diffResult['added']),
                        'removed' => count($diffResult['removed']),
                        'modified' => count($diffResult['modified'])
                    ]);
                    
                    $this->logClientActivity(
                        $client->id,
                        'updated address information',
                        $description,
                        'activity'
                    );
                } else {
                    Log::info('No activity log created - addresses are identical');
                }
            } catch (\Exception $e) {
                // Fallback to simple comparison if diff fails
                Log::warning('Address diff failed, using simple comparison', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                if ($oldAddressDisplayStr !== $newAddressDisplayStr) {
                    $this->logClientActivityWithChanges(
                        $client->id,
                        'updated address information',
                        ['Address Information' => [
                            'old' => $oldAddressDisplayStr,
                            'new' => $newAddressDisplayStr
                        ]],
                        'activity'
                    );
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Address information updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving address information: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error saving address information: ' . $e->getMessage()
            ], 500);
        }
    }

    private function saveReferBySection($request, $client)
    {
        $validated = $request->validate([
            'refer_by' => 'nullable|string|max:500',
        ]);
        $raw = $validated['refer_by'] ?? null;
        $trimmed = $raw !== null ? trim((string) $raw) : '';
        $value = $trimmed === '' ? null : $trimmed;

        if (! Schema::hasColumn('admins', 'refer_by')) {
            return response()->json([
                'success' => false,
                'message' => 'Database is missing column admins.refer_by. Run migrations (add_refer_by_to_admins_table).',
            ], 503);
        }

        DB::table('admins')->where('id', (int) $client->id)->update([
            'refer_by' => $value,
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Refer by saved successfully',
        ]);
    }

    private function saveLeadSourceSection($request, $client)
    {
        $allowedSources = [
            'Online Enquiry','Walk-in','Phone Call','Email',
            'Referral','Word of Mouth','Social Media','Facebook',
            'Instagram','LinkedIn','Google','Google Ads',
            'Sub Agent','Legal Aid','Court Referral','Other','',
        ];

        $validated = $request->validate([
            'lead_source' => ['nullable','string','max:100'],
            'refer_by'    => 'nullable|string|max:500',
        ]);

        $source   = trim((string) ($validated['lead_source'] ?? ''));
        $referBy  = trim((string) ($validated['refer_by'] ?? ''));

        $updates = ['updated_at' => now()];

        if (Schema::hasColumn('admins', 'source')) {
            $updates['source'] = $source !== '' ? $source : null;
        }
        if (Schema::hasColumn('admins', 'refer_by')) {
            $updates['refer_by'] = $referBy !== '' ? $referBy : null;
        }

        DB::table('admins')->where('id', (int) $client->id)->update($updates);

        return response()->json([
            'success' => true,
            'message' => 'Lead source saved successfully.',
        ]);
    }

    private function buildAddressDiff($oldAddresses, $newAddresses)
    {
        $added = [];
        $removed = [];
        $modified = [];
        
        // Normalize addresses for comparison
        $oldNormalized = $this->normalizeAddressesForComparison($oldAddresses);
        $newNormalized = $this->normalizeAddressesForComparison($newAddresses);
        
        // Find added addresses (in new but not in old)
        foreach ($newNormalized as $newKey => $newAddr) {
            if (!isset($oldNormalized[$newKey])) {
                $added[] = $this->formatAddressForDisplay($newAddr);
            }
        }
        
        // Find removed addresses (in old but not in new)
        foreach ($oldNormalized as $oldKey => $oldAddr) {
            if (!isset($newNormalized[$oldKey])) {
                $removed[] = $this->formatAddressForDisplay($oldAddr);
            }
        }
        
        // Find modified addresses (same key but different details)
        foreach ($oldNormalized as $key => $oldAddr) {
            if (isset($newNormalized[$key])) {
                $newAddr = $newNormalized[$key];
                if ($this->isAddressModified($oldAddr, $newAddr)) {
                    $modified[] = [
                        'old' => $this->formatAddressForDisplay($oldAddr),
                        'new' => $this->formatAddressForDisplay($newAddr)
                    ];
                }
            }
        }
        
        return [
            'added' => $added,
            'removed' => $removed,
            'modified' => $modified
        ];
    }

    /**
     * Normalize addresses for comparison by creating a unique key
     * 
     * @param Collection $addresses
     * @return array Array keyed by comparison key
     */
    private function normalizeAddressesForComparison($addresses)
    {
        $normalized = [];
        
        foreach ($addresses as $address) {
            // Create comparison key from core address fields
            $key = strtolower(trim(
                ($address->address_line_1 ?? '') . '|' .
                ($address->suburb ?? '') . '|' .
                ($address->state ?? '') . '|' .
                ($address->zip ?? '')
            ));
            
            // Store full address object with the key
            $normalized[$key] = $address;
        }
        
        return $normalized;
    }

    /**
     * Format address for display in activity log
     * 
     * @param object $address Address object
     * @return string Formatted address string
     */
    private function formatAddressForDisplay($address)
    {
        $parts = [];
        
        if (!empty($address->address_line_1)) {
            $parts[] = $address->address_line_1;
        }
        if (!empty($address->address_line_2)) {
            $parts[] = $address->address_line_2;
        }
        if (!empty($address->suburb)) {
            $parts[] = $address->suburb;
        }
        if (!empty($address->state)) {
            $parts[] = $address->state;
        }
        if (!empty($address->zip)) {
            $parts[] = $address->zip;
        }
        if (!empty($address->country)) {
            $parts[] = $address->country;
        }
        if (!empty($address->start_date)) {
            $parts[] = 'From: ' . date('d/m/Y', strtotime($address->start_date));
        }
        if (!empty($address->end_date)) {
            $parts[] = 'To: ' . date('d/m/Y', strtotime($address->end_date));
        }
        
        return !empty($parts) ? implode(', ', $parts) : 'Address record';
    }

    /**
     * Check if an address has been modified (same location, different details)
     * 
     * @param object $oldAddr Old address
     * @param object $newAddr New address
     * @return bool True if modified
     */
    private function isAddressModified($oldAddr, $newAddr)
    {
        // Compare fields that might change
        $fieldsToCompare = [
            'address_line_2',
            'country',
            'regional_code',
            'start_date',
            'end_date'
        ];
        
        foreach ($fieldsToCompare as $field) {
            $oldValue = $oldAddr->$field ?? null;
            $newValue = $newAddr->$field ?? null;
            
            if ($oldValue !== $newValue) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Format address diff result for activity log with HTML styling
     * 
     * @param array $diffResult Result from buildAddressDiff()
     * @return string HTML formatted description
     */
    private function formatAddressDiffForActivityLog($diffResult)
    {
        $html = '<div style="margin-top: 5px;">';
        
        // Removed addresses (red strikethrough)
        foreach ($diffResult['removed'] as $addr) {
            $html .= '<div style="margin-bottom: 4px;">';
            $html .= '<span style="color: #dc3545; text-decoration: line-through;">';
            $html .= htmlspecialchars($addr);
            $html .= '</span>';
            $html .= '</div>';
        }
        
        // Modified addresses (old in red strikethrough → new in green)
        foreach ($diffResult['modified'] as $mod) {
            $html .= '<div style="margin-bottom: 4px;">';
            $html .= '<span style="color: #dc3545; text-decoration: line-through;">';
            $html .= htmlspecialchars($mod['old']);
            $html .= '</span>';
            $html .= ' <span style="color: #666;">→</span> ';
            $html .= '<span style="color: #28a745; font-weight: 600;">';
            $html .= htmlspecialchars($mod['new']);
            $html .= '</span>';
            $html .= '</div>';
        }
        
        // Added addresses (green)
        foreach ($diffResult['added'] as $addr) {
            $html .= '<div style="margin-bottom: 4px;">';
            $html .= '<span style="color: #28a745; font-weight: 600;">';
            $html .= htmlspecialchars($addr);
            $html .= '</span>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }

    private function buildEmailDiff($oldEmails, $newEmails)
    {
        $added = [];
        $removed = [];
        $modified = [];
        
        $oldNormalized = $this->normalizeEmailsForComparison($oldEmails);
        $newNormalized = $this->normalizeEmailsForComparison($newEmails);
        
        foreach ($newNormalized as $newKey => $newEmail) {
            if (!isset($oldNormalized[$newKey])) {
                $added[] = $this->formatEmailForDisplay($newEmail);
            }
        }
        
        foreach ($oldNormalized as $oldKey => $oldEmail) {
            if (!isset($newNormalized[$oldKey])) {
                $removed[] = $this->formatEmailForDisplay($oldEmail);
            }
        }
        
        foreach ($oldNormalized as $key => $oldEmail) {
            if (isset($newNormalized[$key])) {
                $newEmail = $newNormalized[$key];
                if ($this->isEmailModified($oldEmail, $newEmail)) {
                    $modified[] = [
                        'old' => $this->formatEmailForDisplay($oldEmail),
                        'new' => $this->formatEmailForDisplay($newEmail)
                    ];
                }
            }
        }
        
        return ['added' => $added, 'removed' => $removed, 'modified' => $modified];
    }

    private function normalizeEmailsForComparison($emails)
    {
        $normalized = [];
        foreach ($emails as $email) {
            $key = strtolower(trim($email->email ?? ''));
            $normalized[$key] = $email;
        }
        return $normalized;
    }

    private function formatEmailForDisplay($email)
    {
        $display = $email->email ?? '';
        if (!empty($email->email_type)) {
            $display .= ' (' . $email->email_type . ')';
        }
        return $display;
    }

    private function isEmailModified($oldEmail, $newEmail)
    {
        return ($oldEmail->email_type ?? null) !== ($newEmail->email_type ?? null);
    }

    private function formatEmailDiffForActivityLog($diffResult)
    {
        $html = '<div style="margin-top: 5px;">';
        
        foreach ($diffResult['removed'] as $email) {
            $html .= '<div style="margin-bottom: 4px;">';
            $html .= '<span style="color: #dc3545; text-decoration: line-through;">';
            $html .= htmlspecialchars($email);
            $html .= '</span></div>';
        }
        
        foreach ($diffResult['modified'] as $mod) {
            $html .= '<div style="margin-bottom: 4px;">';
            $html .= '<span style="color: #dc3545; text-decoration: line-through;">';
            $html .= htmlspecialchars($mod['old']);
            $html .= '</span> <span style="color: #666;">→</span> ';
            $html .= '<span style="color: #28a745; font-weight: 600;">';
            $html .= htmlspecialchars($mod['new']);
            $html .= '</span></div>';
        }
        
        foreach ($diffResult['added'] as $email) {
            $html .= '<div style="margin-bottom: 4px;">';
            $html .= '<span style="color: #28a745; font-weight: 600;">';
            $html .= htmlspecialchars($email);
            $html .= '</span></div>';
        }
        
        $html .= '</div>';
        return $html;
    }
}
