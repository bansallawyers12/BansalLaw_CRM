<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Kyslik\ColumnSortable\Sortable;

class Workflow extends Model
{
	use Sortable;
	
	protected $table = 'workflows';
	
	protected $fillable = [
        'id', 'name', 'matter_id', 'status', 'created_at', 'updated_at'
    ];
  
	public $sortable = ['id', 'name', 'created_at', 'updated_at'];

	/**
	 * Get the matter type this workflow is linked to (nullable for General workflow).
	 */
	public function matter()
	{
		return $this->belongsTo(Matter::class, 'matter_id');
	}

	/**
	 * Get the stages for this workflow.
	 */
	public function stages()
	{
		return $this->hasMany(WorkflowStage::class, 'workflow_id')->orderByRaw('COALESCE(sort_order, id) ASC');
	}
}
