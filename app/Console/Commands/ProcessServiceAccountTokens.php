<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\GenerateServiceAccountToken;
use App\Models\Admin;
use App\Services\ServiceAccountTokenService;

class ProcessServiceAccountTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'service-account:generate-token {admin_id?} {--sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate service account token for admin(s)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $adminId = $this->argument('admin_id');
        $sync = $this->option('sync');

        if ($adminId) {
            // Generate token for specific admin
            $admin = Admin::find($adminId);
            if (!$admin) {
                $this->error("Admin with ID {$adminId} not found.");
                return 1;
            }

            if ($sync) {
                $this->generateTokenSync($admin);
            } else {
                $this->generateTokenAsync($admin);
            }
        } else {
            // Generate tokens for all active admins
            $admins = Admin::where('status', 1)->get();
            
            if ($admins->isEmpty()) {
                $this->info('No active admins found.');
                return 0;
            }

            $this->info("Found {$admins->count()} active admin(s).");

            foreach ($admins as $admin) {
                if ($sync) {
                    $this->generateTokenSync($admin);
                } else {
                    $this->generateTokenAsync($admin);
                }
            }
        }

        return 0;
    }

    /**
     * Generate token synchronously
     *
     * @param Admin $admin
     * @return void
     */
    private function generateTokenSync(Admin $admin)
    {
        $this->info("Generating token synchronously for admin: {$admin->email}");
        
        $service = new ServiceAccountTokenService();
        $result = $service->generateTokenSync($admin);
        
        if ($result) {
            $this->info("Token generated successfully for {$admin->email}");
            $this->line("Token: " . ($result['token'] ?? 'N/A'));
        } else {
            $this->error("Failed to generate token for {$admin->email}");
        }
    }

    /**
     * Generate token asynchronously
     *
     * @param Admin $admin
     * @return void
     */
    private function generateTokenAsync(Admin $admin)
    {
        $this->info("Dispatching token generation job for admin: {$admin->email}");
        
        $service = new ServiceAccountTokenService();
        $service->generateTokenInBackground($admin);
        
        $this->info("Job dispatched successfully for {$admin->email}");
    }
} 