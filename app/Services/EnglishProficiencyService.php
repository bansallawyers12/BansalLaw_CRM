<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * English Proficiency Calculation Service
 * 
 * Provides consistent English level calculation logic for both frontend and backend
 * Based on Department of Home Affairs requirements
 */
class EnglishProficiencyService
{
    /**
     * Calculate English proficiency level and points from test scores
     * 
     * @param string $testType The type of test (IELTS, PTE, TOEFL, etc.)
     * @param array $scores Array containing listening, reading, writing, speaking, overall scores
     * @param string|null $testDate Test date in Y-m-d format (for determining pre/post Aug 2025 rules)
     * @return array ['level' => string, 'points' => int]
     */
    public function calculateProficiency(string $testType, array $scores, ?string $testDate = null): array
    {
        // For OET, we need to preserve string values for letter grades
        if (strtoupper($testType) === 'OET') {
            $listening = $scores['listening'] ?? 0;
            $reading = $scores['reading'] ?? 0;
            $writing = $scores['writing'] ?? 0;
            $speaking = $scores['speaking'] ?? 0;
            $overall = $scores['overall'] ?? 0;
        } else {
            $listening = floatval($scores['listening'] ?? 0);
            $reading = floatval($scores['reading'] ?? 0);
            $writing = floatval($scores['writing'] ?? 0);
            $speaking = floatval($scores['speaking'] ?? 0);
            $overall = floatval($scores['overall'] ?? 0);
        }
        
        // Determine if test was taken before or after 7 August 2025
        $isAfterAug2025 = false;
        if ($testDate) {
            try {
                $testDateObj = Carbon::parse($testDate);
                $isAfterAug2025 = $testDateObj->gte('2025-08-07');
            } catch (\Exception $e) {
                // If date parsing fails, default to false (pre-Aug 2025 rules)
            }
        }
        
        switch (strtoupper($testType)) {
            case 'IELTS':
            case 'IELTS_A':
                return $this->calculateIELTSLevel($listening, $reading, $writing, $speaking, $overall);
            case 'PTE':
                return $this->calculatePTELevel($listening, $reading, $writing, $speaking, $overall, $isAfterAug2025);
            case 'TOEFL':
                return $this->calculateTOEFLLevel($listening, $reading, $writing, $speaking, $overall, $isAfterAug2025);
            case 'CAE':
                return $this->calculateCAELevel($listening, $reading, $writing, $speaking, $overall, $isAfterAug2025);
            case 'OET':
                return $this->calculateOETLevel($listening, $reading, $writing, $speaking, $overall, $isAfterAug2025);
            case 'CELPIP':
                return $this->calculateCELPIPLevel($listening, $reading, $writing, $speaking, $overall, $isAfterAug2025);
            case 'MET':
                return $this->calculateMETLevel($listening, $reading, $writing, $speaking, $overall, $isAfterAug2025);
            case 'LANGUAGECERT':
                return $this->calculateLANGUAGECERTLevel($listening, $reading, $writing, $speaking, $overall, $isAfterAug2025);
            default:
                return ['level' => 'Unknown', 'points' => 0];
        }
    }
    
    /**
     * Calculate IELTS proficiency level
     * IELTS scores: 0-9 for each component
     */
    protected function calculateIELTSLevel(float $listening, float $reading, float $writing, float $speaking, float $overall): array
    {
        $scores = [$listening, $reading, $writing, $speaking];
        
        // Superior English: 8.0 in each component
        if ($scores[0] >= 8.0 && $scores[1] >= 8.0 && $scores[2] >= 8.0 && $scores[3] >= 8.0) {
            return ['level' => 'Superior English', 'points' => 20];
        }
        
        // Proficient English: 7.0 in each component
        if ($scores[0] >= 7.0 && $scores[1] >= 7.0 && $scores[2] >= 7.0 && $scores[3] >= 7.0) {
            return ['level' => 'Proficient English', 'points' => 10];
        }
        
        // Competent English: 6.0 in each component
        if ($scores[0] >= 6.0 && $scores[1] >= 6.0 && $scores[2] >= 6.0 && $scores[3] >= 6.0) {
            return ['level' => 'Competent English', 'points' => 0];
        }
        
        // Vocational English: 5.0 in each component
        if ($scores[0] >= 5.0 && $scores[1] >= 5.0 && $scores[2] >= 5.0 && $scores[3] >= 5.0) {
            return ['level' => 'Vocational English', 'points' => 0];
        }
        
        // Functional English: 4.5 average
        $avgScore = array_sum($scores) / count($scores);
        if ($avgScore >= 4.5) {
            return ['level' => 'Functional English', 'points' => 0];
        }
        
        return ['level' => 'Below Functional English', 'points' => 0];
    }
    
