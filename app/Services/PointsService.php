<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\AnzscoOccupation;
use App\Models\ClientSpouseDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Points Calculation Service for EOI/ROI
 * 
 * Calculates points based on Australian skilled migration criteria
 * for subclasses 189, 190, and 491
 */
class PointsService
{
    // Points thresholds for age brackets
    // Format: [min_age_inclusive, max_age_exclusive, points]
    // e.g., [18, 25, 25] means ages 18.0 to 24.999... get 25 points
    const AGE_BRACKETS = [
        [18, 25, 25],  // 18 to less than 25
        [25, 33, 30],  // 25 to less than 33
        [33, 40, 25],  // 33 to less than 40
        [40, 45, 15],  // 40 to less than 45
    ];

    // English level points
    const ENGLISH_COMPETENT = 0;
    const ENGLISH_PROFICIENT = 10;
    const ENGLISH_SUPERIOR = 20;

    // Nomination bonuses by subclass
    const NOMINATION_189 = 0;
    const NOMINATION_190 = 5;
    const NOMINATION_491 = 15;

    // Cache duration in minutes
    const CACHE_DURATION = 15;

    /**
     * Compute points for a client with optional subclass and forward-looking period
     * 
     * @param Admin $client The client to calculate points for
     * @param string|null $selectedSubclass EOI subclass (189, 190, 491)
     * @param int $monthsAhead Number of months ahead to check for warnings
     * @return array ['total' => int, 'breakdown' => array, 'warnings' => array]
     */
    public function compute(Admin $client, ?string $selectedSubclass = null, int $monthsAhead = 6): array
    {
        // Use cache to avoid expensive recalculations
        $cacheKey = "points_{$client->id}_{$selectedSubclass}_{$monthsAhead}";
        
        return Cache::remember($cacheKey, self::CACHE_DURATION, function () use ($client, $selectedSubclass, $monthsAhead) {
            return $this->calculatePoints($client, $selectedSubclass, $monthsAhead);
        });
    }

    /**
     * Clear cache for a specific client
     */
    public function clearCache(int $clientId): void
    {
        $subclasses = ['189', '190', '491', '491-Family', null];
        
        foreach ($subclasses as $subclass) {
            Cache::forget("points_{$clientId}_{$subclass}_6");
        }
    }

