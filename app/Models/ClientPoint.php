<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientPoint extends Model
{
    protected $table = 'client_points'; // Ensure this matches your table name

    protected $fillable = [
        'client_id',
        'admin_id',
        'item_type', // This field stores 'age' or 'english'
		'test_name',
        'value', // Age or English proficiency result
        'calculate_point', // Points for age (or can be null for English)
    ];
}
