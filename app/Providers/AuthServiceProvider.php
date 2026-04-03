<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        \App\Models\Document::class => \App\Policies\DocumentPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Client authorization gates
        Gate::define('view', function ($user, $client) {
            // Admin can view if they are assigned to the client or have admin role
            return $user->role === 1 || // Super admin
                   $user->id === $client->admin_id || // Assigned admin
                   $user->id === $client->id; // The client themselves
        });

        Gate::define('update', function ($user, $client) {
            // Admin can update if they have update permissions
            return $user->role === 1 || // Super admin
                   $user->id === $client->admin_id; // Assigned admin
        });
    }
}
