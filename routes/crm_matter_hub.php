<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CRM\ClientMatterHubController;

/*
| CRM matter utilities: logs, notes, mail, documents.
*/

Route::get('/crm/matter/logs', [ClientMatterHubController::class, 'getMatterLogs'])
    ->name('crm.matter.logs');
Route::get('/crm/matter/list', [ClientMatterHubController::class, 'listClientMatters'])
    ->name('crm.matter.list');

// Legacy aliases — thin adapters onto preferred /clients/matter/discontinue|reopen.
Route::post('/crm/matter/discontinue', [ClientMatterHubController::class, 'discontinueMatter']);
Route::post('/crm/matter/revert', [ClientMatterHubController::class, 'revertMatter']);

Route::post('/create-app-note', [ClientMatterHubController::class, 'addNote']);
Route::get('/crm/matter/notes', [ClientMatterHubController::class, 'getMatterNotes']);
Route::post('/crm/matter/sendmail', [ClientMatterHubController::class, 'sendMatterMail']);

Route::get('/crm/matter/document-categories-for-move', [ClientMatterHubController::class, 'getDocumentCategoriesForMove']);
