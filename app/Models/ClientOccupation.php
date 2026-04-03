<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientOccupation extends Model
{
    protected $table = 'client_occupations'; // Ensure this matches your table name

    protected $fillable = [
        'client_id',
        'admin_id',
        'skill_assessment',
        'nomi_occupation',
        'occupation_code',
        'list',
        'visa_subclass',
        'dates',
        'expiry_dates',
        'relevant_occupation',
        'occ_reference_no',
        'anzsco_occupation_id'
    ];

    /**
     * Get the ANZSCO occupation associated with this client occupation
     */
    public function anzscoOccupation()
    {
        return $this->belongsTo(\App\Models\AnzscoOccupation::class, 'anzsco_occupation_id');
    }
}