    /**
     * Calculate PTE Academic proficiency level
     * PTE scores: 10-90 for each component
     */
    protected function calculatePTELevel(float $listening, float $reading, float $writing, float $speaking, float $overall, bool $isAfterAug2025): array
    {
        $scores = [$listening, $reading, $writing, $speaking];
        
        if ($isAfterAug2025) {
            // Post-Aug 2025 requirements - Individual component thresholds
            // Superior English: 69 Listening, 70 Reading, 85 Writing, 88 Speaking
            if ($listening >= 69 && $reading >= 70 && $writing >= 85 && $speaking >= 88) {
                return ['level' => 'Superior English', 'points' => 20];
            }
            
            // Proficient English: 58 Listening, 59 Reading, 69 Writing, 76 Speaking
            if ($listening >= 58 && $reading >= 59 && $writing >= 69 && $speaking >= 76) {
                return ['level' => 'Proficient English', 'points' => 10];
            }
            
            // Competent English: 47 Listening, 48 Reading, 51 Writing, 54 Speaking
            if ($listening >= 47 && $reading >= 48 && $writing >= 51 && $speaking >= 54) {
                return ['level' => 'Competent English', 'points' => 0];
            }
            
            // Vocational English: 33 Listening, 36 Reading, 29 Writing, 24 Speaking
            if ($listening >= 33 && $reading >= 36 && $writing >= 29 && $speaking >= 24) {
                return ['level' => 'Vocational English', 'points' => 0];
            }
        } else {
            // Pre-Aug 2025 requirements - All components same threshold
            // Superior English: 79 in each component
            if ($scores[0] >= 79 && $scores[1] >= 79 && $scores[2] >= 79 && $scores[3] >= 79) {
                return ['level' => 'Superior English', 'points' => 20];
            }
            
            // Proficient English: 65 in each component
            if ($scores[0] >= 65 && $scores[1] >= 65 && $scores[2] >= 65 && $scores[3] >= 65) {
                return ['level' => 'Proficient English', 'points' => 10];
            }
            
            // Competent English: 50 in each component
            if ($scores[0] >= 50 && $scores[1] >= 50 && $scores[2] >= 50 && $scores[3] >= 50) {
                return ['level' => 'Competent English', 'points' => 0];
            }
            
            // Vocational English: 36 in each component
            if ($scores[0] >= 36 && $scores[1] >= 36 && $scores[2] >= 36 && $scores[3] >= 36) {
                return ['level' => 'Vocational English', 'points' => 0];
            }
        }
        
        // Functional English: 24 overall (after Aug 2025) or 30 overall (before Aug 2025)
        // According to DHA: PTE Academic has the same name but different test and scores after Aug 7, 2025
        $functionalThreshold = $isAfterAug2025 ? 24 : 30;
        if ($overall >= $functionalThreshold) {
            return ['level' => 'Functional English', 'points' => 0];
        }
        
        return ['level' => 'Below Functional English', 'points' => 0];
    }
    
