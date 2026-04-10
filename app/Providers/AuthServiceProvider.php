<?php

namespace App\Providers;

use App\Models\Staff;
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
            $super = $user instanceof Staff && $user->hasEffectiveSuperAdminPrivileges();

            return $super
                   || $user->id === $client->admin_id
                   || $user->id === $client->id;
        });

        Gate::define('update', function ($user, $client) {
            $super = $user instanceof Staff && $user->hasEffectiveSuperAdminPrivileges();

            return $super
                   || $user->id === $client->admin_id;
        });
    }
}
