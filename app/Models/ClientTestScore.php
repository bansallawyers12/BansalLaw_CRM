<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientTestScore extends Model
{
    protected $table = 'client_testscore';

    protected $fillable = [
        'admin_id',
        'client_id',
        'test_type',     // The type of test, e.g., IELTS, TOEFL, etc.
        'listening',     // Score for Listening
        'reading',       // Score for Reading
        'writing',       // Score for Writing
        'speaking',      // Score for Speaking
        'overall_score', // Overall Score of the test
        'proficiency_level', // Calculated English proficiency level (e.g., Competent English, Proficient English, Superior English)
        'proficiency_points', // Points awarded for this proficiency level (0, 10, or 20)
        'test_date',     // The date when the test was taken
        'relevant_test',
        'test_reference_no'
    ];

    // You can add additional relationships or methods here if necessary
}