    /**
     * Calculate TOEFL iBT proficiency level
     */
    protected function calculateTOEFLLevel(float $listening, float $reading, float $writing, float $speaking, float $overall, bool $isAfterAug2025): array
    {
        if ($isAfterAug2025) {
            // Post-Aug 2025 requirements - Individual component thresholds
            // Superior English: 26 Listening, 27 Reading, 30 Writing, 28 Speaking
            if ($listening >= 26 && $reading >= 27 && $writing >= 30 && $speaking >= 28) {
                return ['level' => 'Superior English', 'points' => 20];
            }
            
            // Proficient English: 22 Listening, 22 Reading, 26 Writing, 24 Speaking
            if ($listening >= 22 && $reading >= 22 && $writing >= 26 && $speaking >= 24) {
                return ['level' => 'Proficient English', 'points' => 10];
            }
            
            // Competent English: 16 Listening, 16 Reading, 19 Writing, 19 Speaking
            if ($listening >= 16 && $reading >= 16 && $writing >= 19 && $speaking >= 19) {
                return ['level' => 'Competent English', 'points' => 0];
            }
            
            // Vocational English: 8 Listening, 8 Reading, 9 Writing, 14 Speaking
            if ($listening >= 8 && $reading >= 8 && $writing >= 9 && $speaking >= 14) {
                return ['level' => 'Vocational English', 'points' => 0];
            }
        } else {
            // Pre-Aug 2025 requirements - Individual component thresholds
            // Superior English: 28 Listening, 29 Reading, 30 Writing, 26 Speaking
            if ($listening >= 28 && $reading >= 29 && $writing >= 30 && $speaking >= 26) {
                return ['level' => 'Superior English', 'points' => 20];
            }
            
            // Proficient English: 24 Listening, 24 Reading, 27 Writing, 23 Speaking
            if ($listening >= 24 && $reading >= 24 && $writing >= 27 && $speaking >= 23) {
                return ['level' => 'Proficient English', 'points' => 10];
            }
            
            // Competent English: 12 Listening, 13 Reading, 21 Writing, 21 Speaking
            if ($listening >= 12 && $reading >= 13 && $writing >= 21 && $speaking >= 21) {
                return ['level' => 'Competent English', 'points' => 0];
            }
            
            // Vocational English: 4 Reading/Listening, 14 Writing/Speaking
            if ($reading >= 4 && $listening >= 4 && $writing >= 14 && $speaking >= 14) {
                return ['level' => 'Vocational English', 'points' => 0];
            }
        }
        
        // Functional English: 26 total (after Aug 2025) or 32 total (before Aug 2025)
        // Note: TOEFL iBT was not approved from 26 July 2023 to 4 May 2024
        $functionalThreshold = $isAfterAug2025 ? 26 : 32;
        if ($overall >= $functionalThreshold) {
            return ['level' => 'Functional English', 'points' => 0];
        }
        
        return ['level' => 'Below Functional English', 'points' => 0];
    }
    
    /**
     * Calculate CAE proficiency level
     */
    protected function calculateCAELevel(float $listening, float $reading, float $writing, float $speaking, float $overall, bool $isAfterAug2025): array
    {
        if ($isAfterAug2025) {
            // After Aug 2025: Different scores per component
            // Superior English: 186 Listening, 190 Reading, 210 Writing, 208 Speaking
            if ($listening >= 186 && $reading >= 190 && $writing >= 210 && $speaking >= 208) {
                return ['level' => 'Superior English', 'points' => 20];
            }
            
            // Proficient English: 175 Listening, 179 Reading, 193 Writing, 194 Speaking
            if ($listening >= 175 && $reading >= 179 && $writing >= 193 && $speaking >= 194) {
                return ['level' => 'Proficient English', 'points' => 10];
            }
            
            // Competent English: 163 Listening, 163 Reading, 170 Writing, 179 Speaking
            if ($listening >= 163 && $reading >= 163 && $writing >= 170 && $speaking >= 179) {
                return ['level' => 'Competent English', 'points' => 0];
            }
            
            // Vocational English: Not accepted after Aug 7, 2025
            // Functional English: Not accepted after Aug 7, 2025
            
        } else {
            // Before Aug 2025: Uniform scores across all components
            $scores = [$listening, $reading, $writing, $speaking];
            
            // Superior English: 200 in each component
            if ($scores[0] >= 200 && $scores[1] >= 200 && $scores[2] >= 200 && $scores[3] >= 200) {
                return ['level' => 'Superior English', 'points' => 20];
            }
            
            // Proficient English: 185 in each component
            if ($scores[0] >= 185 && $scores[1] >= 185 && $scores[2] >= 185 && $scores[3] >= 185) {
                return ['level' => 'Proficient English', 'points' => 10];
            }
            
            // Competent English: 169 in each component
            if ($scores[0] >= 169 && $scores[1] >= 169 && $scores[2] >= 169 && $scores[3] >= 169) {
                return ['level' => 'Competent English', 'points' => 0];
            }
            
            // Vocational English: 154 in each component
            if ($scores[0] >= 154 && $scores[1] >= 154 && $scores[2] >= 154 && $scores[3] >= 154) {
                return ['level' => 'Vocational English', 'points' => 0];
            }
            
            // Functional English: Total band >= 147
            if ($overall >= 147) {
                return ['level' => 'Functional English', 'points' => 0];
            }
        }
        
        return ['level' => 'Below Functional English', 'points' => 0];
    }
    
