<?php
namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Kyslik\ColumnSortable\Sortable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Team extends Authenticatable
{ 
    use Notifiable;
	use Sortable;  
	
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
