<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Admin;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class UpdateClientAges extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clients:update-ages {--chunk=1000} {--smart}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update client ages based on date of birth (bi-weekly scheduled job)';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $startTime = microtime(true);
        $this->info('Starting client age update job...');
        $this->info('Time: ' . Carbon::now()->format('Y-m-d H:i:s'));

        $chunkSize = (int) $this->option('chunk');
        $smartUpdate = $this->option('smart');

        // Get all clients with valid DOB
        $query = Admin::whereIn('type', ['client', 'lead'])
            ->whereNotNull('dob')
            ->where('dob', '<=', Carbon::now()->format('Y-m-d')) // Not future dates
            ->whereNull('is_deleted')
            ->where('is_archived', 0);

        $totalClients = $query->count();
        $this->info("Found {$totalClients} clients with valid DOB");

        if ($totalClients === 0) {
            $this->info('No clients to update.');
            return 0;
        }

        $updatedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        // Process in chunks to avoid memory issues
        $query->chunk($chunkSize, function ($clients) use (&$updatedCount, &$skippedCount, &$errorCount, $smartUpdate) {
            foreach ($clients as $client) {
                try {
                    $newAge = $this->calculateAge($client->dob);
                    
                    if (empty($newAge)) {
                        $skippedCount++;
                        continue;
                    }

                    // Smart update: only update if age actually changed
                    if ($smartUpdate && $client->age === $newAge) {
                        $skippedCount++;
                        continue;
                    }

                    // Update the age
                    $client->age = $newAge;
                    $client->save();

                    $updatedCount++;

                    // Log significant changes (optional, can be verbose)
                    if ($client->age !== $newAge) {
                        Log::info("Age updated for client {$client->id} ({$client->client_id}): {$client->age} → {$newAge}");
                    }

                } catch (\Exception $e) {
                    $errorCount++;
                    Log::error("Failed to update age for client {$client->id}: " . $e->getMessage());
                    $this->error("Error updating client {$client->id}: " . $e->getMessage());
                }
            }
        });

        $executionTime = round(microtime(true) - $startTime, 2);

        // Summary
        $this->info('');
        $this->info('=== Age Update Summary ===');
        $this->info("Total clients processed: {$totalClients}");
        $this->info("Updated: {$updatedCount}");
        $this->info("Skipped: {$skippedCount}");
        $this->info("Errors: {$errorCount}");
        $this->info("Execution time: {$executionTime} seconds");
        $this->info('Job completed at: ' . Carbon::now()->format('Y-m-d H:i:s'));

        // Log summary
        Log::info("Age update job completed", [
            'total' => $totalClients,
            'updated' => $updatedCount,
            'skipped' => $skippedCount,
            'errors' => $errorCount,
            'execution_time' => $executionTime
        ]);

        return 0;
    }

    /**
     * Calculate age from date of birth
     * Returns format: "X years Y months"
     *
     * @param string $dob Date of birth in Y-m-d format
     * @return string|null
     */
    protected function calculateAge($dob)
    {
        if (empty($dob)) {
            return null;
        }

        try {
            $dobDate = Carbon::parse($dob);
            $now = Carbon::now();
            
            // Check for invalid dates
            if ($dobDate->isFuture()) {
                return null;
            }

            $diff = $now->diff($dobDate);
            
            return $diff->y . ' years ' . $diff->m . ' months';
        } catch (\Exception $e) {
            Log::error("Error calculating age for DOB {$dob}: " . $e->getMessage());
            return null;
        }
    }
}