    /**
     * Calculate OET proficiency level
     */
    protected function calculateOETLevel($listening, $reading, $writing, $speaking, $overall, bool $isAfterAug2025): array
    {
        if ($isAfterAug2025) {
            // After Aug 2025: OET uses numerical scores (0-500 scale)
            // Superior English: 390 Listening, 400 Reading, 420 Writing, 400 Speaking
            if ($listening >= 390 && $reading >= 400 && $writing >= 420 && $speaking >= 400) {
                return ['level' => 'Superior English', 'points' => 20];
            }
            
            // Proficient English: 350 Listening, 360 Reading, 380 Writing, 360 Speaking
            if ($listening >= 350 && $reading >= 360 && $writing >= 380 && $speaking >= 360) {
                return ['level' => 'Proficient English', 'points' => 10];
            }
            
            // Competent English: 290 Listening, 310 Reading, 290 Writing, 330 Speaking
            if ($listening >= 290 && $reading >= 310 && $writing >= 290 && $speaking >= 330) {
                return ['level' => 'Competent English', 'points' => 0];
            }
            
            // Vocational English: 220 Listening, 240 Reading, 200 Writing, 270 Speaking
            if ($listening >= 220 && $reading >= 240 && $writing >= 200 && $speaking >= 270) {
                return ['level' => 'Vocational English', 'points' => 0];
            }
            
            // Functional English: Overall band score >= 1020
            if ($overall >= 1020) {
                return ['level' => 'Functional English', 'points' => 0];
            }
        } else {
            // Before Aug 2025: OET uses letter grades A-E
            $gradeOrder = ['A' => 5, 'B' => 4, 'C' => 3, 'D' => 2, 'E' => 1];
            $scores = [
                $gradeOrder[$listening] ?? 0,
                $gradeOrder[$reading] ?? 0,
                $gradeOrder[$writing] ?? 0,
                $gradeOrder[$speaking] ?? 0
            ];
            
            // Superior English: A in each component
            if ($scores[0] >= 5 && $scores[1] >= 5 && $scores[2] >= 5 && $scores[3] >= 5) {
                return ['level' => 'Superior English', 'points' => 20];
            }
            
            // Proficient English: B in each component
            if ($scores[0] >= 4 && $scores[1] >= 4 && $scores[2] >= 4 && $scores[3] >= 4) {
                return ['level' => 'Proficient English', 'points' => 10];
            }
            
            // Competent English: B in each component (same as Proficient for pre-Aug 2025)
            if ($scores[0] >= 4 && $scores[1] >= 4 && $scores[2] >= 4 && $scores[3] >= 4) {
                return ['level' => 'Competent English', 'points' => 0];
            }
            
            // Vocational English: B in each component (same as Proficient for pre-Aug 2025)
            if ($scores[0] >= 4 && $scores[1] >= 4 && $scores[2] >= 4 && $scores[3] >= 4) {
                return ['level' => 'Vocational English', 'points' => 0];
            }
            
            // Functional English: Not listed as an option before Aug 7, 2025
        }
        
        return ['level' => 'Below Functional English', 'points' => 0];
    }
    
