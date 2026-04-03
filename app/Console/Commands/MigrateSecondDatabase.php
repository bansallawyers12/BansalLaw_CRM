<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateSecondDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:second-db {--force : Force the operation to run when in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run migrations for the second database connection';

    /**
     * The migrator instance.
     *
     * @var \Illuminate\Database\Migrations\Migrator
     */
    protected $migrator;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        
        $this->migrator = app('migrator');
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $connection = 'second_db';
        
        // Check if the connection exists
        if (!config("database.connections.{$connection}")) {
            $this->error("Database connection '{$connection}' not found in config/database.php");
            return 1;
        }

        // Check if we can connect to the database
        try {
            DB::connection($connection)->getPdo();
        } catch (\Exception $e) {
            $this->error("Cannot connect to the second database: " . $e->getMessage());
            $this->info("Please check your SECOND_DB_* environment variables in .env file");
            return 1;
        }

        // Ensure migrations table exists for second database
        $this->ensureMigrationsTableExists($connection);

        // Set the connection for the migrator
        $this->migrator->setConnection($connection);

        // Get all migration files
        $migrationFiles = $this->migrator->getMigrationFiles(database_path('migrations'));
        
        // Get ran migrations
        $ranMigrations = $this->migrator->getRepository()->getRan();
        
        // Get pending migrations
        $pendingMigrations = array_diff(array_keys($migrationFiles), $ranMigrations);
        
        if (empty($pendingMigrations)) {
            $this->info('No pending migrations for second database.');
            return 0;
        }

        $this->info('Found ' . count($pendingMigrations) . ' pending migration(s) for second database:');
        foreach ($pendingMigrations as $migration) {
            $this->line('  - ' . $migration);
        }

        if (!$this->option('force') && $this->laravel->environment() === 'production') {
            if (!$this->confirm('Are you sure you want to run migrations in production?')) {
                $this->info('Migration cancelled.');
                return 0;
            }
        }

        // Run the migrations
        $this->info('Running migrations for second database...');
        
        try {
            $this->migrator->run(database_path('migrations'), [
                'pretend' => false,
                'step' => false,
            ]);

            $this->info('Migrations completed successfully for second database!');
            return 0;
        } catch (\Exception $e) {
            $this->error('Migration failed: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Ensure the migrations table exists for the given connection.
     *
     * @param string $connection
     * @return void
     */
    protected function ensureMigrationsTableExists($connection)
    {
        $table = config('database.migrations', 'migrations');
        
        if (!Schema::connection($connection)->hasTable($table)) {
            $this->info("Creating migrations table for {$connection} connection...");
            
            Schema::connection($connection)->create($table, function ($table) {
                $table->increments('id');
                $table->string('migration');
                $table->integer('batch');
            });
            
            $this->info("Migrations table created for {$connection} connection.");
        }
    }
}
