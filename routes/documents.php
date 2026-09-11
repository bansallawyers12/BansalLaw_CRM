<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CRM\DocumentController as AdminDocumentController;
use App\Http\Controllers\PublicDocumentController;
use App\Http\Controllers\CRM\SignatureDashboardController;

/*
|--------------------------------------------------------------------------
| Document Signature Routes
|--------------------------------------------------------------------------
|
| WORKFLOW:
| 1. Admin prepares document for signing (CRUD operations)
| 2. Admin sends signing link via email to client
| 3. Client receives email with link: /sign/{id}/{token}
| 4. Client signs document (no login - token validated)
| 5. Client sees thank you page & downloads signed document
| 6. Admin views completed document in admin panel
|
| ROUTE ORGANIZATION:
| - Staff (auth:admin) registered first so /documents/create and similar
|   are not swallowed by the public /documents/{id?} stub.
| - Public token routes keep stable /documents/* and /sign/* URIs for email links.
| - Staff download/reminder URIs that would collide with public paths use
|   the /crm/documents/* prefix (DOC-2).
|
*/

/*
|--------------------------------------------------------------------------
| ADMIN DOCUMENT MANAGEMENT ROUTES
|--------------------------------------------------------------------------
| Middleware: auth:admin
| Route Names: documents.* and signatures.*
*/

Route::middleware('auth:admin')->group(function () {

/*---------- Document CRUD Operations ----------*/
Route::get('/documents/create', [AdminDocumentController::class, 'create'])
    ->name('documents.create');

Route::post('/documents', [AdminDocumentController::class, 'store'])
    ->name('documents.store');

Route::get('/documents/{id}/edit', [AdminDocumentController::class, 'edit'])
    ->whereNumber('id')
    ->name('documents.edit');

Route::patch('/documents/{id}', [AdminDocumentController::class, 'update'])
    ->whereNumber('id')
    ->name('documents.update');

Route::get('/documents/{id}/signature-placement-data', [AdminDocumentController::class, 'getSignaturePlacementData'])
    ->whereNumber('id')
    ->name('documents.signature-placement-data');

// Staff PDF page preview for signature placement (replaces /debug-pdf-page — DOC-1)
Route::get('/documents/{id}/preview-page/{page}', [AdminDocumentController::class, 'getPage'])
    ->whereNumber(['id', 'page'])
    ->name('documents.preview.page');

/*---------- Admin Signing & Reminder Operations ----------*/
Route::post('/crm/documents/{document}/send-reminder', [AdminDocumentController::class, 'sendReminder'])
    ->name('documents.sendReminder');

Route::post('/documents/{document}/send-signing-link', [AdminDocumentController::class, 'sendSigningLink'])
    ->name('documents.sendSigningLink');

Route::get('/documents/{document}/sign', [AdminDocumentController::class, 'showSignForm'])
    ->name('documents.showSignForm');

/*---------- Admin Document Viewing & Download ----------*/
Route::get('/documents/{id}/preview-signed', [AdminDocumentController::class, 'previewSigned'])
    ->whereNumber('id')
    ->name('documents.preview.signed');

Route::get('/crm/documents/{id}/download-signed', [AdminDocumentController::class, 'downloadSigned'])
    ->whereNumber('id')
    ->name('documents.download.signed');

Route::get('/crm/documents/{id}/download-signed-and-thankyou', [AdminDocumentController::class, 'downloadSignedAndThankyou'])
    ->whereNumber('id')
    ->name('documents.download_and_thankyou');

/*---------- Signature Dashboard Routes ----------*/
Route::prefix('signatures')->group(function () {
    Route::get('/', [SignatureDashboardController::class, 'index'])->name('signatures.index');
    Route::get('/create', [SignatureDashboardController::class, 'create'])->name('signatures.create');
    Route::post('/', [SignatureDashboardController::class, 'store'])->name('signatures.store');
    Route::post('/suggest-association', [SignatureDashboardController::class, 'suggestAssociation'])->name('signatures.suggest-association');
    Route::post('/preview-email', [SignatureDashboardController::class, 'previewEmail'])->name('signatures.preview-email');

    // Bulk actions
    Route::post('/bulk-archive', [SignatureDashboardController::class, 'bulkArchive'])->name('signatures.bulk-archive');
    Route::post('/bulk-void', [SignatureDashboardController::class, 'bulkVoid'])->name('signatures.bulk-void');
    Route::post('/bulk-resend', [SignatureDashboardController::class, 'bulkResend'])->name('signatures.bulk-resend');

    Route::get('/{id}', [SignatureDashboardController::class, 'show'])->name('signatures.show');
    Route::get('/{id}/certificate', [SignatureDashboardController::class, 'downloadCertificate'])->name('signatures.certificate');
    Route::post('/{id}/reminder', [SignatureDashboardController::class, 'sendReminder'])->name('signatures.reminder');
    Route::post('/{id}/cancel', [SignatureDashboardController::class, 'cancelSignature'])->name('signatures.cancel');
    Route::post('/{id}/send', [SignatureDashboardController::class, 'sendForSignature'])->name('signatures.send');
    Route::get('/{id}/copy-link', [SignatureDashboardController::class, 'copyLink'])->name('signatures.copy-link');

    // Association management
    Route::post('/{id}/associate', [SignatureDashboardController::class, 'associate'])->name('signatures.associate');
    Route::get('/api/client-matters/{clientId}', [SignatureDashboardController::class, 'getClientMatters'])->name('signatures.client-matters');
    Route::post('/{id}/detach', [SignatureDashboardController::class, 'detach'])->name('signatures.detach');
});

/*---------- Client Matters API ----------*/
Route::get('/clients/{id}/matters', [SignatureDashboardController::class, 'getClientMatters'])->name('clients.matters');

}); // End of admin routes group

/*
|--------------------------------------------------------------------------
| PUBLIC DOCUMENT SIGNING ROUTES
|--------------------------------------------------------------------------
| No authentication required - access controlled by token validation
| Route Names: public.documents.*
|
| These routes allow clients to sign documents via email links without
| requiring login. Security is handled through unique tokens sent via email.
*/

/*---------- Public Signing Interface ----------*/
Route::get('/sign/{id}/{token}', [PublicDocumentController::class, 'sign'])
    ->name('public.documents.sign');

Route::post('/documents/{document}/sign', [PublicDocumentController::class, 'submitSignatures'])
    ->name('public.documents.submitSignatures');

/*---------- Public Document Viewing ----------*/
Route::get('/documents/{id}/page/{page}', [PublicDocumentController::class, 'getPage'])
    ->whereNumber(['id', 'page'])
    ->name('public.documents.page');

/*---------- Public Download & Thank You ----------*/
Route::get('/documents/{id}/download-signed', [PublicDocumentController::class, 'downloadSigned'])
    ->whereNumber('id')
    ->name('public.documents.download.signed');

Route::get('/documents/{id}/download-signed-and-thankyou', [PublicDocumentController::class, 'downloadSignedAndThankyou'])
    ->whereNumber('id')
    ->name('public.documents.download_and_thankyou');

Route::get('/documents/thankyou/{id?}', [PublicDocumentController::class, 'thankyou'])
    ->name('public.documents.thankyou');

/*---------- Public Reminder ----------*/
Route::post('/documents/{document}/send-reminder', [PublicDocumentController::class, 'sendReminder'])
    ->name('public.documents.sendReminder');

// No public GET /documents index — signing is token-only via /sign/{id}/{token} (DOC-3).
