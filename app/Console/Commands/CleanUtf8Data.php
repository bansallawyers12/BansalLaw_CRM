<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Helpers\Utf8Helper;

class CleanUtf8Data extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'utf8:clean {--table=notes : Table to clean} {--column=description : Column to clean} {--dry-run : Show what would be changed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean UTF-8 encoding issues in database tables';

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
     * @return int
     */
    public function handle()
    {
        $table = $this->option('table');
        $column = $this->option('column');
        $dryRun = $this->option('dry-run');

        $this->info("Starting UTF-8 cleanup for table: {$table}, column: {$column}");
        
        if ($dryRun) {
            $this->warn("DRY RUN MODE - No changes will be made");
        }

        try {
            // Get records that might have UTF-8 issues
            $records = DB::table($table)
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->get(['id', $column]);

            $totalRecords = $records->count();
            $cleanedCount = 0;
            $errorCount = 0;

            $this->info("Found {$totalRecords} records to process");

            $progressBar = $this->output->createProgressBar($totalRecords);
            $progressBar->start();

            foreach ($records as $record) {
                try {
                    $originalValue = $record->$column;
                    $cleanedValue = Utf8Helper::safeSanitize($originalValue);

                    // Only update if the value changed
                    if ($originalValue !== $cleanedValue) {
                        if (!$dryRun) {
                            DB::table($table)
                                ->where('id', $record->id)
                                ->update([$column => $cleanedValue]);
                        }
                        $cleanedCount++;
                        
                        if ($this->output->isVerbose()) {
                            $this->line("\nCleaned record ID {$record->id}");
                            $this->line("Before: " . substr($originalValue, 0, 100) . "...");
                            $this->line("After:  " . substr($cleanedValue, 0, 100) . "...");
                        }
                    }
                } catch (\Exception $e) {
                    $errorCount++;
                    if ($this->output->isVerbose()) {
                        $this->error("\nError processing record ID {$record->id}: " . $e->getMessage());
                    }
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->line('');

            $this->info("Processing completed:");
            $this->info("- Total records processed: {$totalRecords}");
            $this->info("- Records cleaned: {$cleanedCount}");
            $this->info("- Errors encountered: {$errorCount}");

            if ($dryRun && $cleanedCount > 0) {
                $this->warn("Run without --dry-run to apply these changes");
            }

        } catch (\Exception $e) {
            $this->error("Error during cleanup: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
