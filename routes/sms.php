<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminConsole\Sms\SmsWebhookController;

/*
|--------------------------------------------------------------------------
| SMS Routes
|--------------------------------------------------------------------------
|
| SMS webhook routes for Cellcast. Main SMS management is in AdminConsole.
|
*/

// ============================================================================
// WEBHOOK ROUTES (Public - No Authentication)
// ============================================================================
Route::prefix('webhooks/sms')->name('webhooks.sms.')->group(function () {
    Route::post('/cellcast/status', [SmsWebhookController::class, 'cellcastStatus'])->name('cellcast.status');
    Route::post('/cellcast/incoming', [SmsWebhookController::class, 'cellcastIncoming'])->name('cellcast.incoming');
});
