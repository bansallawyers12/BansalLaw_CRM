<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * This namespace is applied to your controller routes.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    protected $namespace = 'App\Http\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        // Add support for old Laravel 5.x route syntax
        Route::macro('oldStyle', function ($uri, $action) {
            if (is_string($action) && strpos($action, '@') !== false) {
                list($controller, $method) = explode('@', $action);
                if (strpos($controller, '\\') === 0) {
                    $controller = 'App\\Http\\Controllers' . $controller;
                } elseif (strpos($controller, '\\') === false) {
                    $controller = 'App\\Http\\Controllers\\' . $controller;
                }
                $action = [$controller, $method];
            }
            return Route::any($uri, $action);
        });

        // Custom route model binding for Admin model to handle encoded IDs
        Route::bind('admin', function ($value) {
            try {
                // Decode the encoded client ID
                $decodedId = convert_uudecode(base64_decode($value));
                return \App\Models\Admin::findOrFail($decodedId);
            } catch (\Exception $e) {
                // If decoding fails, try to find by the original value
                return \App\Models\Admin::findOrFail($value);
            }
        });

        // Custom route model binding for 'client' parameter (same as 'admin' since they use the same model)
        Route::bind('client', function ($value) {
            try {
                // Decode the encoded client ID
                $decodedId = convert_uudecode(base64_decode($value));
                return \App\Models\Admin::findOrFail($decodedId);
            } catch (\Exception $e) {
                // If decoding fails, try to find by the original value
                return \App\Models\Admin::findOrFail($value);
            }
        });

        parent::boot();
    }

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map()
    {
        $this->mapApiRoutes();

        $this->mapWebRoutes();

        $this->mapSmsRoutes();

        //
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    protected function mapWebRoutes()
    {
        Route::middleware('web')
             ->namespace($this->namespace)
             ->group(base_path('routes/web.php'));
    }



    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapApiRoutes()
    {
        Route::prefix('api')
             ->middleware('api')
             ->namespace($this->namespace)
             ->group(base_path('routes/api.php'));
    }

    /**
     * Define the "sms" routes for the application.
     *
     * These routes handle all SMS-related functionality.
     *
     * @return void
     */
    protected function mapSmsRoutes()
    {
        Route::middleware('web')
             ->namespace($this->namespace)
             ->group(base_path('routes/sms.php'));
    }
}
