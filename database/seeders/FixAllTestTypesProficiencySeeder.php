<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ClientTestScore;
use App\Services\EnglishProficiencyService;

class FixAllTestTypesProficiencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $service = new EnglishProficiencyService();
        
        echo "Fixing ALL test type proficiency calculations...\n";
        
        // Get all test scores
        $testScores = ClientTestScore::whereNotNull('test_type')->get();
        
        $updated = 0;
        $errors = 0;
        
        foreach ($testScores as $test) {
            try {
                // Calculate correct proficiency
                $scores = [
                    'listening' => $test->listening ?? 0,
                    'reading' => $test->reading ?? 0,
                    'writing' => $test->writing ?? 0,
                    'speaking' => $test->speaking ?? 0,
                    'overall' => $test->overall_score ?? 0
                ];
                
                $result = $service->calculateProficiency($test->test_type, $scores, $test->test_date);
                
                // Update if different
                if ($test->proficiency_level !== $result['level'] || $test->proficiency_points !== $result['points']) {
                    $oldLevel = $test->proficiency_level;
                    $test->update([
                        'proficiency_level' => $result['level'],
                        'proficiency_points' => $result['points']
                    ]);
                    
                    echo "Client {$test->client_id} ({$test->test_type}): {$oldLevel} -> {$result['level']}\n";
                    $updated++;
                }
                
            } catch (\Exception $e) {
                echo "Error updating client {$test->client_id}: " . $e->getMessage() . "\n";
                $errors++;
            }
        }
        
        echo "\nUpdated {$updated} test scores with corrected proficiency levels.\n";
        if ($errors > 0) {
            echo "Encountered {$errors} errors.\n";
        }
        
        echo "\nAll test types now use individual component checks instead of min() function.\n";
        echo "This should resolve all discrepancies between client edit page and EOI page.\n";
    }
}
