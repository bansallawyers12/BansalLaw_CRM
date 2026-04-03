<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\RequestException;
use Exception;

class OthersController extends Controller
{
    /**
     * Get Bansal API configuration
     * 
     * @return array Returns array with 'baseUrl', 'apiToken', and 'timeout'
     */
    private function getBansalApiConfig()
    {
        return [
            'baseUrl' => config('services.bansal_api.url', 'https://www.bansalimmigration.com.au/api/crm'),
            'apiToken' => config('services.bansal_api.token'),
            'timeout' => config('services.bansal_api.timeout', 30)
        ];
    }

    /**
     * Get Blog List
     * GET /api/blogs/list
     */
    public function getBlogList(Request $request)
    {
        try {
            $config = $this->getBansalApiConfig();
            $baseUrl = $config['baseUrl'];
            $apiToken = $config['apiToken'];
            $timeout = $config['timeout'];

            if (empty($apiToken)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bansal API token not configured. Set BANSAL_API_TOKEN in .env'
                ], 500);
            }

            // Get query parameters from request
            $page = $request->get('page', 1);
            $searchQuery = $request->get('q');
            $featured = $request->get('featured');

            // Build query parameters array
            $queryParams = [
                'page' => $page
            ];

            // Add optional parameters if provided
            if ($searchQuery !== null && $searchQuery !== '') {
                $queryParams['q'] = $searchQuery;
            }

            if ($featured !== null && $featured !== '') {
                $queryParams['featured'] = $featured;
            }

            // Make API call to Bansal API
            $response = Http::timeout($timeout)
                ->withToken($apiToken)
                ->acceptJson()
                ->get("{$baseUrl}/blogs/list", $queryParams);

            if ($response->failed()) {
                Log::error('Bansal API Blog List Error', [
                    'method' => 'getBlogList',
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'query_params' => $queryParams
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch blog list from external API',
                    'error' => $response->status() === 404 ? 'Blog list not found' : 'API request failed'
                ], $response->status());
            }

            $data = $response->json();

