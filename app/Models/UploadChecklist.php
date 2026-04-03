<?php
namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Kyslik\ColumnSortable\Sortable;
use Illuminate\Foundation\Auth\User as Authenticatable;

class UploadChecklist extends Authenticatable
{
    use Notifiable;
	use Sortable;

	protected $table = 'matter_checklists';

	protected $fillable = [
        'id', 'created_at', 'updated_at'
    ];
	
	public $sortable = ['id'];
	
}