<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Broadcast;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Register broadcasting routes with web middleware and admin auth
        Broadcast::routes(['middleware' => ['web', 'auth:admin']]);
        
        require base_path('routes/channels.php');
    }
}
