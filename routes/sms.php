<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminConsole\Sms\SmsWebhookController;

/*
|--------------------------------------------------------------------------
| SMS Routes
|--------------------------------------------------------------------------
|
| SMS webhook routes for external providers
| Main SMS management is now in AdminConsole
|
*/

// ============================================================================
// WEBHOOK ROUTES (Public - No Authentication)
// ============================================================================
Route::prefix('webhooks/sms')->name('webhooks.sms.')->group(function () {
    
    // Twilio Webhooks
    Route::post('/twilio/status', [SmsWebhookController::class, 'twilioStatus'])->name('twilio.status');
    Route::post('/twilio/incoming', [SmsWebhookController::class, 'twilioIncoming'])->name('twilio.incoming');
    
    // Cellcast Webhooks
    Route::post('/cellcast/status', [SmsWebhookController::class, 'cellcastStatus'])->name('cellcast.status');
    Route::post('/cellcast/incoming', [SmsWebhookController::class, 'cellcastIncoming'])->name('cellcast.incoming');
});

