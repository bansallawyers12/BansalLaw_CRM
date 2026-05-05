<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\ServiceAccountController;
use App\Http\Controllers\API\StaffApiAuthController;
use App\Http\Controllers\API\PublicListingController;
use App\Http\Controllers\API\PublicBookingController;
use App\Http\Controllers\API\LeadBookingApiController;

/*
|--------------------------------------------------------------------------
| API Routes (client mobile app removed; staff auth + public booking retained)
|--------------------------------------------------------------------------
*/

Route::post('/admin-login', [StaffApiAuthController::class, 'adminLogin']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [StaffApiAuthController::class, 'logout']);
    Route::post('/logout-all', [StaffApiAuthController::class, 'logoutAll']);
});

Route::get('/countries', [PublicListingController::class, 'getCountries']);

Route::post('/leads', [LeadBookingApiController::class, 'storeLead']);
Route::post('/booking-appointments', [LeadBookingApiController::class, 'storeBookingAppointment']);

Route::get('/appointment-variable-lists', [PublicBookingController::class, 'getAppointmentVariableLists']);

Route::post('/appointments/add-appointment-without-login', [PublicBookingController::class, 'addAppointmentWithoutLogin']);

Route::post('/appointments/get-disabled-dates', [PublicBookingController::class, 'getDisabledDateFromCalendar']);
Route::post('/appointments/get-disabled-slots', [PublicBookingController::class, 'getDisabledSlotsOfAnyDateFromCalendar']);
Route::post('/appointments/get-booked-disabled-time-slots', [PublicBookingController::class, 'getBookedTimeSlotsToDisable']);

Route::post('/appointments/record-payment-without-login', [PublicBookingController::class, 'recordAppointmentPaymentWithoutLogin']);
Route::post('/appointments/record-payment-without-login-wallet', [PublicBookingController::class, 'recordAppointmentPaymentWithoutLoginWallet']);

Route::post('/payments/create-payment-intent', function (Request $request) {
    $validated = $request->validate([
        'amount' => ['required', 'integer', 'min:50'],
        'currency' => ['sometimes', 'string', 'size:3'],
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
            'currency' => strtolower($validated['currency'] ?? 'usd'),
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
});

Route::post('/service-account/generate-token', [ServiceAccountController::class, 'generateToken']);
