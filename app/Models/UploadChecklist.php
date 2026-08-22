<?php
namespace App\Models;

use Illuminate\Notifications\Notifiable;
use App\Traits\SortableTrait;
use Illuminate\Foundation\Auth\User as Authenticatable;

class UploadChecklist extends Authenticatable
{
    use Notifiable;
	use SortableTrait;

	protected $table = 'matter_checklists';

	protected $fillable = [
        'id',
        'matter_id',
        'name',
        'file',
        'created_at',
        'updated_at',
    ];
	
	public $sortable = ['id'];
	
}