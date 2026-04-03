<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ClientEoiReference;
use Illuminate\Support\Facades\DB;

class BackfillEoiRoiData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'eoi:backfill-arrays {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill eoi_subclasses and eoi_states JSON arrays from scalar EOI_subclass and EOI_state fields';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->info('🔍 Running in DRY-RUN mode - no changes will be saved');
        }

        $this->info('Starting EOI/ROI data backfill...');
        $this->newLine();

        // Query all records that have scalar values but missing JSON arrays
        $records = ClientEoiReference::whereNotNull('EOI_subclass')
            ->orWhereNotNull('EOI_state')
            ->get();

        if ($records->isEmpty()) {
            $this->warn('No records found to backfill.');
            return 0;
        }

        $this->info("Found {$records->count()} EOI reference(s) to process");
        $this->newLine();

        $progressBar = $this->output->createProgressBar($records->count());
        $progressBar->start();

        $updated = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($records as $record) {
            try {
                $needsUpdate = false;

                // Backfill eoi_subclasses from EOI_subclass
                if (!empty($record->EOI_subclass) && empty($record->eoi_subclasses)) {
                    // Split by comma if multiple values exist
                    $subclasses = array_map('trim', explode(',', $record->EOI_subclass));
                    $record->eoi_subclasses = $subclasses;
                    $needsUpdate = true;
                }

                // Backfill eoi_states from EOI_state
                if (!empty($record->EOI_state) && empty($record->eoi_states)) {
                    // Split by comma if multiple values exist
                    $states = array_map('trim', explode(',', $record->EOI_state));
                    $record->eoi_states = $states;
                    $needsUpdate = true;
                }

                if ($needsUpdate) {
                    if (!$dryRun) {
                        // Save without triggering boot events that would overwrite scalar fields
                        DB::table('client_eoi_references')
                            ->where('id', $record->id)
                            ->update([
                                'eoi_subclasses' => json_encode($record->eoi_subclasses),
                                'eoi_states' => json_encode($record->eoi_states),
                                'updated_at' => now(),
                            ]);
                    }
                    $updated++;
                } else {
                    $skipped++;
                }

                $progressBar->advance();
            } catch (\Exception $e) {
                $errors++;
                $this->newLine();
                $this->error("Error processing record ID {$record->id}: " . $e->getMessage());
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('📊 Backfill Summary');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line("Total records processed: {$records->count()}");
        $this->info("✓ Updated: {$updated}");
        $this->comment("⊘ Skipped (already populated): {$skipped}");
        
        if ($errors > 0) {
            $this->error("✗ Errors: {$errors}");
        }
        
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        if ($dryRun && $updated > 0) {
            $this->newLine();
            $this->warn('This was a DRY-RUN. Run without --dry-run to apply changes.');
        }

        if (!$dryRun && $updated > 0) {
            $this->newLine();
            $this->info('✓ Backfill completed successfully!');
        }

        return 0;
    }
}
