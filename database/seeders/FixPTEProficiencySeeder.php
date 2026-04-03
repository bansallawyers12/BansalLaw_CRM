<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ClientTestScore;
use App\Services\EnglishProficiencyService;

class FixPTEProficiencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $service = new EnglishProficiencyService();
        
        echo "Fixing PTE proficiency calculations...\n";
        
        // Get all PTE test scores
        $pteTests = ClientTestScore::where('test_type', 'PTE')->get();
        
        $updated = 0;
        $errors = 0;
        
        foreach ($pteTests as $test) {
            try {
                // Calculate correct proficiency
                $scores = [
                    'listening' => $test->listening ?? 0,
                    'reading' => $test->reading ?? 0,
                    'writing' => $test->writing ?? 0,
                    'speaking' => $test->speaking ?? 0,
                    'overall' => $test->overall_score ?? 0
                ];
                
                $result = $service->calculateProficiency('PTE', $scores, $test->test_date);
                
                // Update if different
                if ($test->proficiency_level !== $result['level'] || $test->proficiency_points !== $result['points']) {
                    $oldLevel = $test->proficiency_level;
                    $test->update([
                        'proficiency_level' => $result['level'],
                        'proficiency_points' => $result['points']
                    ]);
                    
                    echo "Client {$test->client_id}: {$oldLevel} -> {$result['level']} (scores: {$test->listening},{$test->reading},{$test->writing},{$test->speaking})\n";
                    $updated++;
                }
                
            } catch (\Exception $e) {
                echo "Error updating client {$test->client_id}: " . $e->getMessage() . "\n";
                $errors++;
            }
        }
        
        echo "\nUpdated {$updated} PTE test scores.\n";
        if ($errors > 0) {
            echo "Encountered {$errors} errors.\n";
        }
    }
}
