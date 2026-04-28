<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Helpers\SortableHelper;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Keep /up available during "php artisan down" so load balancer (ALB/ELB) health checks pass during CodeDeploy.
        PreventRequestsDuringMaintenance::except(['up']);

        Schema::defaultStringLength(191);
        Paginator::useBootstrap();

        // Register sortable link directive
        Blade::directive('sortablelink', function ($expression) {
            return "<?php echo App\\Helpers\\SortableHelper::linkWithIcon($expression); ?>";
        });
        
        // Slow query logger — gated behind an explicit env flag so it never
        // fires in production accidentally. APP_DEBUG=true alone does NOT
        // enable this; set LOG_SLOW_QUERIES=true in .env to opt in.
        if (env('LOG_SLOW_QUERIES', false)) {
            $slowQueryThreshold = (int) env('SLOW_QUERY_THRESHOLD', 1000);
            DB::listen(function ($query) use ($slowQueryThreshold) {
                if ($query->time > $slowQueryThreshold) {
                    Log::channel('daily')->warning('Slow Query Detected', [
                        'sql'      => $query->sql,
                        'bindings' => $query->bindings,
                        'time'     => $query->time . 'ms',
                        'location' => $this->getQueryLocation(),
                    ]);
                }
            });
        }
        
        // TIER 1 OPTIMIZATION: Log all queries in local environment (optional)
        if (env('LOG_ALL_QUERIES', false) && app()->environment('local')) {
            DB::listen(function ($query) {
                Log::channel('daily')->debug('Query Executed', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time' => $query->time . 'ms',
                ]);
            });
        }
    }

    /**
     * Walk the call stack and return the first application frame that is
     * neither a vendor file nor this service provider itself.
     */
    protected function getQueryLocation(): string
    {
        $self  = __FILE__;
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20);

        foreach ($trace as $item) {
            if (
                isset($item['file'])
                && $item['file'] !== $self
                && !str_contains($item['file'], DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)
            ) {
                return $item['file'] . ':' . ($item['line'] ?? '?');
            }
        }

        return 'Unknown location';
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
