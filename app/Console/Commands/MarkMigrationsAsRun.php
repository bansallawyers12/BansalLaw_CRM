<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class MarkMigrationsAsRun extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:mark-as-run 
                            {migrations?* : Specific migration files to mark as run (without .php extension)}
                            {--all : Mark all pending migrations as run}
                            {--path= : Path to migration files directory}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark migrations as run in the migrations table without actually executing them';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $migrationsPath = $this->option('path') ?: database_path('migrations');
        
        // Get existing migrations from database
        $existingMigrations = DB::table('migrations')
            ->pluck('migration')
            ->toArray();
        
        // Get the highest batch number
        $maxBatch = DB::table('migrations')->max('batch') ?? 0;
        $nextBatch = $maxBatch + 1;
        
        $migrationsToMark = [];
        
        if ($this->option('all')) {
            // Get all migration files
            $migrationFiles = File::glob($migrationsPath . '/*.php');
            
            foreach ($migrationFiles as $file) {
                $migrationName = File::name($file);
                
                // Skip if already in database
                if (!in_array($migrationName, $existingMigrations)) {
                    $migrationsToMark[] = $migrationName;
                }
            }
        } elseif ($this->argument('migrations')) {
            // Use provided migration names
            $migrationsToMark = $this->argument('migrations');
            
            // Filter out already existing migrations
            $migrationsToMark = array_filter($migrationsToMark, function($migration) use ($existingMigrations) {
                return !in_array($migration, $existingMigrations);
            });
        } else {
            $this->error('Please provide migration names or use --all flag');
            $this->info('Usage examples:');
            $this->info('  php artisan migrate:mark-as-run --all');
            $this->info('  php artisan migrate:mark-as-run 2025_10_04_202019_create_email_verifications_table');
            $this->info('  php artisan migrate:mark-as-run migration1 migration2 migration3');
            return 1;
        }
        
        if (empty($migrationsToMark)) {
            $this->info('No migrations to mark. All specified migrations are already recorded.');
            return 0;
        }
        
        $this->info("Found " . count($migrationsToMark) . " migration(s) to mark as run.");
        $this->info("They will be added to batch: $nextBatch");
        
        if (!$this->confirm('Do you want to continue?', true)) {
            $this->info('Cancelled.');
            return 0;
        }
        
        $this->newLine();
        $this->info('Marking migrations as run...');
        
        $bar = $this->output->createProgressBar(count($migrationsToMark));
        $bar->start();
        
        foreach ($migrationsToMark as $migration) {
            DB::table('migrations')->insert([
                'migration' => $migration,
                'batch' => $nextBatch
            ]);
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine(2);
        
        $this->info('✓ Successfully marked ' . count($migrationsToMark) . ' migration(s) as run in batch ' . $nextBatch);
        
        return 0;
    }
}
