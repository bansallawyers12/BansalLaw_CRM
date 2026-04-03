<?php

namespace App\Console\Commands;

use Illuminate\Database\Console\Migrations\BaseCommand;
use Illuminate\Database\Migrations\Migrator;

class MarkMigrationsAsRunCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:mark-as-run
                {--database= : The database connection to use}
                {--path=* : The path(s) to the migration files}
                {--realpath : Indicate any provided migration file paths are pre-resolved absolute paths}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark all pending migrations as already run (no schema changes, no data loss). Safe for production.';

    /**
     * The migrator instance.
     *
     * @var \Illuminate\Database\Migrations\Migrator
     */
    protected $migrator;

    public function __construct(Migrator $migrator)
    {
        parent::__construct();

        $this->migrator = $migrator;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $failed = false;
        $this->migrator->usingConnection($this->option('database'), function () use (&$failed) {
            if (! $this->migrator->repositoryExists()) {
                $this->error('Migration table not found. Run php artisan migrate:install first.');
                $failed = true;
                return;
            }

            $paths = $this->getMigrationPaths();
            $files = $this->migrator->getMigrationFiles($paths);
            $ran = $this->migrator->getRepository()->getRan();

            $pending = array_values(array_diff(array_keys($files), $ran));
            sort($pending);

            if (empty($pending)) {
                $this->info('Nothing to mark. All migrations are already recorded as run.');
                return;
            }

            $batch = $this->migrator->getRepository()->getNextBatchNumber();

            foreach ($pending as $migrationName) {
                $this->migrator->getRepository()->log($migrationName, $batch);
                $this->line("  Marked as run: <info>{$migrationName}</info>");
            }

            $this->newLine();
            $this->info('Marked ' . count($pending) . ' migration(s) as run (batch ' . $batch . '). No schema changes were made.');
        });

        return $failed ? 1 : 0;
    }
}
