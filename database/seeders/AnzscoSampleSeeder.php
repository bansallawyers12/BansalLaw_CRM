<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AnzscoOccupation;

class AnzscoSampleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * This seeder provides sample ANZSCO occupations for testing.
     * Remove or modify before production use.
     */
    public function run(): void
    {
        $sampleOccupations = [
            [
                'anzsco_code' => '261313',
                'occupation_title' => 'Software Engineer',
                'skill_level' => 1,
                'is_on_mltssl' => true,
                'is_on_stsol' => false,
                'is_on_rol' => true,
                'is_on_csol' => false,
                'assessing_authority' => 'ACS',
                'assessment_validity_years' => 3,
                'additional_info' => 'ICT Professional - Requires degree in IT or related field',
                'alternate_titles' => 'Developer, Programmer, Software Developer',
                'is_active' => true
            ],
            [
                'anzsco_code' => '351311',
                'occupation_title' => 'Chef',
                'skill_level' => 3,
                'is_on_mltssl' => true,
                'is_on_stsol' => true,
                'is_on_rol' => true,
                'is_on_csol' => false,
                'assessing_authority' => 'TRA',
                'assessment_validity_years' => 3,
                'additional_info' => 'Trade qualification required',
                'alternate_titles' => 'Cook, Head Chef, Executive Chef',
                'is_active' => true
            ],
            [
                'anzsco_code' => '221111',
                'occupation_title' => 'Accountant (General)',
                'skill_level' => 1,
                'is_on_mltssl' => true,
                'is_on_stsol' => false,
                'is_on_rol' => true,
                'is_on_csol' => false,
                'assessing_authority' => 'CPA Australia',
                'assessment_validity_years' => 3,
                'additional_info' => 'Accounting degree and professional membership required',
                'alternate_titles' => 'Accountant',
                'is_active' => true
            ],
            [
                'anzsco_code' => '254111',
                'occupation_title' => 'Midwife',
                'skill_level' => 1,
                'is_on_mltssl' => true,
                'is_on_stsol' => false,
                'is_on_rol' => true,
                'is_on_csol' => false,
                'assessing_authority' => 'ANMAC',
                'assessment_validity_years' => 3,
                'additional_info' => 'Registered nurse with midwifery qualification',
                'alternate_titles' => 'Registered Midwife',
                'is_active' => true
            ],
            [
                'anzsco_code' => '233211',
                'occupation_title' => 'Civil Engineer',
                'skill_level' => 1,
                'is_on_mltssl' => true,
                'is_on_stsol' => false,
                'is_on_rol' => true,
                'is_on_csol' => false,
                'assessing_authority' => 'Engineers Australia',
                'assessment_validity_years' => 3,
                'additional_info' => 'Engineering degree required',
                'alternate_titles' => 'Civil Engineering Professional',
                'is_active' => true
            ],
            [
                'anzsco_code' => '321211',
                'occupation_title' => 'Motor Mechanic (General)',
                'skill_level' => 3,
                'is_on_mltssl' => true,
                'is_on_stsol' => false,
                'is_on_rol' => true,
                'is_on_csol' => false,
                'assessing_authority' => 'TRA',
                'assessment_validity_years' => 3,
                'additional_info' => 'Trade qualification in automotive mechanics',
                'alternate_titles' => 'Automotive Mechanic, Car Mechanic',
                'is_active' => true
            ],
            [
                'anzsco_code' => '241111',
                'occupation_title' => 'Early Childhood (Pre-primary School) Teacher',
                'skill_level' => 1,
                'is_on_mltssl' => true,
                'is_on_stsol' => false,
                'is_on_rol' => true,
                'is_on_csol' => false,
                'assessing_authority' => 'AITSL',
                'assessment_validity_years' => 3,
                'additional_info' => 'Teaching qualification required',
                'alternate_titles' => 'Preschool Teacher, Kindergarten Teacher',
                'is_active' => true
            ],
            [
                'anzsco_code' => '232111',
                'occupation_title' => 'Architect',
                'skill_level' => 1,
                'is_on_mltssl' => true,
                'is_on_stsol' => false,
                'is_on_rol' => true,
                'is_on_csol' => false,
                'assessing_authority' => 'AACA',
                'assessment_validity_years' => 3,
                'additional_info' => 'Architecture degree and registration',
                'alternate_titles' => 'Building Architect, Design Architect',
                'is_active' => true
            ],
            [
                'anzsco_code' => '411411',
                'occupation_title' => 'Enrolled Nurse',
                'skill_level' => 2,
                'is_on_mltssl' => false,
                'is_on_stsol' => true,
                'is_on_rol' => true,
                'is_on_csol' => false,
                'assessing_authority' => 'ANMAC',
                'assessment_validity_years' => 3,
                'additional_info' => 'Enrolled nursing qualification',
                'alternate_titles' => 'EN, Enrolled Nursing Professional',
                'is_active' => true
            ],
            [
                'anzsco_code' => '141111',
                'occupation_title' => 'Cafe or Restaurant Manager',
                'skill_level' => 2,
                'is_on_mltssl' => false,
                'is_on_stsol' => true,
                'is_on_rol' => true,
                'is_on_csol' => false,
                'assessing_authority' => 'VETASSESS',
                'assessment_validity_years' => 3,
                'additional_info' => 'Hospitality management experience required',
                'alternate_titles' => 'Restaurant Manager, Cafe Manager',
                'is_active' => true
            ]
        ];

        foreach ($sampleOccupations as $occupation) {
            AnzscoOccupation::updateOrCreate(
                ['anzsco_code' => $occupation['anzsco_code']],
                $occupation
            );
        }

        $this->command->info('Sample ANZSCO occupations seeded successfully!');
        $this->command->info('Total occupations: ' . count($sampleOccupations));
    }
}

