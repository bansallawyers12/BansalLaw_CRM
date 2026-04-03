<?php
namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Kyslik\ColumnSortable\Sortable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class VisaDocumentType extends Authenticatable {
    use Notifiable;
	use Sortable;

	protected $fillable = ['id', 'title', 'status','client_id','client_matter_id','created_at', 'updated_at'];

	public $sortable = ['id', 'created_at', 'updated_at'];
}
