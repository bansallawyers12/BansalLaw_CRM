<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientSpouseDetail extends Model
{
    /**
     * Get the related partner client (when related_client_id is set)
     */
    public function relatedClient()
    {
        return $this->belongsTo(Admin::class, 'related_client_id');
    }
    protected $table = 'client_spouse_details';

    protected $fillable = [
        'client_id',
        'admin_id',
        'related_client_id',
        'spouse_has_english_score',
        'spouse_test_type',
        'spouse_listening_score',
        'spouse_reading_score',
        'spouse_writing_score',
        'spouse_speaking_score',
        'spouse_overall_score',
        'spouse_test_date',

        'spouse_has_skill_assessment',
        'spouse_skill_assessment_status',
        'spouse_nomi_occupation',
        'spouse_assessment_date',
        
        // Points calculation fields
        'is_citizen',
        'has_pr',
        'dob'
    ];
}
