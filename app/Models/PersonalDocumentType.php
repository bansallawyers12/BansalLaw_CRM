<?php
namespace App\Models;

use Illuminate\Notifications\Notifiable;
use App\Traits\SortableTrait;
use Illuminate\Foundation\Auth\User as Authenticatable;

class PersonalDocumentType extends Authenticatable {
    use Notifiable;
	use SortableTrait;

	protected $fillable = ['id', 'title', 'status','client_id','created_at', 'updated_at'];

	public $sortable = ['id', 'created_at', 'updated_at'];
}
