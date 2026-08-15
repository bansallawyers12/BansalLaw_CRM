<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CRM\ClientMatterHubController;

/*
| CRM matter utilities: logs, notes, mail, ownership, documents.
*/

Route::get('/crm/matter/logs', [ClientMatterHubController::class, 'getMatterLogs']);
Route::get('/crm/matter/list', [ClientMatterHubController::class, 'getapplications']);

Route::post('/crm/matter/discontinue', [ClientMatterHubController::class, 'discontinueMatter']);
Route::post('/crm/matter/revert', [ClientMatterHubController::class, 'revertMatter']);

Route::post('/create-app-note', [ClientMatterHubController::class, 'addNote']);
Route::get('/crm/matter/notes', [ClientMatterHubController::class, 'getMatterNotes']);
Route::post('/crm/matter/sendmail', [ClientMatterHubController::class, 'sendMatterMail']);

Route::get('/crm/matter/updateintake', [ClientMatterHubController::class, 'updateintake']);
Route::get('/crm/matter/updatedates', [ClientMatterHubController::class, 'updatedates']);
Route::get('/crm/matter/updateexpectwin', [ClientMatterHubController::class, 'updateexpectwin']);

Route::post('/crm/matter/ownership', [ClientMatterHubController::class, 'application_ownership']);

Route::get('/crm/matter/document-categories-for-move', [ClientMatterHubController::class, 'getDocumentCategoriesForMove']);
