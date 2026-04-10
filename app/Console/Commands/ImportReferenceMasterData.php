<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Copy master/reference rows from a second PostgreSQL database (e.g. local Migration Manager)
 * into this app's DB. Uses column intersection so minor schema drift still works.
 */
class ImportReferenceMasterData extends Command
{
    protected $signature = 'import:reference-master-data
                            {--source=pgsql_reference : Laravel connection name for the reference PostgreSQL database}
                            {--target= : Target connection (default: database.default)}
                            {--table=* : Tables to copy (default: user_roles, branches; adds teams/departments if both DBs have them)}
                            {--dry-run : List what would be copied without writing}';

    protected $description = 'Import user_roles, branches, teams, and optional departments from a reference PostgreSQL database';

    public function handle(): int
    {
        $source = $this->option('source');
        $target = $this->option('target') ?: config('database.default');
        $dryRun = (bool) $this->option('dry-run');

        if (! config("database.connections.{$source}")) {
            $this->error("Connection [{$source}] is not defined. Add pgsql_reference to config/database.php and PG_REFERENCE_* in .env.");

            return 1;
        }

        try {
            DB::connection($source)->getPdo();
        } catch (\Throwable $e) {
            $this->error("Cannot connect to source [{$source}]: {$e->getMessage()}");

            return 1;
        }

        try {
            DB::connection($target)->getPdo();
        } catch (\Throwable $e) {
            $this->error("Cannot connect to target [{$target}]: {$e->getMessage()}");

            return 1;
        }

        $requested = $this->option('table');
        $tables = ! empty($requested)
            ? $requested
            : $this->defaultTables($source, $target);

        if (empty($tables)) {
            $this->warn('No tables to import.');

            return 0;
        }

        $refDbName = (string) DB::connection($source)->getDatabaseName();
        if ($refDbName === '') {
            $this->error('PG_REFERENCE_DATABASE is empty; set it in .env to the reference PostgreSQL database name.');

            return 1;
        }

        $this->info('Source: '.$source.' → Target: '.$target.($dryRun ? ' (dry-run)' : ''));
        $this->line('Reference PostgreSQL database: '.$refDbName);
        $this->newLine();

        foreach ($tables as $table) {
            $result = $this->importTable($source, $target, $table, $dryRun);
            if ($result === false) {
                return 1;
            }
        }

        if (! $dryRun && DB::connection($target)->getDriverName() === 'pgsql') {
            foreach ($tables as $table) {
                if (Schema::connection($target)->hasTable($table)) {
                    $this->syncPostgresSequence($target, $table);
                }
            }
        }

        $this->newLine();
        $this->info('Done.');

        if (empty($requested)) {
            $expected = $this->defaultTables($source, $target);
            $missing = array_values(array_filter($expected, fn (string $t) => ! Schema::connection($source)->hasTable($t)));
            if ($missing !== []) {
                $this->newLine();
                $this->warn('Reference DB is missing table(s): '.implode(', ', $missing).' (search_path / schema: '.(DB::connection($source)->getConfig('schema') ?? 'public').').');
                $this->line('Restore or migrate the full CRM schema into `'.$refDbName.'`, then run: php artisan import:reference-master-data');
            }
        }

        return 0;
    }

    /**
     * @return string[]
     */
    protected function defaultTables(string $source, string $target): array
    {
        $tables = ['user_roles', 'branches'];
        if (Schema::connection($source)->hasTable('teams') && Schema::connection($target)->hasTable('teams')) {
            $tables[] = 'teams';
        }
        if (Schema::connection($source)->hasTable('departments') && Schema::connection($target)->hasTable('departments')) {
            $tables[] = 'departments';
        }

        return $tables;
    }

    protected function importTable(string $source, string $target, string $table, bool $dryRun): bool
    {
        if (! Schema::connection($source)->hasTable($table)) {
            $this->warn("Skip [{$table}]: not found in source.");

            return true;
        }

        if (! Schema::connection($target)->hasTable($table)) {
            $this->error("Table [{$table}] does not exist in target. Run migrations or create the table first.");

            return false;
        }

        $srcCols = Schema::connection($source)->getColumnListing($table);
        $tgtCols = Schema::connection($target)->getColumnListing($table);
        $columns = array_values(array_intersect($srcCols, $tgtCols));

        if (! in_array('id', $columns, true)) {
            $this->error("Table [{$table}]: cannot align on column `id` (missing from source/target intersection).");

            return false;
        }

        $count = DB::connection($source)->table($table)->count();
        $this->info("[{$table}] {$count} row(s) in source; columns: ".implode(', ', $columns));

        if ($dryRun) {
            return true;
        }

        $imported = 0;
        $errors = 0;

        DB::connection($source)->table($table)->orderBy('id')->chunkById(200, function ($rows) use ($target, $table, $columns, &$imported, &$errors) {
            foreach ($rows as $row) {
                $rowArr = (array) $row;
                try {
                    $data = [];
                    foreach ($columns as $col) {
                        $data[$col] = $rowArr[$col] ?? null;
                    }
                    $id = $data['id'];
                    unset($data['id']);
                    DB::connection($target)->table($table)->updateOrInsert(['id' => $id], $data);
                    $imported++;
                } catch (\Throwable $e) {
                    $errors++;
                    $this->error('  Row id='.($rowArr['id'] ?? '?').' '.$e->getMessage());
                }
            }
        }, 'id');

        $this->line("  Imported/updated: {$imported}, errors: {$errors}");

        return true;
    }

    protected function syncPostgresSequence(string $connection, string $table): void
    {
        if (DB::connection($connection)->getDriverName() !== 'pgsql') {
            return;
        }

        $max = DB::connection($connection)->table($table)->max('id');
        if ($max === null || (int) $max < 1) {
            return;
        }

        $schema = DB::connection($connection)->getConfig('schema') ?? 'public';
        $qualified = $schema.'.'.$table;
        $row = DB::connection($connection)->selectOne(
            'SELECT pg_get_serial_sequence(?, ?) AS seq',
            [$qualified, 'id']
        );

        if (! $row || empty($row->seq)) {
            return;
        }

        DB::connection($connection)->statement('SELECT setval(?, ?::bigint, true)', [$row->seq, $max]);
        $this->line("  Sequence for {$schema}.{$table}.id set to {$max}");
    }
}
