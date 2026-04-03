<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientVisaCountry extends Model
{
    protected $table = 'client_visa_countries'; // Table name

    protected $fillable = [
        'client_id',
        'admin_id',
        'visa_type',         // Type of visa (Matter ID)
        'visa_description',
        'visa_expiry_date',  // Expiry date of the visa
        'visa_grant_date',
    ];

    /**
     * Get the matter (visa type) for this visa
     * Enables eager loading to prevent N+1 queries
     */
    public function matter()
    {
        return $this->belongsTo(Matter::class, 'visa_type', 'id');
    }

    /**
     * Get the client (admin) for this visa
     */
    public function client()
    {
        return $this->belongsTo(Admin::class, 'client_id', 'id');
    }
}
