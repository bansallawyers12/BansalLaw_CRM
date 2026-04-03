<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientAddress extends Model
{
    protected $table = 'client_addresses';

    protected $fillable = [
        'admin_id',
		'client_id', // New field added
        'address',           // Keep for backward compatibility
        'address_line_1',    // NEW
        'address_line_2',    // NEW
        'suburb',
        'state',
        'country',           // NEW
        'zip',               // Existing
        'regional_code',     // Existing
        'start_date',        // Existing
        'end_date',          // Existing
        'is_current'         // Existing
    ];
}


