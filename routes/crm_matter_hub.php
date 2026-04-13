<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CRM\ClientMatterHubController;

/*
| CRM matter utilities: logs, notes, mail, ownership, documents (non-workflow surface).
*/

Route::get('/client-portal/logs', [ClientMatterHubController::class, 'getMatterLogs']);
Route::get('/client-portal/list', [ClientMatterHubController::class, 'getapplications']);

Route::post('/client-portal/discontinue', [ClientMatterHubController::class, 'discontinueMatter']);
Route::post('/client-portal/revert', [ClientMatterHubController::class, 'revertMatter']);

Route::post('/create-app-note', [ClientMatterHubController::class, 'addNote']);
Route::get('/client-portal/notes', [ClientMatterHubController::class, 'getMatterNotes']);
Route::post('/client-portal/sendmail', [ClientMatterHubController::class, 'clientPortalSendmail']);

Route::get('/client-portal/updateintake', [ClientMatterHubController::class, 'updateintake']);
Route::get('/client-portal/updatedates', [ClientMatterHubController::class, 'updatedates']);
Route::get('/client-portal/updateexpectwin', [ClientMatterHubController::class, 'updateexpectwin']);

Route::post('/client-portal/ownership', [ClientMatterHubController::class, 'application_ownership']);

Route::get('/crm/document-checklists-options', [ClientMatterHubController::class, 'getDocumentChecklistsOptions']);
Route::post('/add-checklists', [ClientMatterHubController::class, 'addChecklist'])->name('crm.matter.addChecklist');
Route::get('/client-portal/download-document', [ClientMatterHubController::class, 'downloadDocument']);
Route::get('/client-portal/document-categories-for-move', [ClientMatterHubController::class, 'getDocumentCategoriesForMove']);