    /**
     * Get partner points only (e.g. for EOI/ROI sheet).
     * Single / no partner = 10 pts; partner citizen/PR = 10; partner with skills = 10; partner English only = 5; else 0.
     * For subclass 189: skilled partner (10 pts) requires partner occupation on MLTSSL; if not on MLTSSL, 5 pts for English only.
     * No Competent English = 0 pts regardless.
     *
     * @param Admin $client
     * @param string|null $subclass EOI subclass (189, 190, 491) - 189 applies MLTSSL check for skilled partner
     * @return int|null Points value or null on error
     */
    public function getPartnerPoints(Admin $client, ?string $subclass = null): ?int
    {
        try {
            $referenceDate = $this->getInvitationDate($client);
            $partnerData = $this->calculatePartnerPoints($client, $referenceDate, $subclass);
            return $partnerData['points'] ?? null;
        } catch (\Throwable $e) {
            Log::warning('PointsService::getPartnerPoints failed', [
                'client_id' => $client->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Perform the actual points calculation
     */
    protected function calculatePoints(Admin $client, ?string $selectedSubclass, int $monthsAhead): array
    {
        $breakdown = [];
        $warnings = [];
        
        // Determine invitation date (or use today)
        $invitationDate = $this->getInvitationDate($client);
        
        // 1. Age points
        $ageData = $this->calculateAgePoints($client, $invitationDate);
        $breakdown['age'] = $ageData;
        
        // 2. English points
        $englishData = $this->calculateEnglishPoints($client, $invitationDate);
        $breakdown['english'] = $englishData;
        
        // 3. Employment points (separate AU and Overseas)
        $australianWorkData = $this->calculateAustralianWorkExperience($client, $invitationDate);
        $overseasWorkData = $this->calculateOverseasWorkExperience($client, $invitationDate);
        $breakdown['australian_work_experience'] = $australianWorkData;
        $breakdown['overseas_work_experience'] = $overseasWorkData;
        
        // 4. Education points
        $educationData = $this->calculateEducationPoints($client);
        $breakdown['education'] = $educationData;
        
        // 5. Bonus points (separate categories)
        $this->addBonusCategories($client, $breakdown);
        
        // 6. Partner points (subclass affects 189 MLTSSL rule for skilled partner)
        $partnerData = $this->calculatePartnerPoints($client, $invitationDate, $selectedSubclass);
        $breakdown['partner'] = $partnerData;
        
        // 7. Nomination points based on subclass
        $nominationData = $this->calculateNominationPoints($selectedSubclass);
        $breakdown['nomination'] = $nominationData;
        
        // Calculate total
        $total = array_sum(array_column($breakdown, 'points'));
        
        // Generate warnings for upcoming changes
        $warnings = $this->generateWarnings($client, $invitationDate, $monthsAhead, $breakdown);
        
        return [
            'total' => $total,
            'breakdown' => $breakdown,
            'warnings' => $warnings,
        ];
    }

    /**
     * Calculate age points based on DOB
     */
    protected function calculateAgePoints(Admin $client, Carbon $referenceDate): array
    {
        if (!$client->dob) {
            return [
                'detail' => 'Date of birth not set',
                'points' => 0,
                'warning' => true,
            ];
        }

        $ageDiff = Carbon::parse($client->dob)->diff($referenceDate);
        $ageYears = $ageDiff->y;
        $ageMonths = $ageDiff->m;
        
        // Calculate total age in years for points calculation
        $age = $ageYears + ($ageMonths / 12);
        
        // Check age brackets (upper bound is exclusive)
        foreach (self::AGE_BRACKETS as [$min, $max, $points]) {
            if ($age >= $min && $age < $max) {
                return [
                    'detail' => "{$ageYears} years {$ageMonths} months",
                    'points' => $points,
                    'age' => $age,
                ];
            }
        }
        
        // Age 45+ or under 18
        return [
            'detail' => "{$ageYears} years {$ageMonths} months (outside eligible range)",
            'points' => 0,
            'age' => $age,
            'warning' => $age >= 45,
        ];
    }

    /**
     * Calculate English language points using stored proficiency levels
     */
    protected function calculateEnglishPoints(Admin $client, Carbon $referenceDate): array
    {
        // Load test scores (assuming relationship exists)
        $testScores = $client->testScores ?? collect();
        
        if ($testScores->isEmpty()) {
            return [
                'detail' => 'No English test recorded',
                'points' => self::ENGLISH_COMPETENT,
                'level' => 'competent',
                'warning' => true,
            ];
        }

        $validTests = $testScores->filter(function ($test) use ($referenceDate) {
            if (!$test->test_date) {
                return false;
            }
            
            $testDate = Carbon::parse($test->test_date);
            $expiryDate = $testDate->copy()->addYears(3); // Default 3-year validity
            
            return $referenceDate->lte($expiryDate);
        });

        if ($validTests->isEmpty()) {
            return [
                'detail' => 'English test expired',
                'points' => self::ENGLISH_COMPETENT,
                'level' => 'competent',
                'warning' => true,
            ];
        }

        $completeTests = $validTests->filter(function ($test) {
            return $this->hasCompleteEnglishScores($test);
        });

        if ($completeTests->isEmpty()) {
            $bestIncomplete = $validTests->sortByDesc(function ($test) {
                return $test->proficiency_points ?? 0;
            })->first();

            if ($bestIncomplete) {
                $detail = 'Incomplete English data';
                if ($bestIncomplete->proficiency_level) {
                    $detail .= ' (last recorded: ' . $bestIncomplete->proficiency_level . ')';
                }

                return [
                    'detail' => $detail,
                    'points' => self::ENGLISH_COMPETENT,
                    'level' => 'incomplete',
                    'warning' => true,
                    'stored_level' => $bestIncomplete->proficiency_level,
                    'stored_points' => $bestIncomplete->proficiency_points,
                ];
            }
        }

        // Get best valid test based on stored proficiency points (only complete tests)
        $bestTest = $completeTests->sortByDesc(function ($test) {
            return $test->proficiency_points ?? 0;
        })->first();

        // Use stored proficiency level and points if available
        if ($bestTest->proficiency_level && $bestTest->proficiency_points !== null) {
            $points = $bestTest->proficiency_points;
            
            // Convert proficiency level to lowercase for consistency
            $level = strtolower(str_replace(' English', '', $bestTest->proficiency_level));
            
            return [
                'detail' => $bestTest->proficiency_level,
                'points' => $points,
                'level' => $level,
                'test_type' => $bestTest->test_type ?? 'Unknown',
                'test_date' => $bestTest->test_date,
                'expiry_date' => Carbon::parse($bestTest->test_date)->addYears(3)->format('Y-m-d'),
            ];
        }

        // Fallback to old calculation method if proficiency level not stored
        $level = $this->deriveEnglishLevel($bestTest);
        $points = match ($level) {
            'superior' => self::ENGLISH_SUPERIOR,
            'proficient' => self::ENGLISH_PROFICIENT,
            default => self::ENGLISH_COMPETENT,
        };

        return [
            'detail' => ucfirst($level) . ' English',
            'points' => $points,
            'level' => $level,
            'test_type' => $bestTest->test_type ?? 'Unknown',
            'test_date' => $bestTest->test_date,
            'expiry_date' => Carbon::parse($bestTest->test_date)->addYears(3)->format('Y-m-d'),
        ];
    }

    /**
     * Derive English level from test scores
     * This is simplified - actual logic should check each component (L/R/W/S)
     */
    protected function deriveEnglishLevel($test): string
    {
        // Simplified logic - in production, check all 4 components
        $overallScore = $test->overall_score ?? 0;
        
        // Example thresholds (IELTS-based)
        if ($overallScore >= 8) {
            return 'superior';
        } elseif ($overallScore >= 7) {
            return 'proficient';
        }
        
        return 'competent';
    }

    /**
     * Calculate Australian work experience points
     * Only counts experiences marked as "relevant" in Australia
     */
    protected function calculateAustralianWorkExperience(Admin $client, Carbon $referenceDate): array
    {
        // Load employment history - ONLY relevant experiences
        $experiences = $client->experiences ?? collect();
        
        // Filter by relevant_experience = 1 and Australia
        $relevantExperiences = $experiences->where('relevant_experience', 1)->where('job_country', 'Australia');
        
        $years = $this->calculateFTEYears($relevantExperiences, $referenceDate);
        $points = $this->getEmploymentPoints($years, 'australian');
        
        return [
            'detail' => $years > 0 ? sprintf('%.1f years', $years) : 'Not claimed',
            'points' => $points,
            'years' => $years,
        ];
    }

    /**
     * Calculate Overseas work experience points
     * Only counts experiences marked as "relevant" outside Australia
     */
    protected function calculateOverseasWorkExperience(Admin $client, Carbon $referenceDate): array
    {
        // Load employment history - ONLY relevant experiences
        $experiences = $client->experiences ?? collect();
        
        // Filter by relevant_experience = 1 and not Australia
        $relevantExperiences = $experiences->where('relevant_experience', 1)->where('job_country', '!=', 'Australia');
        
        $years = $this->calculateFTEYears($relevantExperiences, $referenceDate);
        $points = $this->getEmploymentPoints($years, 'overseas');
        
        return [
            'detail' => $years > 0 ? sprintf('%.1f years', $years) : 'Not claimed',
            'points' => $points,
            'years' => $years,
        ];
    }

    /**
     * Calculate years from experience records
     * Only counts experience within the last 10 years from the reference date.
     * Note: For EOI purposes, any employment 20+ hours/week counts as full-time
     */
    protected function calculateFTEYears($experiences, Carbon $referenceDate): float
    {
        $totalDays = 0;
        $windowStart = $referenceDate->copy()->subYears(10);

        foreach ($experiences as $exp) {
            // Use actual field names from ClientExperience model
            if (!$exp->job_start_date) {
                continue;
            }

            $start = Carbon::parse($exp->job_start_date);
            $end = $exp->job_finish_date ? Carbon::parse($exp->job_finish_date) : $referenceDate;

            // Don't count future experience
            if ($start->gt($referenceDate)) {
                continue;
            }

            $end = $end->min($referenceDate);

            // Only count the portion that falls within the last 10 years
            $overlapStart = $start->max($windowStart);
            $overlapEnd = $end->min($referenceDate);

            if ($overlapStart->lt($overlapEnd)) {
                $days = $overlapStart->diffInDays($overlapEnd);
                // Count all work as full-time (20+ hours/week qualifies for EOI)
                // No FTE multiplier needed - part-time, casual, full-time all count the same
                $totalDays += $days;
            }
        }

        return round($totalDays / 365, 2);
    }

    /**
     * Get employment points based on years and type
     */
    protected function getEmploymentPoints(float $years, string $type): int
    {
        if ($type === 'australian') {
            if ($years >= 8) return 20;
            if ($years >= 5) return 15;
            if ($years >= 3) return 10;
            if ($years >= 1) return 5;
        } else {
            // Overseas
            if ($years >= 8) return 15;
            if ($years >= 5) return 10;
            if ($years >= 3) return 5;
        }
        
        return 0;
    }

    /**
     * Calculate education points
     */
    protected function calculateEducationPoints(Admin $client): array
    {
        // Load qualifications
        $qualifications = $client->qualifications ?? collect();
        
        if ($qualifications->isEmpty()) {
            return [
                'detail' => 'No qualifications recorded',
                'points' => 0,
                'warning' => true,
            ];
        }

        // Find highest qualification
        $highest = $qualifications->sortByDesc(function ($qual) {
            return $this->getQualificationLevel($qual->level);
        })->first();

        $points = $this->getQualificationPoints($highest->level);
        
        return [
            'detail' => $highest->level ?? 'Unknown',
            'points' => $points,
            'qualification' => $highest->qualification_name ?? 'N/A',
        ];
    }

    /**
     * Get qualification level for sorting
     * Higher number = higher qualification level
     */
    protected function getQualificationLevel(string $level): int
    {
        $levels = [
            'Doctorate' => 5,
            'Doctoral' => 5,
            'PhD' => 5,
            'Masters' => 4,
            'Graduate Diploma' => 3,
            'Graduate Certificate' => 3,
            'Bachelor Honours' => 3,
            'Bachelor' => 3,
            'Associate Degree' => 2,
            'Advanced Diploma' => 2,
            'Diploma' => 1,
            'Certificate IV' => 1,   // Same level as Diploma (10 points)
            'Certificate III' => 1,  // Same level as Diploma (10 points)
            'Trade' => 1,
            'Certificate II' => 0,   // Below Diploma level (0 points)
            'Certificate I' => 0,    // Below Diploma level (0 points)
        ];
        
        // Check in order - longer strings first to avoid false matches
        // e.g., "Certificate IV" should match before "Certificate I"
        foreach ($levels as $key => $value) {
            if (stripos($level, $key) !== false) {
                return $value;
            }
        }
        
        return 0;
    }

    /**
     * Get points for qualification level
     * Based on Australian skilled migration points test
     */
    protected function getQualificationPoints(string $level): int
    {
        // Highest level qualifications (20 points)
        if (stripos($level, 'Doctorate') !== false || 
            stripos($level, 'Doctoral') !== false ||
            stripos($level, 'PhD') !== false) {
            return 20;
        }
        
        // Bachelor/Masters/Graduate qualifications (15 points)
        // IMPORTANT: Check "Graduate Certificate" and "Graduate Diploma" BEFORE regular "Diploma"/"Certificate"
        // to avoid false matches (e.g., "Graduate Certificate" contains "Certificate")
        if (stripos($level, 'Graduate Certificate') !== false ||
            stripos($level, 'Graduate Diploma') !== false ||
            stripos($level, 'Bachelor') !== false || 
            stripos($level, 'Masters') !== false ||
            stripos($level, 'Master') !== false) {
            return 15;
        }
        
        // Diploma/Trade/Certificate III & IV level (10 points)
        // IMPORTANT: Check Certificate IV and III BEFORE Certificate I and II
        // to avoid false matches (e.g., "Certificate IV" contains "Certificate I")
        if (stripos($level, 'Certificate IV') !== false || 
            stripos($level, 'Certificate III') !== false ||
            stripos($level, 'Advanced Diploma') !== false ||
            stripos($level, 'Associate Degree') !== false ||
            stripos($level, 'Diploma') !== false || 
            stripos($level, 'Trade') !== false) {
            return 10;
        }
        
        // Certificate I and II (0 points)
        if (stripos($level, 'Certificate I') !== false || 
            stripos($level, 'Certificate II') !== false) {
            return 0;
        }
        
        return 0;
    }

    /**
     * Add individual bonus categories to breakdown
     * Only includes categories where points are claimed
     */
    protected function addBonusCategories(Admin $client, array &$breakdown): void
    {
        // Australian Study Requirement
        if ($this->hasAustralianStudy($client)) {
            $breakdown['australian_study'] = [
                'detail' => 'Australian Study',
                'points' => 5,
            ];
        }
        
        // Specialist Education (STEM Masters/PhD)
        if ($this->hasSpecialistEducation($client)) {
            $breakdown['specialist_education'] = [
                'detail' => 'Specialist Education',
                'points' => 10,
            ];
        }
        
        // Regional Study
        if ($this->hasRegionalStudy($client)) {
            $breakdown['regional_study'] = [
                'detail' => 'Regional Study',
                'points' => 5,
            ];
        }
        
        // NAATI/CCL
        if ($this->hasNAATI($client)) {
            $breakdown['naati_ccl'] = [
                'detail' => 'NAATI/CCL',
                'points' => 5,
            ];
        }
        
        // Professional Year
        if ($this->hasProfessionalYear($client)) {
            $breakdown['professional_year'] = [
                'detail' => 'Professional Year',
                'points' => 5,
            ];
        }
    }

    /**
     * Check if client has Australian study requirement
     * Uses manual entry from Additional Information section
     */
    protected function hasAustralianStudy(Admin $client): bool
    {
        // Use manual entry field (staff verifies 2+ years Australian study)
        return $client->australian_study == 1;
    }

    /**
     * Check if client has specialist education (STEM)
     * Uses manual entry from Additional Information section
     */
    protected function hasSpecialistEducation(Admin $client): bool
    {
        // Use manual entry field (staff verifies STEM Masters/PhD by research)
        return $client->specialist_education == 1;
    }

    /**
     * Check if client has regional study
     * Uses manual entry from Additional Information section
     */
    protected function hasRegionalStudy(Admin $client): bool
    {
        // Use manual entry field (staff verifies regional Australian study)
        return $client->regional_study == 1;
    }

    /**
     * Check if client has NAATI/CCL
     */
    protected function hasNAATI(Admin $client): bool
    {
        // Check naati_test field (0/1 boolean in DB)
        // CCL is typically combined with NAATI credential
        return $client->naati_test == 1;
    }

    /**
     * Check if client has Professional Year
     */
    protected function hasProfessionalYear(Admin $client): bool
    {
        // Check py_test field (0/1 boolean in DB)
        return $client->py_test == 1;
    }

    /**
     * Calculate partner points
     * Only includes partner if marital status is 'Married' or 'De Facto'
     * For subclass 189: skilled partner (10 pts) requires partner occupation on MLTSSL; else 5 pts for English only if Competent English.
     * No Competent English = 0 pts regardless.
     *
     * @param Admin $client
     * @param Carbon $referenceDate
     * @param string|null $subclass EOI subclass - 189 applies MLTSSL check for skilled partner
     */
    protected function calculatePartnerPoints(Admin $client, Carbon $referenceDate, ?string $subclass = null): array
    {
        // Check marital status - only Married/De Facto partners count for points
        if (!$client->marital_status || !in_array($client->marital_status, ['Married', 'De Facto', 'Defacto'])) {
            return [
                'detail' => 'Single (10 pts)',
                'points' => 10,
                'category' => 'single',
                'note' => 'Marital status: ' . ($client->marital_status ?: 'Not set'),
            ];
        }

        // Get partner from spouse details (if exists)
        $partner = $client->partner;
        
        if (!$partner) {
            return [
                'detail' => 'No partner information (10 pts)',
                'points' => 10,
                'category' => 'single',
            ];
        }
        
        // Partner is citizen/PR - check if columns exist (no English required for this path)
        if (($partner->is_citizen ?? 0) == 1 || ($partner->has_pr ?? 0) == 1) {
            return [
                'detail' => 'Partner is citizen/PR (10 pts)',
                'points' => 10,
                'category' => 'single_or_pr',
            ];
        }

        // Partner with skills assessment (use actual field name)
        $hasSkills = ($partner->spouse_has_skill_assessment ?? 0) == 1;
        
        // Partner English level - calculate proficiency level using EnglishProficiencyService
        $hasEnglish = false;
        if (($partner->spouse_has_english_score ?? 0) == 1) {
            $englishService = new \App\Services\EnglishProficiencyService();
            
            $scores = [
                'listening' => $partner->spouse_listening_score ?? 0,
                'reading' => $partner->spouse_reading_score ?? 0,
                'writing' => $partner->spouse_writing_score ?? 0,
                'speaking' => $partner->spouse_speaking_score ?? 0,
                'overall' => $partner->spouse_overall_score ?? 0,
            ];
            
            $proficiency = $englishService->calculateProficiency(
                $partner->spouse_test_type ?? '',
                $scores,
                $partner->spouse_test_date ?? null
            );
            
            $level = strtolower($proficiency['level'] ?? '');
            $hasEnglish = in_array($level, ['competent english', 'proficient english', 'superior english']);
        }
        
        // No Competent English = 0 points regardless of skills
        if (!$hasEnglish) {
            return [
                'detail' => 'Partner (no points – no Competent English)',
                'points' => 0,
                'category' => 'none',
            ];
        }
        
        // Get partner age if DOB exists
        $partnerAge = 99;
        if (isset($partner->dob) && $partner->dob) {
            try {
                $partnerAge = Carbon::parse($partner->dob)->diffInYears($referenceDate);
            } catch (\Exception $e) {
                $partnerAge = 99;
            }
        }
        
        // Skilled partner: skills + English + under 45. For 189, occupation must be on MLTSSL
        if ($hasSkills && $hasEnglish && $partnerAge < 45) {
            $occupationOnMltssl = true;
            if ($subclass === '189') {
                $occupationOnMltssl = $this->isPartnerOccupationOnMltssl($partner);
            }
            
            if ($occupationOnMltssl) {
                return [
                    'detail' => 'Partner with skills (10 pts)',
                    'points' => 10,
                    'category' => 'skilled_partner',
                ];
            }
            // For 189: partner has skills but occupation not on MLTSSL – fall through to English-only (5 pts)
        }

        // Partner with competent English only (5 pts) – includes 189 case where occupation not on MLTSSL
        return [
            'detail' => 'Partner with English (5 pts)',
            'points' => 5,
            'category' => 'english_partner',
        ];
    }

    /**
     * Check if partner's skills-assessed occupation is on the MLTSSL list.
     * Resolves via related_client_id -> partner's latest ClientOccupation -> AnzscoOccupation.
     *
     * @param ClientSpouseDetail $partner ClientSpouseDetail for the partner
     * @return bool True if occupation is on MLTSSL, false otherwise (or if cannot determine)
     */
    protected function isPartnerOccupationOnMltssl(ClientSpouseDetail $partner): bool
    {
        if (!$partner->related_client_id) {
            return false;
        }

        $partnerClient = $partner->relatedClient;
        if (!$partnerClient) {
            return false;
        }

        $partnerOccupation = $partnerClient->occupations()->latest('id')->first();
        if (!$partnerOccupation) {
            return false;
        }

        // Prefer anzsco_occupation_id for direct lookup
        if ($partnerOccupation->anzsco_occupation_id) {
            $anzscoOccupation = AnzscoOccupation::find($partnerOccupation->anzsco_occupation_id);
            return $anzscoOccupation && $anzscoOccupation->is_on_mltssl;
        }

        // Fallback: look up by occupation_code (ANZSCO code)
        $occupationCode = $partnerOccupation->occupation_code ? trim((string) $partnerOccupation->occupation_code) : null;
        if ($occupationCode) {
            $anzscoOccupation = AnzscoOccupation::where('anzsco_code', $occupationCode)->first();
            return $anzscoOccupation && $anzscoOccupation->is_on_mltssl;
        }

        return false;
    }

    /**
     * Calculate nomination points based on subclass
     */
    protected function calculateNominationPoints(?string $subclass): array
    {
        $points = match ($subclass) {
            '491', '491-Family' => self::NOMINATION_491,
            '190' => self::NOMINATION_190,
            default => self::NOMINATION_189,
        };

        $detail = match ($subclass) {
            '491' => 'Subclass 491 nomination (15 pts)',
            '491-Family' => 'Subclass 491-Family nomination (15 pts)',
            '190' => 'Subclass 190 nomination (5 pts)',
            '189' => 'Subclass 189 - no nomination',
            default => 'No subclass selected',
        };

        return [
            'detail' => $detail,
            'points' => $points,
            'subclass' => $subclass ?? 'N/A',
        ];
    }

    /**
     * Generate warnings for upcoming changes
     */
    protected function generateWarnings(Admin $client, Carbon $referenceDate, int $monthsAhead, array $breakdown): array
    {
        $warnings = [];
        $thresholdDate = $referenceDate->copy()->addMonths($monthsAhead);
        
        // Age bracket changes
        if ($client->dob) {
            $age = Carbon::parse($client->dob)->diffInYears($referenceDate);
            $nextBirthdayDate = Carbon::parse($client->dob)->addYears($age + 1);
            
            // Check if turning 33, 40, or 45 within threshold
            $criticalAges = [33, 40, 45];
            foreach ($criticalAges as $criticalAge) {
                $criticalDate = Carbon::parse($client->dob)->addYears($criticalAge);
                
                if ($criticalDate->between($referenceDate, $thresholdDate)) {
                    $currentPoints = $breakdown['age']['points'];
                    $futureAge = $criticalAge;
                    $futurePoints = $this->getAgePoints($futureAge);
                    
                    $warnings[] = [
                        'type' => 'age_bracket_change',
                        'date' => $criticalDate->format('Y-m-d'),
                        'message' => "Age will be {$futureAge} on {$criticalDate->format('d/m/Y')} - points will change from {$currentPoints} to {$futurePoints}",
                        'severity' => $futurePoints < $currentPoints ? 'high' : 'info',
                    ];
                }
            }
        }
        
        // English test expiry
        if (isset($breakdown['english']['expiry_date'])) {
            $expiryDate = Carbon::parse($breakdown['english']['expiry_date']);
            
            if ($expiryDate->between($referenceDate, $thresholdDate)) {
                $warnings[] = [
                    'type' => 'english_expiry',
                    'date' => $expiryDate->format('Y-m-d'),
                    'message' => "English test will expire on {$expiryDate->format('d/m/Y')} - take new test before invitation",
                    'severity' => 'high',
                ];
            }
        }
        
        // Skill assessment expiry warnings
        $this->addSkillAssessmentWarnings($warnings, $client, $referenceDate, $thresholdDate);
        
        // Work experience thresholds approaching
        $this->addEmploymentWarnings($warnings, $breakdown, $client, $referenceDate, $thresholdDate);
        
        return $warnings;
    }

    /**
     * Add employment threshold warnings
     */
    protected function addEmploymentWarnings(array &$warnings, array $breakdown, Admin $client, Carbon $referenceDate, Carbon $thresholdDate): void
    {
        // Fix: use correct breakdown keys
        $auYears = $breakdown['australian_work_experience']['years'] ?? 0;
        $osYears = $breakdown['overseas_work_experience']['years'] ?? 0;
        
        // Check AU thresholds: 1, 3, 5, 8 years
        $auThresholds = [1, 3, 5, 8];
        foreach ($auThresholds as $threshold) {
            if ($auYears < $threshold && $auYears > ($threshold - 0.5)) {
                $monthsToThreshold = ($threshold - $auYears) * 12;
                
                if ($monthsToThreshold <= 6) {
                    $targetDate = $referenceDate->copy()->addMonths((int)$monthsToThreshold);
                    
                    if ($targetDate->lte($thresholdDate)) {
                        $currentPoints = $breakdown['australian_work_experience']['points'];
                        $futurePoints = $this->getEmploymentPoints($threshold, 'australian');
                        
                        $warnings[] = [
                            'type' => 'employment_threshold',
                            'date' => $targetDate->format('Y-m-d'),
                            'message' => "Will reach {$threshold} years AU experience on {$targetDate->format('d/m/Y')} - points will increase from {$currentPoints} to {$futurePoints}",
                            'severity' => 'info',
                        ];
                    }
                }
            }
        }
    }

    /**
     * Add skill assessment expiry warnings
     */
    protected function addSkillAssessmentWarnings(array &$warnings, Admin $client, Carbon $referenceDate, Carbon $thresholdDate): void
    {
        // Load occupations/skill assessments
        $occupations = $client->occupations ?? collect();
        
        if ($occupations->isEmpty()) {
            return;
        }
        
        foreach ($occupations as $occupation) {
            // Skip if no expiry date set
            if (empty($occupation->expiry_dates)) {
                continue;
            }
            
            try {
                $expiryDate = Carbon::parse($occupation->expiry_dates);
                
                // Check if expiry is within the threshold period
                if ($expiryDate->between($referenceDate, $thresholdDate)) {
                    $occupationName = $occupation->skill_assessment ?? $occupation->nomi_occupation ?? 'Skill assessment';
                    
                    // Check how close to expiry (for severity)
                    $daysUntilExpiry = $referenceDate->diffInDays($expiryDate);
                    $severity = $daysUntilExpiry <= 60 ? 'high' : 'medium';
                    
                    $warnings[] = [
                        'type' => 'skill_assessment_expiry',
                        'date' => $expiryDate->format('Y-m-d'),
                        'message' => "{$occupationName} will expire on {$expiryDate->format('d/m/Y')} - renew before invitation",
                        'severity' => $severity,
                    ];
                }
            } catch (\Exception $e) {
                // Skip invalid dates
                continue;
            }
        }
    }

    /**
     * Get age points for a specific age
     */
    protected function getAgePoints(int $age): int
    {
        // Check age brackets (upper bound is exclusive)
        foreach (self::AGE_BRACKETS as [$min, $max, $points]) {
            if ($age >= $min && $age < $max) {
                return $points;
            }
        }
        
        return 0;
    }

    /**
     * Get invitation date (or default to today)
     */
    protected function getInvitationDate(Admin $client): Carbon
    {
        // Check if client has upcoming invitation date from EOI records
        $eoiReference = $client->eoiReferences()->latest()->first();
        
        if ($eoiReference && $eoiReference->eoi_invitation_date) {
            return Carbon::parse($eoiReference->eoi_invitation_date);
        }
        
        return Carbon::today();
    }

    /**
     * Determine if an English test record has all component scores populated
     */
    protected function hasCompleteEnglishScores($test): bool
    {
        if (!$test) {
            return false;
        }

        $fields = ['listening', 'reading', 'writing', 'speaking', 'overall_score'];

        foreach ($fields as $field) {
            $value = $field === 'overall_score' ? $test->overall_score : $test->{$field};
            if ($value === null || $value === '') {
                return false;
            }
        }

        return true;
    }
}

