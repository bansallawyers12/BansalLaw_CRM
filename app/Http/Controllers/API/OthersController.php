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
            'baseUrl' => rtrim(config('services.bansal_api.url'), '/'),
            'apiToken' => config('services.bansal_api.token'),
            'timeout' => config('services.bansal_api.timeout', 30)
        ];
    }

    /**
     * Resolved outbound URL for blog detail: {base}/{prefix}/{id}.
     */
    private function resolveExternalBlogDetailUrl(string $baseUrl, int|string $id): string
    {
        $template = config('services.bansal_api.blog_detail_url');
        if (is_string($template) && trim($template) !== '') {
            $out = str_replace('{id}', (string) $id, trim($template));

            return rtrim($this->normalizeOutboundHttpUrl($out), '/');
        }

        $prefix = trim((string) config('services.bansal_api.blog_detail_path_prefix', 'blogs/detail'), '/');

        return rtrim($baseUrl, '/') . '/' . $prefix . '/' . $id;
    }

    private function normalizeOutboundHttpUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }

        return 'https://' . $url;
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
                    'message' => 'Bansal API token not configured. Set APPOINTMENT_API_BEARER_TOKEN or BANSAL_API_TOKEN in the environment.'
                ], 500);
            }

            // Validate ID
            if (empty($id) || !is_numeric($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid blog ID provided'
                ], 400);
            }

            $detailUrl = $this->resolveExternalBlogDetailUrl($baseUrl, $id);

            // Make API call to Bansal API
            $response = Http::timeout($timeout)
                ->withToken($apiToken)
                ->acceptJson()
                ->get($detailUrl);

            if ($response->failed()) {
                Log::error('Bansal API Blog Detail Error', [
                    'method' => 'getBlogDetail',
                    'url' => $detailUrl,
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
                'url' => $this->resolveExternalBlogDetailUrl(rtrim(config('services.bansal_api.url'), '/'), $id),
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
                    'message' => 'Bansal API token not configured. Set APPOINTMENT_API_BEARER_TOKEN or BANSAL_API_TOKEN in the environment.'
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
                    'message' => 'Bansal API token not configured. Set APPOINTMENT_API_BEARER_TOKEN or BANSAL_API_TOKEN in the environment.'
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

