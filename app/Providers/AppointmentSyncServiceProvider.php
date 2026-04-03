<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Services\BansalAppointmentSync\BansalApiClient;
use App\Services\BansalAppointmentSync\AppointmentSyncService;
use App\Services\BansalAppointmentSync\ClientMatchingService;
use App\Services\BansalAppointmentSync\ConsultantAssignmentService;
use App\Services\BansalAppointmentSync\NotificationService;

class AppointmentSyncServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register BansalApiClient as singleton with configuration
        $this->app->singleton(BansalApiClient::class, function ($app) {
            return new BansalApiClient(
                config('services.bansal_api.url'),
                config('services.bansal_api.token'),
                config('services.bansal_api.timeout', 30)
            );
        });

        // Register helper services as singletons
        $this->app->singleton(ClientMatchingService::class);
        $this->app->singleton(ConsultantAssignmentService::class);
        $this->app->singleton(NotificationService::class);
        
        // Register main sync service with dependencies injected
        $this->app->singleton(AppointmentSyncService::class, function ($app) {
            return new AppointmentSyncService(
                $app->make(BansalApiClient::class),
                $app->make(ClientMatchingService::class),
                $app->make(ConsultantAssignmentService::class)
            );
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register authorization gates for sync management
        $this->registerPolicies();
    }

    /**
     * Register authorization policies
     */
    protected function registerPolicies(): void
    {
        // Only admin (role=1) or super admin (role=12) can manage sync
        Gate::define('manage-booking-sync', function ($user) {
            return in_array($user->role, [1, 12]);
        });

        // Staff members can view synced appointments
        Gate::define('view-booking-appointments', function ($user) {
            return in_array($user->role, [1, 2, 3, 4, 5, 6, 12]); // Most staff roles
        });

        // Only admin and super admin can manually trigger sync
        Gate::define('trigger-manual-sync', function ($user) {
            return in_array($user->role, [1, 12]);
        });
    }
}