            // Return the response as-is from the external API
            return response()->json($data, $response->status());

        } catch (RequestException $e) {
            $response = $e->response;
            $responseBody = $response?->json();
            $message = null;

            if (is_array($responseBody)) {
                $message = $responseBody['message']
                    ?? ($responseBody['error']['message'] ?? null);
            }

            $message = $message ?: $response?->body() ?: $e->getMessage();

            Log::error('Bansal API Blog List Request Error', [
                'method' => 'getBlogList',
                'status' => $response?->status(),
                'body' => $response?->body(),
                'error' => $message,
                'query_params' => [
                    'page' => $request->get('page', 1),
                    'q' => $request->get('q'),
                    'featured' => $request->get('featured')
                ]
            ]);

            return response()->json([
                'success' => false,
                'message' => $message ?: 'Failed to fetch blog list',
                'error' => 'API request failed'
            ], $response?->status() ?: 500);

        } catch (Exception $e) {
            Log::error('Bansal API Blog List Error', [
                'method' => 'getBlogList',
                'error_type' => get_class($e),
                'error' => $e->getMessage(),
                'query_params' => [
                    'page' => $request->get('page', 1),
                    'q' => $request->get('q'),
                    'featured' => $request->get('featured')
                ]
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while fetching blog list',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Blog Detail (requires Sanctum Bearer token — route: auth:sanctum)
     * GET /api/blogs/detail/{id}
     */
    public function getBlogDetail(Request $request, $id)
    {
        try {
            $config = $this->getBansalApiConfig();
            $baseUrl = $config['baseUrl'];
            $apiToken = $config['apiToken'];
            $timeout = $config['timeout'];

            if (empty($apiToken)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bansal API token not configured. Set BANSAL_API_TOKEN in .env'
                ], 500);
            }

            // Validate ID
            if (empty($id) || !is_numeric($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid blog ID provided'
                ], 400);
            }

            // Make API call to Bansal API
            $response = Http::timeout($timeout)
                ->withToken($apiToken)
                ->acceptJson()
                ->get("{$baseUrl}/blogs/detail/{$id}");

            if ($response->failed()) {
                Log::error('Bansal API Blog Detail Error', [
                    'method' => 'getBlogDetail',
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'id' => $id
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch blog detail from external API',
                    'error' => $response->status() === 404 ? 'Blog not found' : 'API request failed'
                ], $response->status());
            }

            $data = $response->json();

            // Return the response as-is from the external API
            return response()->json($data, $response->status());

        } catch (RequestException $e) {
            $response = $e->response;
            $responseBody = $response?->json();
            $message = null;

            if (is_array($responseBody)) {
                $message = $responseBody['message']
                    ?? ($responseBody['error']['message'] ?? null);
            }

            $message = $message ?: $response?->body() ?: $e->getMessage();

            Log::error('Bansal API Blog Detail Request Error', [
                'method' => 'getBlogDetail',
                'status' => $response?->status(),
                'body' => $response?->body(),
                'error' => $message,
                'id' => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => $message ?: 'Failed to fetch blog detail',
                'error' => 'API request failed'
            ], $response?->status() ?: 500);

        } catch (Exception $e) {
            Log::error('Bansal API Blog Detail Error', [
                'method' => 'getBlogDetail',
                'error_type' => get_class($e),
                'error' => $e->getMessage(),
                'id' => $id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while fetching blog detail',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get PR Point Calculator Lists
     * GET /api/pr-point-calc-lists
     */
    public function getPrPointCalcLists(Request $request)
    {
        try {
            $config = $this->getBansalApiConfig();
            $baseUrl = $config['baseUrl'];
            $apiToken = $config['apiToken'];
            $timeout = $config['timeout'];

            if (empty($apiToken)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bansal API token not configured. Set BANSAL_API_TOKEN in .env'
                ], 500);
            }

            // Make API call to Bansal API
            $response = Http::timeout($timeout)
                ->withToken($apiToken)
                ->acceptJson()
                ->get("{$baseUrl}/pr-point-calc-lists");

            if ($response->failed()) {
                Log::error('Bansal API PR Point Calculator Lists Error', [
                    'method' => 'getPrPointCalcLists',
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch PR Point Calculator lists from external API',
                    'error' => $response->status() === 404 ? 'PR Point Calculator lists not found' : 'API request failed'
                ], $response->status());
            }

            $data = $response->json();

            // Return the response as-is from the external API
            return response()->json($data, $response->status());

        } catch (RequestException $e) {
            $response = $e->response;
            $responseBody = $response?->json();
            $message = null;

            if (is_array($responseBody)) {
                $message = $responseBody['message']
                    ?? ($responseBody['error']['message'] ?? null);
            }

            $message = $message ?: $response?->body() ?: $e->getMessage();

            Log::error('Bansal API PR Point Calculator Lists Request Error', [
                'method' => 'getPrPointCalcLists',
                'status' => $response?->status(),
                'body' => $response?->body(),
                'error' => $message
            ]);

            return response()->json([
                'success' => false,
                'message' => $message ?: 'Failed to fetch PR Point Calculator lists',
                'error' => 'API request failed'
            ], $response?->status() ?: 500);

        } catch (Exception $e) {
            Log::error('Bansal API PR Point Calculator Lists Error', [
                'method' => 'getPrPointCalcLists',
                'error_type' => get_class($e),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while fetching PR Point Calculator lists',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate PR Points Result
     * POST /api/pr-point-calc-result
     */
    public function calculatePrPointsResult(Request $request)
    {
        try {
            $config = $this->getBansalApiConfig();
            $baseUrl = $config['baseUrl'];
            $apiToken = $config['apiToken'];
            $timeout = $config['timeout'];

            if (empty($apiToken)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bansal API token not configured. Set BANSAL_API_TOKEN in .env'
                ], 500);
            }

            // Get request body data
            $requestData = $request->all();

            // Make API call to Bansal API
            $response = Http::timeout($timeout)
                ->withToken($apiToken)
                ->acceptJson()
                ->post("{$baseUrl}/pr-point-calc-result", $requestData);

            if ($response->failed()) {
                Log::error('Bansal API Calculate PR Points Result Error', [
                    'method' => 'calculatePrPointsResult',
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'request_data' => $requestData
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to calculate PR Points result from external API',
                    'error' => $response->status() === 404 ? 'PR Points calculation not found' : 'API request failed'
                ], $response->status());
            }

            $data = $response->json();

            // Return the response as-is from the external API
            return response()->json($data, $response->status());

        } catch (RequestException $e) {
            $response = $e->response;
            $responseBody = $response?->json();
            $message = null;

            if (is_array($responseBody)) {
                $message = $responseBody['message']
                    ?? ($responseBody['error']['message'] ?? null);
            }

            $message = $message ?: $response?->body() ?: $e->getMessage();

            Log::error('Bansal API Calculate PR Points Result Request Error', [
                'method' => 'calculatePrPointsResult',
                'status' => $response?->status(),
                'body' => $response?->body(),
                'error' => $message,
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => $message ?: 'Failed to calculate PR Points result',
                'error' => 'API request failed'
            ], $response?->status() ?: 500);

        } catch (Exception $e) {
            Log::error('Bansal API Calculate PR Points Result Error', [
                'method' => 'calculatePrPointsResult',
                'error_type' => get_class($e),
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while calculating PR Points result',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Student Calculator Lists
     * GET /api/student-calc-lists
     */
    public function getStudentCalcLists(Request $request)
    {
        try {
            $config = $this->getBansalApiConfig();
            $baseUrl = $config['baseUrl'];
            $apiToken = $config['apiToken'];
            $timeout = $config['timeout'];

            if (empty($apiToken)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bansal API token not configured. Set BANSAL_API_TOKEN in .env'
                ], 500);
            }

            // Make API call to Bansal API
            $response = Http::timeout($timeout)
                ->withToken($apiToken)
                ->acceptJson()
                ->get("{$baseUrl}/student-calc-lists");

            if ($response->failed()) {
                Log::error('Bansal API Student Calculator Lists Error', [
                    'method' => 'getStudentCalcLists',
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch Student Calculator lists from external API',
                    'error' => $response->status() === 404 ? 'Student Calculator lists not found' : 'API request failed'
                ], $response->status());
            }

            $data = $response->json();

            // Return the response as-is from the external API
            return response()->json($data, $response->status());

        } catch (RequestException $e) {
            $response = $e->response;
            $responseBody = $response?->json();
            $message = null;

            if (is_array($responseBody)) {
                $message = $responseBody['message']
                    ?? ($responseBody['error']['message'] ?? null);
            }

            $message = $message ?: $response?->body() ?: $e->getMessage();

            Log::error('Bansal API Student Calculator Lists Request Error', [
                'method' => 'getStudentCalcLists',
                'status' => $response?->status(),
                'body' => $response?->body(),
                'error' => $message
            ]);

            return response()->json([
                'success' => false,
                'message' => $message ?: 'Failed to fetch Student Calculator lists',
                'error' => 'API request failed'
            ], $response?->status() ?: 500);

        } catch (Exception $e) {
            Log::error('Bansal API Student Calculator Lists Error', [
                'method' => 'getStudentCalcLists',
                'error_type' => get_class($e),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while fetching Student Calculator lists',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate Student Financial Requirements
     * POST /api/student-calc-result
     */
    public function calculateStudentFinancialRequirements(Request $request)
    {
        try {
            $config = $this->getBansalApiConfig();
            $baseUrl = $config['baseUrl'];
            $apiToken = $config['apiToken'];
            $timeout = $config['timeout'];

            if (empty($apiToken)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bansal API token not configured. Set BANSAL_API_TOKEN in .env'
                ], 500);
            }

            // Get request body data
            $requestData = $request->all();

            // Make API call to Bansal API
            $response = Http::timeout($timeout)
                ->withToken($apiToken)
                ->acceptJson()
                ->post("{$baseUrl}/student-calc-result", $requestData);

            if ($response->failed()) {
                Log::error('Bansal API Calculate Student Financial Requirements Error', [
                    'method' => 'calculateStudentFinancialRequirements',
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'request_data' => $requestData
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to calculate Student Financial Requirements from external API',
                    'error' => $response->status() === 404 ? 'Student Financial Requirements calculation not found' : 'API request failed'
                ], $response->status());
            }

            $data = $response->json();

            // Return the response as-is from the external API
            return response()->json($data, $response->status());

        } catch (RequestException $e) {
            $response = $e->response;
            $responseBody = $response?->json();
            $message = null;

            if (is_array($responseBody)) {
                $message = $responseBody['message']
                    ?? ($responseBody['error']['message'] ?? null);
            }

            $message = $message ?: $response?->body() ?: $e->getMessage();

            Log::error('Bansal API Calculate Student Financial Requirements Request Error', [
                'method' => 'calculateStudentFinancialRequirements',
                'status' => $response?->status(),
                'body' => $response?->body(),
                'error' => $message,
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => $message ?: 'Failed to calculate Student Financial Requirements',
                'error' => 'API request failed'
            ], $response?->status() ?: 500);

        } catch (Exception $e) {
            Log::error('Bansal API Calculate Student Financial Requirements Error', [
                'method' => 'calculateStudentFinancialRequirements',
                'error_type' => get_class($e),
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while calculating Student Financial Requirements',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search Occupation (Occupation Finder)
     * GET /api/occupation-finder
     * 
     * Query Parameters:
     * - q: Search query - occupation name or ANZSCO code (required)
     * - limit: Number of results (optional, default: 20)
     * 
     * Response includes:
     * - anzsco_code: ANZSCO occupation code
     * - occupation_title: Title of the occupation
     * - skill_level: Skill level (1-5)
     * - assessing_authority: Skills assessing authority (e.g., ACS, VETASSESS)
     * - assessment_validity_years: Validity period of skill assessment
     * - occupation_lists: Array of occupation lists (e.g., MLTSSL, CSOL)
     * - alternate_titles: Alternative job titles
     * - additional_info: Additional information about the occupation
     */
    public function searchOccupation(Request $request)
    {
        try {
            $config = $this->getBansalApiConfig();
            $baseUrl = $config['baseUrl'];
            $apiToken = $config['apiToken'];
            $timeout = $config['timeout'];

            if (empty($apiToken)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bansal API token not configured. Set BANSAL_API_TOKEN in .env'
                ], 500);
            }

            // Get query parameters from request
            $searchQuery = $request->get('q');
            $limit = $request->get('limit', 20);

            // Validate search query
            if (empty($searchQuery)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Search query (q) is required'
                ], 400);
            }

            // Build query parameters array
            $queryParams = [
                'q' => $searchQuery,
                'limit' => $limit
            ];

            // Make API call to Bansal API
            $response = Http::timeout($timeout)
                ->withToken($apiToken)
                ->acceptJson()
                ->get("{$baseUrl}/occupation-finder", $queryParams);

            if ($response->failed()) {
                Log::error('Bansal API Search Occupation Error', [
                    'method' => 'searchOccupation',
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'query_params' => $queryParams
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to search occupations from external API',
                    'error' => $response->status() === 404 ? 'Occupation finder not found' : 'API request failed'
                ], $response->status());
            }

            $data = $response->json();

            // Return the response as-is from the external API
            return response()->json($data, $response->status());

        } catch (RequestException $e) {
            $response = $e->response;
            $responseBody = $response?->json();
            $message = null;

            if (is_array($responseBody)) {
                $message = $responseBody['message']
                    ?? ($responseBody['error']['message'] ?? null);
            }

            $message = $message ?: $response?->body() ?: $e->getMessage();

            Log::error('Bansal API Search Occupation Request Error', [
                'method' => 'searchOccupation',
                'status' => $response?->status(),
                'body' => $response?->body(),
                'error' => $message,
                'query_params' => [
                    'q' => $request->get('q'),
                    'limit' => $request->get('limit', 20)
                ]
            ]);

            return response()->json([
                'success' => false,
                'message' => $message ?: 'Failed to search occupations',
                'error' => 'API request failed'
            ], $response?->status() ?: 500);

        } catch (Exception $e) {
            Log::error('Bansal API Search Occupation Error', [
                'method' => 'searchOccupation',
                'error_type' => get_class($e),
                'error' => $e->getMessage(),
                'query_params' => [
                    'q' => $request->get('q'),
                    'limit' => $request->get('limit', 20)
                ]
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while searching occupations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search Matching Postcodes
     * GET /api/postcode-search
     * 
     * Query Parameters:
     * - q: Search query - postcode number (e.g., "3000") (required)
     * - limit: Number of results (optional, default: 20)
     * 
     * Returns a list of matching postcodes with suburb information
     */
    public function searchPostcode(Request $request)
    {
        try {
            $config = $this->getBansalApiConfig();
            $baseUrl = $config['baseUrl'];
            $apiToken = $config['apiToken'];
            $timeout = $config['timeout'];

            if (empty($apiToken)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bansal API token not configured. Set BANSAL_API_TOKEN in .env'
                ], 500);
            }

            // Get query parameters from request
            $searchQuery = $request->get('q');
            $limit = $request->get('limit', 20);

            // Validate search query
            if (empty($searchQuery)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Search query (q) is required'
                ], 400);
            }

            // Build query parameters array
            $queryParams = [
                'q' => $searchQuery
            ];

            // Add limit if provided
            if ($limit) {
                $queryParams['limit'] = $limit;
            }

            // Make API call to Bansal API
            $response = Http::timeout($timeout)
                ->withToken($apiToken)
                ->acceptJson()
                ->get("{$baseUrl}/postcode-search", $queryParams);

            if ($response->failed()) {
                Log::error('Bansal API Search Postcode Error', [
                    'method' => 'searchPostcode',
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'query_params' => $queryParams
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to search postcodes from external API',
                    'error' => $response->status() === 404 ? 'Postcode search not found' : 'API request failed'
                ], $response->status());
            }

            $data = $response->json();

            // Return the response as-is from the external API
            return response()->json($data, $response->status());

        } catch (RequestException $e) {
            $response = $e->response;
            $responseBody = $response?->json();
            $message = null;

            if (is_array($responseBody)) {
                $message = $responseBody['message']
                    ?? ($responseBody['error']['message'] ?? null);
            }

            $message = $message ?: $response?->body() ?: $e->getMessage();

            Log::error('Bansal API Search Postcode Request Error', [
                'method' => 'searchPostcode',
                'status' => $response?->status(),
                'body' => $response?->body(),
                'error' => $message,
                'query_params' => [
                    'q' => $request->get('q'),
                    'limit' => $request->get('limit', 20)
                ]
            ]);

            return response()->json([
                'success' => false,
                'message' => $message ?: 'Failed to search postcodes',
                'error' => 'API request failed'
            ], $response?->status() ?: 500);

        } catch (Exception $e) {
            Log::error('Bansal API Search Postcode Error', [
                'method' => 'searchPostcode',
                'error_type' => get_class($e),
                'error' => $e->getMessage(),
                'query_params' => [
                    'q' => $request->get('q'),
                    'limit' => $request->get('limit', 20)
                ]
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while searching postcodes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Postcode Result
     * GET /api/postcode-result
     * 
     * Query Parameters:
     * - postcode: Postcode number (required, integer e.g., 3002)
     * - suburb: Suburb name (optional - if provided, filters result for specific suburb)
     * 
     * Returns detailed information for the specified postcode/suburb
     */
    public function getPostcodeResult(Request $request)
    {
        try {
            $config = $this->getBansalApiConfig();
            $baseUrl = $config['baseUrl'];
            $apiToken = $config['apiToken'];
            $timeout = $config['timeout'];

            if (empty($apiToken)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bansal API token not configured. Set BANSAL_API_TOKEN in .env'
                ], 500);
            }

            // Get query parameters from request
            $postcode = $request->get('postcode');
            $suburb = $request->get('suburb');

            // Validate postcode
            if (empty($postcode)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Postcode is required'
                ], 400);
            }

            // Build query parameters array
            $queryParams = [
                'postcode' => $postcode
            ];

            // Add suburb if provided
            if ($suburb !== null && $suburb !== '') {
                $queryParams['suburb'] = $suburb;
            }

            // Make API call to Bansal API
            $response = Http::timeout($timeout)
                ->withToken($apiToken)
                ->acceptJson()
                ->get("{$baseUrl}/postcode-result", $queryParams);

            if ($response->failed()) {
                Log::error('Bansal API Get Postcode Result Error', [
                    'method' => 'getPostcodeResult',
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'query_params' => $queryParams
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to get postcode result from external API',
                    'error' => $response->status() === 404 ? 'Postcode not found' : 'API request failed'
                ], $response->status());
            }

            $data = $response->json();

            // Return the response as-is from the external API
            return response()->json($data, $response->status());

        } catch (RequestException $e) {
            $response = $e->response;
            $responseBody = $response?->json();
            $message = null;

            if (is_array($responseBody)) {
                $message = $responseBody['message']
                    ?? ($responseBody['error']['message'] ?? null);
            }

            $message = $message ?: $response?->body() ?: $e->getMessage();

            Log::error('Bansal API Get Postcode Result Request Error', [
                'method' => 'getPostcodeResult',
                'status' => $response?->status(),
                'body' => $response?->body(),
                'error' => $message,
                'query_params' => [
                    'postcode' => $request->get('postcode'),
                    'suburb' => $request->get('suburb')
                ]
            ]);

            return response()->json([
                'success' => false,
                'message' => $message ?: 'Failed to get postcode result',
                'error' => 'API request failed'
            ], $response?->status() ?: 500);

        } catch (Exception $e) {
            Log::error('Bansal API Get Postcode Result Error', [
                'method' => 'getPostcodeResult',
                'error_type' => get_class($e),
                'error' => $e->getMessage(),
                'query_params' => [
                    'postcode' => $request->get('postcode'),
                    'suburb' => $request->get('suburb')
                ]
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while getting postcode result',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

