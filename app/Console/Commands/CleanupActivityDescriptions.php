<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ActivitiesLog;

class CleanupActivityDescriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'activities:cleanup-descriptions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove trailing "..." from activity descriptions that were truncated';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting cleanup of activity descriptions...');
        
        // Get all activities where description contains "..."
        $activities = ActivitiesLog::where('description', 'LIKE', '%...%')->get();
        
        if ($activities->isEmpty()) {
            $this->info('No activities found with "..." in descriptions.');
            return 0;
        }
        
        $this->info("Found {$activities->count()} activities with potential truncation.");
        
        $updatedCount = 0;
        $bar = $this->output->createProgressBar($activities->count());
        $bar->start();
        
        foreach ($activities as $activity) {
            $originalDescription = $activity->description;
            
            // Remove trailing "...</p>" pattern
            $cleanedDescription = preg_replace('/\.\.\.<\/p>$/', '</p>', $originalDescription);
            
            // Remove trailing "..." if it's at the end without closing tag
            $cleanedDescription = preg_replace('/\.\.\.$/', '', $cleanedDescription);
            
            // If description was changed, update it
            if ($cleanedDescription !== $originalDescription) {
                $activity->description = $cleanedDescription;
                $activity->save();
                $updatedCount++;
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine(2);
        
        $this->info("✓ Cleanup completed!");
        $this->info("Total activities checked: {$activities->count()}");
        $this->info("Activities updated: {$updatedCount}");
        
        return 0;
    }
}