    /**
     * Calculate CELPIP proficiency level
     */
    protected function calculateCELPIPLevel(float $listening, float $reading, float $writing, float $speaking, float $overall, bool $isAfterAug2025): array
    {
        // CELPIP is only accepted for tests on or after 7 August 2025
        if (!$isAfterAug2025) {
            return ['level' => 'CELPIP Not Accepted Before Aug 7, 2025', 'points' => 0];
        }
        
        // Superior English: 10 Listening, 10 Reading, 12 Writing, 10 Speaking
        if ($listening >= 10 && $reading >= 10 && $writing >= 12 && $speaking >= 10) {
            return ['level' => 'Superior English', 'points' => 20];
        }
        
        // Proficient English: 9 Listening, 8 Reading, 10 Writing, 8 Speaking
        if ($listening >= 9 && $reading >= 8 && $writing >= 10 && $speaking >= 8) {
            return ['level' => 'Proficient English', 'points' => 10];
        }
        
        // Competent English: 7 in each component
        if ($listening >= 7 && $reading >= 7 && $writing >= 7 && $speaking >= 7) {
            return ['level' => 'Competent English', 'points' => 0];
        }
        
        // Vocational English: 5 in each component
        if ($listening >= 5 && $reading >= 5 && $writing >= 5 && $speaking >= 5) {
            return ['level' => 'Vocational English', 'points' => 0];
        }
        
        // Functional English: Overall band score >= 5
        if ($overall >= 5) {
            return ['level' => 'Functional English', 'points' => 0];
        }
        
        return ['level' => 'Below Functional English', 'points' => 0];
    }
    
    /**
     * Calculate MET proficiency level
     */
    protected function calculateMETLevel(float $listening, float $reading, float $writing, float $speaking, float $overall, bool $isAfterAug2025): array
    {
        // MET is only accepted for tests on or after 7 August 2025
        if (!$isAfterAug2025) {
            return ['level' => 'MET Not Accepted Before Aug 7, 2025', 'points' => 0];
        }
        
        // Superior English: Not available for MET
        
        // Proficient English: 61 Listening, 63 Reading, 74 Writing, 59 Speaking
        if ($listening >= 61 && $reading >= 63 && $writing >= 74 && $speaking >= 59) {
            return ['level' => 'Proficient English', 'points' => 10];
        }
        
        // Competent English: 56 Listening, 55 Reading, 57 Writing, 48 Speaking
        if ($listening >= 56 && $reading >= 55 && $writing >= 57 && $speaking >= 48) {
            return ['level' => 'Competent English', 'points' => 0];
        }
        
        // Vocational English: 49 Listening, 47 Reading, 45 Writing, 38 Speaking
        if ($listening >= 49 && $reading >= 47 && $writing >= 45 && $speaking >= 38) {
            return ['level' => 'Vocational English', 'points' => 0];
        }
        
        // Functional English: Overall band score >= 38
        if ($overall >= 38) {
            return ['level' => 'Functional English', 'points' => 0];
        }
        
        return ['level' => 'Below Functional English', 'points' => 0];
    }
    
    /**
     * Calculate LanguageCert proficiency level
     */
    protected function calculateLANGUAGECERTLevel(float $listening, float $reading, float $writing, float $speaking, float $overall, bool $isAfterAug2025): array
    {
        // LANGUAGECERT is only accepted for tests on or after 7 August 2025
        if (!$isAfterAug2025) {
            return ['level' => 'LANGUAGECERT Not Accepted Before Aug 7, 2025', 'points' => 0];
        }
        
        // Superior English: 80 Listening, 83 Reading, 89 Writing, 89 Speaking
        if ($listening >= 80 && $reading >= 83 && $writing >= 89 && $speaking >= 89) {
            return ['level' => 'Superior English', 'points' => 20];
        }
        
        // Proficient English: 67 Listening, 71 Reading, 78 Writing, 82 Speaking
        if ($listening >= 67 && $reading >= 71 && $writing >= 78 && $speaking >= 82) {
            return ['level' => 'Proficient English', 'points' => 10];
        }
        
        // Competent English: 57 Listening, 60 Reading, 64 Writing, 70 Speaking
        if ($listening >= 57 && $reading >= 60 && $writing >= 64 && $speaking >= 70) {
            return ['level' => 'Competent English', 'points' => 0];
        }
        
        // Vocational English: 41 Listening, 44 Reading, 45 Writing, 54 Speaking
        if ($listening >= 41 && $reading >= 44 && $writing >= 45 && $speaking >= 54) {
            return ['level' => 'Vocational English', 'points' => 0];
        }
        
        // Functional English: Overall band score >= 38
        if ($overall >= 38) {
            return ['level' => 'Functional English', 'points' => 0];
        }
        
        return ['level' => 'Below Functional English', 'points' => 0];
    }
}
