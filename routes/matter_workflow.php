<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CRM\ClientMatterWorkflowController;

/*
| Matter workflow (stages, deadlines, discontinue/reopen/delete).
*/

Route::get('/client-portal/detail', [ClientMatterWorkflowController::class, 'getClientPortalDetail']);
Route::post('/client-portal/load-matter-upsert', [ClientMatterWorkflowController::class, 'loadMatterUpsert']);
Route::get('/updatestage', [ClientMatterWorkflowController::class, 'updatestage']);
Route::get('/completestage', [ClientMatterWorkflowController::class, 'completestage']);
Route::get('/updatebackstage', [ClientMatterWorkflowController::class, 'updatebackstage']);
Route::post('/clients/matter/update-next-stage', [ClientMatterWorkflowController::class, 'updateClientMatterNextStage'])->name('clients.matter.update-next-stage');
Route::post('/clients/matter/update-previous-stage', [ClientMatterWorkflowController::class, 'updateClientMatterPreviousStage'])->name('clients.matter.update-previous-stage');
Route::post('/clients/matter/discontinue', [ClientMatterWorkflowController::class, 'discontinueClientMatter'])->name('clients.matter.discontinue');
Route::post('/clients/matter/reopen', [ClientMatterWorkflowController::class, 'reopenClientMatter'])->name('clients.matter.reopen');
Route::post('/clients/matter/delete', [ClientMatterWorkflowController::class, 'deleteClientMatter'])->name('clients.matter.delete');
Route::post('/clients/matter/update-deadline', [ClientMatterWorkflowController::class, 'updateClientMatterDeadline'])->name('clients.matter.update-deadline');
Route::post('/clients/matter/change-workflow', [ClientMatterWorkflowController::class, 'changeClientMatterWorkflow'])->name('clients.matter.change-workflow');
