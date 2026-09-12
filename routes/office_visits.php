<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CRM\OfficeVisitController;

/*
| Office visits (queue) vs front-desk check-in (wizard)
| - Front desk `/front-desk/checkin` creates CheckinLog rows.
| - This module manages waiting → attending → completed for those rows.
| - Legacy `/office-visits/create` redirects to the front-desk wizard.
*/

Route::get('/office-visits', fn () => redirect()->route('office-visits.waiting'))->name('office-visits.index');
Route::get('/office-visits/waiting', [OfficeVisitController::class, 'waiting'])->name('office-visits.waiting');
Route::get('/office-visits/attending', [OfficeVisitController::class, 'attending'])->name('office-visits.attending');
Route::get('/office-visits/completed', [OfficeVisitController::class, 'completed'])->name('office-visits.completed');
Route::redirect('/office-visits/create', '/front-desk/checkin', 301)->name('office-visits.create');

Route::post('/checkin', [OfficeVisitController::class, 'checkin']);
Route::get('/get-checkin-detail', [OfficeVisitController::class, 'getcheckin']);
Route::post('/update_visit_purpose', [OfficeVisitController::class, 'update_visit_purpose']);
Route::post('/update_visit_comment', [OfficeVisitController::class, 'update_visit_comment']);
Route::post('/attend_session', [OfficeVisitController::class, 'attend_session']);
Route::post('/complete_session', [OfficeVisitController::class, 'complete_session']);
Route::get('/office-visits/change_assignee', [OfficeVisitController::class, 'change_assignee']);
