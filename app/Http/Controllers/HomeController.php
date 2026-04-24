<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\RequestException;

// use App\Models\WebsiteSetting; // removed website settings dependency
// Website content models removed - tables dropped in migration 2025_12_23_180714
// Models deleted: Slider, OurService, Testimonial, HomeContent
// Tables dropped: sliders, our_services, testimonials, home_contents
use App\Mail\CommonMail;

use Illuminate\Support\Facades\Session;
use Cookie;

use Mail;
use Swift_SmtpTransport;
use Swift_Mailer;
use Helper;

use Stripe;

use App\Support\BansalDatetimeBackendHelper;
use App\Services\Booking\BookedTimeSlotsToDisableService;


class HomeController extends Controller
{

	public function __construct(Request $request)
    {
        // Share safe defaults instead of WebsiteSetting
        $siteData = (object) [
            'phone' => env('APP_PHONE', ''),
            'ofc_timing' => env('APP_OFFICE_TIMING', ''),
            'email' => env('APP_EMAIL', ''),
            'logo' => env('APP_LOGO', 'logo.png'),
        ];
        \View::share('siteData', $siteData);
	}


	public function sicaptcha(Request $request)
    {
		 $code=$request->code;

		$im = imagecreatetruecolor(50, 24);
		$bg = imagecolorallocate($im, 37, 37, 37); //background color blue
		$fg = imagecolorallocate($im, 255, 241, 70);//text color white
		imagefill($im, 0, 0, $bg);
		imagestring($im, 5, 5, 5,  $code, $fg);
		header("Cache-Control: no-cache, must-revalidate");
		header('Content-type: image/png');
		imagepng($im);
		imagedestroy($im);

    }

	public static function hextorgb ($hexstring){
		$integar = hexdec($hexstring);
					return array( "red" => 0xFF & ($integar >> 0x10),
		"green" => 0xFF & ($integar >> 0x8),
		"blue" => 0xFF & $integar
		);
	}




	public function refresh_captcha() {
		$vals = array(
			'img_path' => public_path().'/captcha/',
			'img_url' => asset('public/captcha'),
			'expiration' => 7200,
			'word_lenght' => 6,
			'font_size' => 15,
			'img_width'	=> '110',
			'img_height' => '40',
			'colors'	=> array('background' => array(255,175,2),'border' => array(255,175,2),	'text' => array(255,255,255),	'grid' => array(255,255,255))
		);

		$cap = $this->create_captcha($vals);
		$captcha = $cap['image'];
		session()->put('captchaWord', $cap['word']);
		echo $cap['image'];
	}

	


