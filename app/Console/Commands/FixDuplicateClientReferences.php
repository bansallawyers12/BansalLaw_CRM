<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\ClientReferenceService;
use App\Models\Admin;

class FixDuplicateClientReferences extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clients:fix-duplicate-references 
                            {--dry-run : Run in dry-run mode without making changes}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix duplicate client_id references (only exact duplicates like GURP0000 & GURP0000). Keeps OLDEST record unchanged, fixes NEWER ones.';

    protected $referenceService;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->referenceService = app(ClientReferenceService::class);
        $dryRun = $this->option('dry-run');
        
        $this->info('==========================================================');
        $this->info('  CLIENT REFERENCE DUPLICATE FIX SCRIPT');
        $this->info('==========================================================');
        $this->info('  Strategy: Keep OLDEST reference, fix NEWER duplicates');
        $this->info('  Target: Only exact duplicate references (e.g., GURP0000 & GURP0000)');
        $this->info('==========================================================');
        $this->newLine();

        // Step 1: Find all duplicates
        $this->info('📊 Step 1: Analyzing client references...');
        $duplicates = $this->findDuplicateReferences();
        
        if ($duplicates->isEmpty()) {
            $this->info('✓ No duplicate client references found!');
            return 0;
        }

        // Display summary
        $totalDuplicates = 0;
        $affectedClients = [];
        
        foreach ($duplicates as $duplicate) {
            $totalDuplicates += $duplicate->count;
            $affectedClients[] = $duplicate->client_id;
        }

        $this->newLine();
        $this->warn("⚠ Found {$duplicates->count()} client_id values with duplicates");
        $this->warn("⚠ Total {$totalDuplicates} client records affected");
        $this->newLine();

        // Show duplicate details
        $this->info('Duplicate References:');
        $this->table(
            ['Client ID', 'Count', 'IDs'],
            $duplicates->map(function($dup) {
                return [
                    $dup->client_id,
                    $dup->count,
                    $dup->ids
                ];
            })
        );

        $this->newLine();

        // Step 2: Confirmation
        if (!$dryRun && !$this->option('force')) {
            if (!$this->confirm('Do you want to proceed with fixing these duplicates?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        // Step 3: Fix duplicates
        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
            $this->simulateFix($duplicates);
        } else {
            $this->info('🔧 Step 2: Fixing duplicate references...');
            $this->newLine();
            $this->fixDuplicates($duplicates);
        }

        $this->newLine();
        $this->info('==========================================================');
        $this->info('  OPERATION COMPLETED');
        $this->info('==========================================================');

        return 0;
    }

    /**
     * Find all duplicate client_id references
     */
    protected function findDuplicateReferences()
    {
        return DB::table('admins')
            ->select('client_id', DB::raw('COUNT(*) as count'), DB::raw('STRING_AGG(id::text, \', \' ORDER BY id) as ids'))
            ->whereIn('type', ['client', 'lead'])
            ->whereNotNull('client_id')
            ->whereNull('is_deleted')
            ->groupBy('client_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderByRaw('COUNT(*) DESC')
            ->get();
    }

    /**
     * Simulate the fix without making changes
     */
    protected function simulateFix($duplicates)
    {
        foreach ($duplicates as $duplicate) {
            $clientIds = explode(',', $duplicate->ids);
            
            $this->info("Processing duplicate: {$duplicate->client_id}");
            
            // Get all clients with this duplicate reference, ordered by created_at (oldest first)
            $clients = Admin::whereIn('id', $clientIds)
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->get();
            
            // Keep the OLDEST one unchanged
            $oldestClient = $clients->first();
            $this->line("  → Client ID {$oldestClient->id}: {$oldestClient->first_name} {$oldestClient->last_name}");
            $this->line("     Created: {$oldestClient->created_at}");
            $this->line("     KEEP: {$duplicate->client_id} (OLDEST - unchanged)");
            $this->newLine();
            
            // Generate new references for the NEWER ones
            foreach ($clients->skip(1) as $client) {
                // Simulate new reference generation
                $newPrefix = $this->generatePrefix($client->first_name);
                $this->line("  → Client ID {$client->id}: {$client->first_name} {$client->last_name}");
                $this->line("     Created: {$client->created_at}");
                $this->line("     WOULD CHANGE: {$client->client_id} → {$newPrefix}25[NEW_COUNTER]");
            }
            
            $this->newLine();
        }
    }

    /**
     * Fix all duplicate references
     */
    protected function fixDuplicates($duplicates)
    {
        $totalFixed = 0;
        $totalKept = 0;
        $errors = [];

        $progressBar = $this->output->createProgressBar($duplicates->count());
        $progressBar->start();

        foreach ($duplicates as $duplicate) {
            $clientIds = explode(',', $duplicate->ids);
            
            // Get all clients with this duplicate reference, ordered by created_at (oldest first)
            $clients = Admin::whereIn('id', $clientIds)
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->get();
            
            // Keep the OLDEST one unchanged
            $oldestClient = $clients->first();
            $totalKept++;
            
            // Fix the NEWER ones
            foreach ($clients->skip(1) as $client) {
                try {
                    DB::transaction(function () use ($client, &$totalFixed) {
                        // Lock this specific client record
                        $lockedClient = Admin::lockForUpdate()->find($client->id);
                        
                        if (!$lockedClient) {
                            throw new \Exception("Client ID {$client->id} not found");
                        }
                        
                        // Generate new unique reference
                        $reference = $this->referenceService->generateClientReference($lockedClient->first_name);
                        
                        $oldReference = $lockedClient->client_id;
                        
                        // Update the client
                        $lockedClient->client_id = $reference['client_id'];
                        $lockedClient->client_counter = $reference['client_counter'];
                        $lockedClient->save();
                        
                        // Log the change in activities_logs
                        DB::table('activities_logs')->insert([
                            'created_by' => 1, // System
                            'client_id' => $lockedClient->id,
                            'subject' => 'Client Reference Fixed',
                            'description' => "Duplicate reference fixed: {$oldReference} → {$reference['client_id']} (older reference kept on client ID: {$lockedClient->id})",
                            'activity_type' => 'activity',
                            'task_status' => 0,
                            'pin' => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        
                        $totalFixed++;
                    });
                } catch (\Exception $e) {
                    $errors[] = "Client ID {$client->id}: " . $e->getMessage();
                }
            }
            
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display results
        $this->info("✓ Successfully fixed {$totalFixed} duplicate references");
        $this->info("✓ Kept {$totalKept} original (oldest) references unchanged");
        
        if (!empty($errors)) {
            $this->newLine();
            $this->error("⚠ " . count($errors) . " errors occurred:");
            foreach ($errors as $error) {
                $this->error("  - {$error}");
            }
        }

        // Show verification
        $this->newLine();
        $this->info('🔍 Verifying fix...');
        $remainingDuplicates = $this->findDuplicateReferences();
        
        if ($remainingDuplicates->isEmpty()) {
            $this->info('✓ All duplicates fixed successfully!');
        } else {
            $this->warn("⚠ {$remainingDuplicates->count()} duplicates still remain");
            $this->table(
                ['Client ID', 'Count'],
                $remainingDuplicates->map(function($dup) {
                    return [$dup->client_id, $dup->count];
                })
            );
        }
    }

    /**
     * Generate prefix from first name (matches ClientReferenceService logic)
     */
    protected function generatePrefix(string $firstName): string
    {
        $cleaned = preg_replace('/[^A-Za-z]/', '', $firstName);
        $prefix = strlen($cleaned) >= 4 
            ? substr($cleaned, 0, 4) 
            : $cleaned;
        return strtoupper($prefix);
    }
}

