<?php

/*
|--------------------------------------------------------------------------
| Health-check Routes
|--------------------------------------------------------------------------
|
| These routes are registered with NO middleware (see RouteServiceProvider::
| mapHealthRoutes). That means no session, no Redis, no cookie encryption,
| no CSRF — so /up is always reachable regardless of infrastructure state.
|
| Used by:
|   - AWS ALB target-group health checks (polls /up every few seconds)
|   - CodeDeploy ValidateService hook  (scripts/validate.sh)
|
| Maintenance-mode passthrough is handled at the global middleware level via
| AppServiceProvider: PreventRequestsDuringMaintenance::except(['up'])
|
*/

use Illuminate\Support\Facades\Route;

Route::get('/up', function () {
    return response('OK', 200)->header('Content-Type', 'text/plain; charset=UTF-8');
})->name('health.up');
