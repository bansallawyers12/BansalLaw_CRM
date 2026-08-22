<?php
namespace App\Models;

use App\Support\WorkflowStageFreeze;
use Illuminate\Database\Eloquent\Model;
use App\Traits\SortableTrait;

class WorkflowStage extends Model
{
	use SortableTrait;
	
	protected $table = 'workflow_stages';

	public $sortable = ['id', 'name', 'sort_order', 'created_at', 'updated_at'];
	
	protected $fillable = [
        'id', 'name', 'workflow_id', 'sort_order', 'created_at', 'updated_at'
    ];
  
	protected static function booted()
	{
		static::saving(function (WorkflowStage $stage) {
			if (empty($stage->workflow_id)) {
				$general = Workflow::firstOrCreate(
					['name' => 'General'],
					['description' => 'Default General Workflow', 'status' => 1]
				);
				$stage->workflow_id = $general->id;
			}
		});
	}

	/**
	 * Get the workflow this stage belongs to.
	 */
	public function workflow()
	{
		return $this->belongsTo(Workflow::class, 'workflow_id');
	}

	/**
	 * Whether this stage is locked (cannot rename/delete in Admin Console).
	 */
	public function isFrozen(): bool
	{
		return WorkflowStageFreeze::isFrozen($this->name);
	}
}