    /**
     * Get date/time backend settings (office hours, duration, disabled days)
     * Returns appointment configuration for calendar initialization
     * Uses external CRM API (services.bansal_api).
     */
    public function getdatetimebackend(Request $request)
    {
        // Get new input parameters
        $id = $request->id; // 1=>consultation, 2=>paid-consultation, 3=>overseas-enquiry; promo_free|paid (client modal)
        $enquiry_item = $request->enquiry_item; // 1=>permanent-residency, 2=>temporary-residency, etc.
        $inperson_address = $request->inperson_address; // 1=>Adelaide, 2=>melbourne
        $slot_overwrite = $request->slot_overwrite ?? 0; // 0 or 1
        
        Log::info('getdatetimebackend called', [
            'id' => $id,
            'enquiry_item' => $enquiry_item,
            'inperson_address' => $inperson_address,
            'slot_overwrite' => $slot_overwrite
        ]);
        
        // Map id to specific_service (numeric legacy + client modal slugs)
        $specific_service_map = [
            1 => 'consultation',
            2 => 'paid-consultation',
            3 => 'overseas-enquiry',
            'promo_free' => 'consultation',
            'paid' => 'paid-consultation',
        ];
        $specific_service = $specific_service_map[$id] ?? 'consultation';
        
        // Map enquiry_item to service_type
        $service_type_map = [
            1 => 'permanent-residency',
            2 => 'temporary-residency',
            3 => 'jrp-skill-assessment',
            4 => 'tourist-visa',
            5 => 'education-visa',
            6 => 'complex-matters',
            7 => 'visa-cancellation',
            8 => 'international-migration'
        ];
        $service_type = $service_type_map[$enquiry_item] ?? 'permanent-residency';
        
        // Map inperson_address to location
        $location_map = [
            1 => 'adelaide',
            2 => 'melbourne'
        ];
        $location = $location_map[$inperson_address] ?? 'adelaide';
        
        // Prepare request data for external API
        $requestData = [
            'specific_service' => $specific_service,
            'service_type' => $service_type,
            'location' => $location,
            'slot_overwrite' => $slot_overwrite
        ];
        
        try {
            $baseUrl = rtrim(config('services.bansal_api.url'), '/');
            $apiToken = config('services.bansal_api.token');
            $timeout = config('services.bansal_api.timeout', 30);

            if (empty($apiToken)) {
                Log::warning('Bansal API token not configured for getdatetimebackend');
                if (BansalDatetimeBackendHelper::fallbackEnabled()) {
                    return response()->json(BansalDatetimeBackendHelper::defaultPayload());
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Bansal API token not configured. Set APPOINTMENT_API_BEARER_TOKEN or BANSAL_API_TOKEN in the environment.',
                ], 503);
            }

            $response = Http::timeout($timeout)
                ->withToken($apiToken)
                ->acceptJson()
                ->post("{$baseUrl}/appointments/get-datetime-backend", $requestData);

            if ($response->failed()) {
                Log::error('Bansal API get-datetime-backend Error', [
                    'method' => 'getdatetimebackend',
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'request_data' => $requestData,
                ]);

                if (BansalDatetimeBackendHelper::fallbackEnabled()) {
                    Log::warning('getdatetimebackend: using default hours (API HTTP error)');

                    return response()->json(BansalDatetimeBackendHelper::defaultPayload());
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch datetime backend from external API',
                    'error' => $response->status() === 404 ? 'Endpoint not found' : 'API request failed',
                ], 502);
            }

