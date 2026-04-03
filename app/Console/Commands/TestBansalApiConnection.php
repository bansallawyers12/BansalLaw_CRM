<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BansalAppointmentSync\BansalApiClient;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'booking:test-api')]
class TestBansalApiConnection extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'booking:test-api 
                            {--show-config : Display API configuration}';

    /**
     * The console command description.
     */
    protected $description = 'Test connection to Bansal Immigration API';

    /**
     * Execute the console command.
     */
    public function handle(BansalApiClient $apiClient): int
    {
        $this->info('Testing API connection...');
        $this->newLine();

        // Show configuration if requested
        if ($this->option('show-config')) {
            $this->info('API Configuration:');
            
            $token = config('services.bansal_api.token');
            $tokenDisplay = (!empty($token) && strlen($token) >= 4) 
                ? '***' . substr($token, -4) 
                : 'NOT SET';
            
            $this->table(
                ['Setting', 'Value'],
                [
                    ['URL', config('services.bansal_api.url')],
                    ['Token', $tokenDisplay],
                    ['Timeout', config('services.bansal_api.timeout', 30) . 's'],
                ]
            );
            $this->newLine();
        }

        try {
            // Test basic connection
            $this->info('→ Testing basic connection...');
            if ($apiClient->testConnection()) {
                $this->info('  ✓ API connection successful!');
            } else {
                $this->error('  ✗ API connection failed');
                return self::FAILURE;
            }

            // Try to fetch recent appointments
            $this->newLine();
            $this->info('→ Fetching recent appointments (last 10 minutes)...');
            $appointments = $apiClient->getRecentAppointments(10);
            
            $count = count($appointments);
            $this->info("  ✓ Fetched {$count} recent appointments");

            if ($count > 0) {
                $this->newLine();
                $this->info('Sample appointment data:');
                $sample = $appointments[0];
                $this->table(
                    ['Field', 'Value'],
                    [
                        ['ID', $sample['id'] ?? 'N/A'],
                        ['Client Name', $sample['full_name'] ?? 'N/A'],
                        ['Email', $sample['email'] ?? 'N/A'],
                        ['Date/Time', $sample['appointment_datetime'] ?? 'N/A'],
                        ['Location', $sample['location'] ?? 'N/A'],
                        ['Status', $sample['status'] ?? 'N/A'],
                    ]
                );
            }

            $this->newLine();
            $this->info('✓ All API tests passed!');
            
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->newLine();
            $this->error('✗ API test failed');
            $this->error('Error: ' . $e->getMessage());
            
            $this->newLine();
            $this->warn('Troubleshooting tips:');
            $this->line('  1. Check BANSAL_API_TOKEN is set in .env');
            $this->line('  2. Verify BANSAL_API_URL is correct');
            $this->line('  3. Ensure API token has not expired');
            $this->line('  4. Check network connectivity to API server');
            
            return self::FAILURE;
        }
    }
}

