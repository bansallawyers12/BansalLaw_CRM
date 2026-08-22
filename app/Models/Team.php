<?php
namespace App\Models;

use Illuminate\Notifications\Notifiable;
use App\Traits\SortableTrait;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Team extends Authenticatable
{ 
    use Notifiable;
	use SortableTrait;  
	
    /**
     * The attributes that are mass assignable.
     *
     * @var array  
     */
	
	
	protected $fillable = [
        'id', 'name', 'color', 'created_at', 'updated_at'
    ];
   
	public $sortable = ['id', 'created_at'];
	
}