            $payload = $response->json();
            if (! is_array($payload)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid datetime backend response',
                ], 502);
            }

            Log::info('getdatetimebackend Bansal API raw response', [
                'http_status' => $response->status(),
                'bansal_response' => $payload,
            ]);

            $toClient = BansalDatetimeBackendHelper::withTimeslotLabelsFromConfig($payload);

            Log::info('getdatetimebackend response to client', [
                'response' => $toClient,
            ]);

            return response()->json($toClient);
        } catch (RequestException $e) {
            $response = $e->response;
            $responseBody = $response?->json();
            $message = null;

            if (is_array($responseBody)) {
                $message = $responseBody['message']
                    ?? ($responseBody['error']['message'] ?? null);
            }

            $message = $message ?: $response?->body() ?: $e->getMessage();

            Log::error('Bansal API get-datetime-backend Request Error', [
                'method' => 'getdatetimebackend',
                'message' => $message,
                'request_data' => $requestData,
                'exception' => $e->getMessage(),
            ]);

            if (BansalDatetimeBackendHelper::fallbackEnabled()) {
                Log::warning('getdatetimebackend: using default hours (RequestException)');

                return response()->json(BansalDatetimeBackendHelper::defaultPayload());
            }

            return response()->json([
                'success' => false,
                'message' => 'API request failed: '.$message,
            ], 502);
        } catch (\Exception $e) {
            Log::error('Bansal API get-datetime-backend Exception', [
                'method' => 'getdatetimebackend',
                'message' => $e->getMessage(),
                'request_data' => $requestData,
                'trace' => $e->getTraceAsString(),
            ]);

            if (BansalDatetimeBackendHelper::fallbackEnabled()) {
                Log::warning('getdatetimebackend: using default hours (exception)');

                return response()->json(BansalDatetimeBackendHelper::defaultPayload());
            }

            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Disabled time-slot labels for a selected date.
     *
     * Merges (union, de-duplicated):
     * - Bansal: POST to {@see config('services.bansal_api.disabled_datetime_url')}
     *   (default: https://www.bansallawyers.com.au/api/getdisableddatetimenewapi) via
     *   {@see \App\Services\BansalAppointmentSync\BansalApiClient::getDisabledDateTime}
     * - CRM: {@see \App\Services\Booking\BookedTimeSlotsToDisableService}
     *   (same as POST /api/appointments/get-booked-disabled-time-slots).
     * When slot_overwrite is 1, returns an empty disabled list (Bansal convention; no merge).
     */
    public function getdisableddatetime(Request $request)
    {
        $service_id = $request->service_id;
        $enquiry_item = $request->enquiry_item;
        $inperson_address = $request->inperson_address;
        $sel_date = $request->sel_date;
        $slot_overwrite = (int) ($request->slot_overwrite ?? 0);

        Log::info('getdisableddatetime called', [
            'service_id' => $service_id,
            'enquiry_item' => $enquiry_item,
            'inperson_address' => $inperson_address,
            'sel_date' => $sel_date,
            'slot_overwrite' => $slot_overwrite,
        ]);

        if ($slot_overwrite === 1) {
            return response()->json([
                'success' => true,
                'disabledtimeslotes' => [],
            ]);
        }

        $specific_service_map = [
            1 => 'consultation',
            2 => 'paid-consultation',
            3 => 'overseas-enquiry',
            'promo_free' => 'consultation',
            'paid' => 'paid-consultation',
        ];
        $specific_service = $specific_service_map[$service_id] ?? 'consultation';

        $service_type_map = [
            1 => 'permanent-residency',
            2 => 'temporary-residency',
            3 => 'jrp-skill-assessment',
            4 => 'tourist-visa',
            5 => 'education-visa',
            6 => 'complex-matters',
            7 => 'visa-cancellation',
            8 => 'international-migration',
        ];
        $service_type = $service_type_map[$enquiry_item] ?? 'permanent-residency';

        $location_map = [
            1 => 'adelaide',
            2 => 'melbourne',
        ];
        $location = $location_map[$inperson_address] ?? 'adelaide';

        $bookedSlots = app(BookedTimeSlotsToDisableService::class);
        $dateForCrm = BookedTimeSlotsToDisableService::parseDateInput((string) $sel_date);
        $inpersonInt = in_array((int) $inperson_address, [1, 2], true) ? (int) $inperson_address : null;
        $crmSlotLabels = $dateForCrm
            ? $bookedSlots->getTimeSlotLabelsForDate($dateForCrm, $inpersonInt)
            : [];

        $apiClient = new \App\Services\BansalAppointmentSync\BansalApiClient();

        if (! $apiClient->isConfigured()) {
            if (BansalDatetimeBackendHelper::fallbackEnabled()) {
                Log::info('getdisableddatetime: Bansal token missing, returning CRM-only disabled slots (fallback)');

                return response()->json([
                    'success' => true,
                    'disabledtimeslotes' => $crmSlotLabels,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Bansal API token not configured. Set APPOINTMENT_API_BEARER_TOKEN or BANSAL_API_TOKEN in the environment.',
                'disabledtimeslotes' => $crmSlotLabels,
            ], 503);
        }

        try {
            $response = $apiClient->getDisabledDateTime(
                $specific_service,
                $service_type,
                $location,
                (string) $sel_date,
                $slot_overwrite
            );

            $bansalSlots = $response['disabledtimeslotes'] ?? [];
            if (! is_array($bansalSlots)) {
                $bansalSlots = [];
            }
            $response['disabledtimeslotes'] = $bookedSlots->mergeTimeSlotLabelLists($bansalSlots, $crmSlotLabels);

            Log::info('getdisableddatetime response', [
                'sel_date' => $sel_date,
                'bansal_disabledtimeslotes' => $bansalSlots,
                'crm_disabledtimeslotes' => $crmSlotLabels,
                'merged_disabledtimeslotes' => $response['disabledtimeslotes'],
                'bansal_response_keys' => array_keys($response),
            ]);

            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Bansal API get-disabled-datetime Exception', [
                'method' => 'getdisableddatetime',
                'message' => $e->getMessage(),
                'service_id' => $service_id,
                'enquiry_item' => $enquiry_item,
                'inperson_address' => $inperson_address,
                'sel_date' => $sel_date,
                'slot_overwrite' => $slot_overwrite,
                'trace' => $e->getTraceAsString(),
            ]);

            if (BansalDatetimeBackendHelper::fallbackEnabled()) {
                Log::warning('getdisableddatetime: Bansal error, returning CRM disabled slots (fallback)');

                return response()->json([
                    'success' => true,
                    'disabledtimeslotes' => $crmSlotLabels,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'An error occurred: '.$e->getMessage(),
                'disabledtimeslotes' => $crmSlotLabels,
            ], 500);
        }
    }



}

