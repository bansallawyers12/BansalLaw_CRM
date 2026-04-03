<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ClientTestScore;
use App\Services\EnglishProficiencyService;

class UpdateExistingTestScoresProficiencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $proficiencyService = new EnglishProficiencyService();
        $updatedCount = 0;
        
        // Get all test scores that don't have proficiency levels set
        $testScores = ClientTestScore::whereNull('proficiency_level')
            ->whereNotNull('test_type')
            ->whereNotNull('overall_score')
            ->get();
        
        foreach ($testScores as $testScore) {
            try {
                // Prepare scores array
                $scores = [
                    'listening' => $testScore->listening ?? 0,
                    'reading' => $testScore->reading ?? 0,
                    'writing' => $testScore->writing ?? 0,
                    'speaking' => $testScore->speaking ?? 0,
                    'overall' => $testScore->overall_score ?? 0
                ];
                
                // Calculate proficiency level
                $proficiencyResult = $proficiencyService->calculateProficiency(
                    $testScore->test_type, 
                    $scores, 
                    $testScore->test_date
                );
                
                // Update the test score with calculated proficiency
                $testScore->update([
                    'proficiency_level' => $proficiencyResult['level'],
                    'proficiency_points' => $proficiencyResult['points']
                ]);
                
                $updatedCount++;
                
            } catch (\Exception $e) {
                $this->command->warn("Failed to update test score ID {$testScore->id}: " . $e->getMessage());
            }
        }
        
        $this->command->info("Updated {$updatedCount} test scores with proficiency levels.");
    }
}
