<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientTravelInformation extends Model
{
    protected $table = 'client_travel_informations'; // The name of the table

    protected $fillable = [
        'client_id',
        'admin_id',
        'travel_country_visited',
        'travel_arrival_date',
        'travel_departure_date',
        'travel_purpose'
    ];

    /**
     * Accessor to bridge the field name mismatch
     * Views expect 'country_visited' but database has 'travel_country_visited'
     */
    public function getCountryVisitedAttribute($value)
    {
        return $this->travel_country_visited;
    }

    /**
     * Accessor to bridge the field name mismatch
     * Views expect 'arrival_date' but database has 'travel_arrival_date'
     */
    public function getArrivalDateAttribute($value)
    {
        return $this->travel_arrival_date;
    }

    /**
     * Accessor to bridge the field name mismatch
     * Views expect 'departure_date' but database has 'travel_departure_date'
     */
    public function getDepartureDateAttribute($value)
    {
        return $this->travel_departure_date;
    }
}


