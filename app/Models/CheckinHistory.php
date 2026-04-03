<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Kyslik\ColumnSortable\Sortable;

class CheckinHistory extends Model
{
	use Sortable;

	protected $fillable = [
        'id', 'subject', 'created_by', 'checkin_id', 'description', 'created_at', 'updated_at'
    ];
	
	public $sortable = ['id', 'created_at', 'updated_at'];

	/**
	 * Get the checkin log this history belongs to
	 */
	public function checkinLog()
	{
		return $this->belongsTo(CheckinLog::class, 'checkin_id');
	}

	/**
	 * Get the staff who created this history record
	 */
	public function creator()
	{
		return $this->belongsTo(Staff::class, 'created_by');
	}
}