<?php
namespace App\Models;

use Illuminate\Notifications\Notifiable;
use App\Traits\SortableTrait;
use Illuminate\Foundation\Auth\User as Authenticatable;

class DocumentChecklist extends Authenticatable
{
    use Notifiable;
	use SortableTrait;

	protected $table = 'document_checklists';

	protected $fillable = ['id', 'name','doc_type','status','created_at', 'updated_at'];

	public $sortable = ['id','created_at', 'updated_at'];

}
