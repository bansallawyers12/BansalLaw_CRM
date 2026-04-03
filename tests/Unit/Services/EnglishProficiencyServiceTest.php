<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\EnglishProficiencyService;

class EnglishProficiencyServiceTest extends TestCase
{
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EnglishProficiencyService();
    }

    public function test_ielts_competent_english()
    {
        $scores = [
            'listening' => 6.5,
            'reading' => 6.5,
            'writing' => 6.5,
            'speaking' => 6.5,
            'overall' => 6.5
        ];

        $result = $this->service->calculateProficiency('IELTS', $scores);

        $this->assertEquals('Competent English', $result['level']);
        $this->assertEquals(0, $result['points']);
    }

    public function test_ielts_proficient_english()
    {
        $scores = [
            'listening' => 7.5,
            'reading' => 7.5,
            'writing' => 7.5,
            'speaking' => 7.5,
            'overall' => 7.5
        ];

        $result = $this->service->calculateProficiency('IELTS', $scores);

        $this->assertEquals('Proficient English', $result['level']);
        $this->assertEquals(10, $result['points']);
    }

    public function test_ielts_superior_english()
    {
        $scores = [
            'listening' => 8.5,
            'reading' => 8.5,
            'writing' => 8.5,
            'speaking' => 8.5,
            'overall' => 8.5
        ];

        $result = $this->service->calculateProficiency('IELTS', $scores);

        $this->assertEquals('Superior English', $result['level']);
        $this->assertEquals(20, $result['points']);
    }

    public function test_pte_competent_english()
    {
        $scores = [
            'listening' => 50,
            'reading' => 50,
            'writing' => 50,
            'speaking' => 50,
            'overall' => 50
        ];

        $result = $this->service->calculateProficiency('PTE', $scores);

        $this->assertEquals('Competent English', $result['level']);
        $this->assertEquals(0, $result['points']);
    }

    public function test_mixed_scores_should_be_competent()
    {
        // This test case represents the scenario from the user's issue
        // where individual components don't reach superior level but overall is high
        $scores = [
            'listening' => 7.0,
            'reading' => 7.0,
            'writing' => 6.5,
            'speaking' => 7.0,
            'overall' => 8.0
        ];

        $result = $this->service->calculateProficiency('IELTS', $scores);

        // Should be Competent because not all individual components reach 7.0
        $this->assertEquals('Competent English', $result['level']);
        $this->assertEquals(0, $result['points']);
    }
}
