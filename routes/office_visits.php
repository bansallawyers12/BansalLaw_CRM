<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CRM\OfficeVisitController;

Route::get('/office-visits', fn () => redirect()->route('officevisits.waiting'))->name('officevisits.index');
Route::get('/office-visits/waiting', [OfficeVisitController::class, 'waiting'])->name('officevisits.waiting');
Route::get('/office-visits/attending', [OfficeVisitController::class, 'attending'])->name('officevisits.attending');
Route::get('/office-visits/completed', [OfficeVisitController::class, 'completed'])->name('officevisits.completed');
Route::get('/office-visits/create', [OfficeVisitController::class, 'create'])->name('officevisits.create');

Route::post('/checkin', [OfficeVisitController::class, 'checkin']);
Route::get('/get-checkin-detail', [OfficeVisitController::class, 'getcheckin']);
Route::post('/update_visit_purpose', [OfficeVisitController::class, 'update_visit_purpose']);
Route::post('/update_visit_comment', [OfficeVisitController::class, 'update_visit_comment']);
Route::post('/attend_session', [OfficeVisitController::class, 'attend_session']);
Route::post('/complete_session', [OfficeVisitController::class, 'complete_session']);
Route::get('/office-visits/change_assignee', [OfficeVisitController::class, 'change_assignee']);
