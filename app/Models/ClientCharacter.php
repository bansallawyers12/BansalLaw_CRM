<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientCharacter extends Model
{
    protected $table = 'client_characters'; // The name of the table

    protected $fillable = [
        'client_id',
        'admin_id',
        'type_of_character',
        'character_detail',
        'character_date'
    ];
}


