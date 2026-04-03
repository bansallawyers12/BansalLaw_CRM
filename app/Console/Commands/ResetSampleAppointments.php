<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BookingAppointment;
use App\Models\Admin;
use Illuminate\Support\Facades\DB;

class ResetSampleAppointments extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'booking:reset-samples {--keep-clients : Keep the test clients}';

    /**
     * The console command description.
     */
    protected $description = 'Reset sample booking appointments (clear and reseed test data)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!$this->confirm('This will delete all booking appointments. Continue?', true)) {
            $this->info('Operation cancelled.');
            return self::SUCCESS;
        }

        $this->info('Clearing existing booking appointments...');
        
        $deletedCount = BookingAppointment::count();
        BookingAppointment::truncate();
        
        $this->info("✓ Deleted {$deletedCount} appointments");

        if (!$this->option('keep-clients')) {
            $this->info('Deleting test clients...');
            $testClients = Admin::whereIn('type', ['client', 'lead'])
                ->where('source', 'Bansal Website (Test Data)')
                ->get();
            
            $clientCount = $testClients->count();
            foreach ($testClients as $client) {
                $client->delete();
            }
            
            $this->info("✓ Deleted {$clientCount} test clients");
        }

        $this->newLine();
        $this->info('Creating fresh sample data...');
        
        $this->call('db:seed', [
            '--class' => 'Database\\Seeders\\SampleBookingAppointmentsSeeder'
        ]);

        $this->newLine();
        $this->info('✅ Sample appointments reset successfully!');
        $this->info('You can now test the booking system with fresh data.');

        return self::SUCCESS;
    }
}

