<?php

namespace App\Console\Commands;

use App\Helpers\FontAwesomeHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateFontAwesomeDbIcons extends Command
{
    protected $signature = 'fontawesome:migrate-db-icons
                            {--dry-run : Show changes without writing}
                            {--force : Skip confirmation}';

    protected $description = 'One-time: update stored Font Awesome icon class strings (e.g. email_labels.icon) to FA6';

    /**
     * Tables/columns known to store FA icon class strings.
     *
     * @var array<int, array{table: string, column: string}>
     */
    protected array $targets = [
        ['table' => 'email_labels', 'column' => 'icon'],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! $this->option('force') && ! $this->confirm('Update stored Font Awesome icon classes to FA6?', true)) {
            $this->warn('Aborted.');

            return self::SUCCESS;
        }

        $totalUpdated = 0;

        foreach ($this->targets as $target) {
            $table = $target['table'];
            $column = $target['column'];

            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                $this->line("Skip {$table}.{$column} (missing).");

                continue;
            }

            $rows = DB::table($table)
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->get(['id', $column]);

            $updated = 0;

            foreach ($rows as $row) {
                $original = (string) $row->{$column};
                $migrated = FontAwesomeHelper::migrateClasses($original);

                if ($migrated === $original) {
                    continue;
                }

                $this->line("  [{$table}#{$row->id}] {$original} → {$migrated}");

                if (! $dryRun) {
                    DB::table($table)->where('id', $row->id)->update([
                        $column => $migrated,
                        'updated_at' => now(),
                    ]);
                }

                $updated++;
            }

            $this->info(($dryRun ? '[dry-run] ' : '')."{$table}.{$column}: {$updated} row(s).");
            $totalUpdated += $updated;
        }

        $this->info(($dryRun ? '[dry-run] ' : '')."Done. {$totalUpdated} total update(s).");

        return self::SUCCESS;
    }
}
