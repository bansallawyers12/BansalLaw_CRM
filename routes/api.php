<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\ServiceAccountController;
use App\Http\Controllers\API\PublicListingController;
use App\Http\Controllers\API\PublicBookingController;
use App\Http\Controllers\API\LeadBookingApiController;

/*
|--------------------------------------------------------------------------
| API Routes (public booking + service-account tokens; client/staff mobile auth removed)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/payments/create-payment-intent', function (Request $request) {
        $user = $request->user();

        // 1. Verify authenticated user is active
        if (! $user || (isset($user->status) && (int) $user->status !== 1)) {
            return response()->json([
                'message' => 'Unauthorized or user account is inactive.',
            ], 403);
        }

        // 2. Verify authorization to initiate payments
        $adminRoles = config('crm.admin_console_role_ids', [1, 12, 17]);
        $isAuthorized = false;
        if ($user instanceof \App\Models\Staff) {
            $isAuthorized = $user->canAccessAdminConsole()
                || $user->hasEffectiveSuperAdminPrivileges()
                || in_array((int) $user->role, $adminRoles, true)
                || $user->hasCrmModule('payment')
                || $user->hasCrmModule('booking');
        } elseif (isset($user->role) && in_array((int) $user->role, [1, 12, 17], true)) {
            $isAuthorized = true;
        }

        if (method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
            $token = $user->currentAccessToken();
            if (! $token->can('*') && ! $token->can('payments:create')) {
                $isAuthorized = false;
            }
        }

        if (! $isAuthorized) {
            return response()->json([
                'message' => 'Forbidden: insufficient privileges to create payment intent.',
            ], 403);
        }

        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:50'],
            'currency' => ['sometimes', 'string', 'size:3', 'in:aud,usd,nzd,gbp,eur,AUD,USD,NZD,GBP,EUR'],
            'customer' => ['sometimes', 'string'],
            'description' => ['sometimes', 'string', 'max:255'],
            'metadata' => ['sometimes', 'array'],
            'receipt_email' => ['sometimes', 'email'],
            'automatic_payment_methods.enabled' => ['sometimes', 'boolean'],
        ]);

        try {
            $stripeSecret = config('services.stripe.secret');

            if (! $stripeSecret) {
                return response()->json([
                    'message' => 'Stripe secret key is not configured.',
                ], 500);
            }

            $stripe = new \Stripe\StripeClient($stripeSecret);

            $payload = [
                'amount' => $validated['amount'],
                'currency' => strtolower($validated['currency'] ?? 'aud'),
                'automatic_payment_methods' => [
                    'enabled' => data_get($validated, 'automatic_payment_methods.enabled', true),
                ],
            ];

            if (isset($validated['customer'])) {
                $payload['customer'] = $validated['customer'];
            }

            if (isset($validated['description'])) {
                $payload['description'] = $validated['description'];
            }

            if (isset($validated['metadata'])) {
                $payload['metadata'] = $validated['metadata'];
            }

            if (isset($validated['receipt_email'])) {
                $payload['receipt_email'] = $validated['receipt_email'];
            }

            $paymentIntent = $stripe->paymentIntents->create($payload);

            Log::info('Stripe PaymentIntent created', [
                'staff_id' => $user->id,
                'amount' => $validated['amount'],
                'currency' => $payload['currency'],
                'payment_intent_id' => $paymentIntent->id,
            ]);

            return response()->json([
                'id' => $paymentIntent->id,
                'status' => $paymentIntent->status,
                'client_secret' => $paymentIntent->client_secret,
                'amount' => $paymentIntent->amount,
                'currency' => $paymentIntent->currency,
            ], 201);
        } catch (\Stripe\Exception\ApiErrorException $exception) {
            Log::error('Stripe PaymentIntent creation failed', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Unable to create payment intent.',
                'error' => $exception->getMessage(),
            ], 400);
        } catch (\Throwable $exception) {
            Log::error('Unexpected error creating PaymentIntent', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'An unexpected error occurred.',
            ], 500);
        }
    })->middleware('throttle:6,1');
});

Route::get('/countries', [PublicListingController::class, 'getCountries']);

// Public create-lead (website / other sources) — unchanged.
Route::post('/leads', [LeadBookingApiController::class, 'storeLead'])->middleware('throttle:5,1');

// Migration CRM only — separate path + token required + higher rate limit.
Route::post('/migration-crm/leads', [LeadBookingApiController::class, 'storeLead'])
    ->middleware(['migration.crm.token', 'throttle:migration-crm-leads']);

// Public booking & appointment creation / payments: protected by booking shared secret (when set) + dedicated throttles
Route::middleware(['booking.api.access', 'throttle:10,1'])->group(function () {
    Route::post('/booking-appointments', [LeadBookingApiController::class, 'storeBookingAppointment']);
    Route::post('/appointments/add-appointment-without-login', [PublicBookingController::class, 'addAppointmentWithoutLogin']);
    Route::post('/appointments/record-payment-without-login', [PublicBookingController::class, 'recordAppointmentPaymentWithoutLogin']);
    Route::post('/appointments/record-payment-without-login-wallet', [PublicBookingController::class, 'recordAppointmentPaymentWithoutLoginWallet']);
});

// Calendar & slot availability queries: protected by dedicated throttle
Route::middleware('throttle:30,1')->group(function () {
    Route::get('/appointment-variable-lists', [PublicBookingController::class, 'getAppointmentVariableLists']);
    Route::post('/appointments/get-disabled-dates', [PublicBookingController::class, 'getDisabledDateFromCalendar']);
    Route::post('/appointments/get-disabled-slots', [PublicBookingController::class, 'getDisabledSlotsOfAnyDateFromCalendar']);
    Route::post('/appointments/get-booked-disabled-time-slots', [PublicBookingController::class, 'getBookedTimeSlotsToDisable']);
});

Route::post('/service-account/generate-token', [ServiceAccountController::class, 'generateToken'])
    ->middleware('throttle:5,1');

Route::post('/service-account/authenticate', [ServiceAccountController::class, 'authenticate'])
    ->middleware('throttle:15,1');

