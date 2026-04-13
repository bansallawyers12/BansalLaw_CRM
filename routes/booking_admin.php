<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CRM\BookingAppointmentsController;
use App\Http\Controllers\HomeController;

Route::post('/getdatetimebackend', [HomeController::class, 'getdatetimebackend'])->name('getdatetimebackend');
Route::post('/getdisableddatetime', [HomeController::class, 'getdisableddatetime'])->name('getdisableddatetime');

Route::controller(BookingAppointmentsController::class)
    ->prefix('booking')
    ->name('booking.')
    ->group(function () {
        Route::get('/appointments', 'index')->name('appointments.index');
        Route::get('/appointments/{id}/edit', 'edit')
            ->name('appointments.edit')
            ->whereNumber('id');
        Route::put('/appointments/{id}', 'update')
            ->name('appointments.update')
            ->whereNumber('id');

        Route::get('/appointments/{id}', 'show')
            ->name('appointments.show')
            ->whereNumber('id');

        Route::get('/appointments/{id}/json', 'getAppointmentJson')
            ->name('appointments.json')
            ->whereNumber('id');

        Route::redirect('/calendar/paid', '/booking/calendar/ajay', 301);
        Route::redirect('/calendar/jrp', '/booking/calendar/ajay', 301);
        Route::redirect('/calendar/education', '/booking/calendar/ajay', 301);
        Route::redirect('/calendar/tourist', '/booking/calendar/ajay', 301);
        Route::redirect('/calendar/adelaide', '/booking/calendar/ajay', 301);
        Route::get('/calendar/{type}', 'calendar')
            ->name('appointments.calendar')
            ->whereIn('type', ['ajay', 'kunal']);

        Route::post('/appointments/{id}/update-status', 'updateStatus')
            ->name('appointments.update-status')
            ->whereNumber('id');

        Route::post('/appointments/{id}/update-consultant', 'updateConsultant')
            ->name('appointments.update-consultant')
            ->whereNumber('id');

        Route::post('/appointments/{id}/update-meeting-type', 'updateMeetingType')
            ->name('appointments.update-meeting-type')
            ->whereNumber('id');

        Route::post('/appointments/{id}/update-datetime', 'update')
            ->name('appointments.update-datetime')
            ->whereNumber('id');

        Route::post('/appointments/{id}/add-note', 'addNote')
            ->name('appointments.add-note')
            ->whereNumber('id');

        Route::post('/appointments/{id}/send-reminder', 'sendReminder')
            ->name('appointments.send-reminder')
            ->whereNumber('id');

        Route::post('/appointments/bulk-update-status', 'bulkUpdateStatus')
            ->name('appointments.bulk-update-status');

        Route::get('/appointments/export', 'export')
            ->name('appointments.export');

        Route::get('/sync/dashboard', 'syncDashboard')
            ->name('sync.dashboard');

        Route::get('/sync/stats', 'syncStats')
            ->name('sync.stats');

        Route::post('/sync/manual', 'manualSync')
            ->name('sync.manual')
            ->middleware('can:trigger-manual-sync');

        Route::get('/api/appointments', 'getAppointments')
            ->name('api.appointments');
    });
