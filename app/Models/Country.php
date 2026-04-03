<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $table = 'countries';

	protected $fillable = [
        'id', 'sortname', 'name', 'phonecode', 'status', 'created_at', 'updated_at'
    ];
}