<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Kyslik\ColumnSortable\Sortable;

class CheckinLog extends Model
{
	use Sortable;

	protected $fillable = [
        'id',
        'client_id',
        'walk_in_phone',
        'walk_in_email',
        'user_id',
        'visit_purpose',
        'office',
        'contact_type',
        'status',
        'date',
        'sesion_start',
        'sesion_end',
        'wait_time',
        'attend_time',
        'wait_type',
        'created_at',
        'updated_at',
    ];
	
	public $sortable = ['id','created_at', 'updated_at'];

	/**
	 * Linked client or lead on admins. Uses a direct admins query so Contact Name still resolves when
	 * contact_type/Lead global scope would miss the row (e.g. client_id points at type client, or soft-deleted lead).
	 */
	public function resolveCrmContact(): ?Admin
	{
		if ($this->contact_type === 'Walk-in' || empty($this->client_id)) {
			return null;
		}

		return Admin::whereIn('type', ['client', 'lead'])->where('id', $this->client_id)->first();
	}

	/**
	 * Human-readable contact line for notifications and UI (walk-in includes phone when set).
	 *
	 * @param  Admin|null  $preloadedContact  Optional row from a batch keyed by client_id (avoids N+1).
	 */
	public function contactDisplayLabel(?Admin $preloadedContact = null): string
	{
		if ($this->contact_type === 'Walk-in' || empty($this->client_id)) {
			$label = 'Walk-in';
			if (! empty($this->walk_in_phone)) {
				$label .= ' (' . $this->walk_in_phone . ')';
			}
			return $label;
		}

		$contact = $preloadedContact ?? $this->resolveCrmContact();

		return self::labelForCrmContact($contact);
	}

	public static function labelForCrmContact(?Admin $contact): string
	{
		if (! $contact) {
			return 'Unknown Client';
		}
		$name = trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? ''));
		if ($name !== '') {
			return $name;
		}

		return $contact->email ?? $contact->phone ?? ('Contact #' . $contact->id);
	}

	public function scopeForOfficeVisitViewer(Builder $query): Builder
	{
		return $query;
	}

	/**
	 * Tab badge counts aligned with list queries (optional branch filter only).
	 *
	 * @return array{waiting: int, attending: int, completed: int}
	 */
	public static function officeVisitTabCounts(?int $officeFilter = null): array
	{
		$out = [];
		foreach ([0 => 'waiting', 2 => 'attending', 1 => 'completed'] as $status => $key) {
			$q = static::query()->where('status', $status)->forOfficeVisitViewer();
			if ($officeFilter !== null && $officeFilter > 0) {
				$q->where('office', $officeFilter);
			}
			$out[$key] = $q->count();
		}

		return $out;
	}

	public static function inPersonWaitingCountForViewer(?int $officeFilter = null): int
	{
		$q = static::query()->where('status', 0)->forOfficeVisitViewer();
		if ($officeFilter !== null && $officeFilter > 0) {
			$q->where('office', $officeFilter);
		}

		return $q->count();
	}
	
	/**
     * Get the client associated with this checkin log
     */
    public function client()
    {
        if ($this->contact_type == 'Lead') {
            return $this->belongsTo(Lead::class, 'client_id');
        }

        return $this->belongsTo(Admin::class, 'client_id')->whereIn('type', ['client', 'lead']);
    }
    
    /**
     * Get the assignee associated with this checkin log
     */
    public function assignee()
    {
        return $this->belongsTo(Staff::class, 'user_id');
    }

    /**
     * Get the office (branch) associated with this checkin log
     */
    public function office()
    {
        return $this->belongsTo(Branch::class, 'office');
    }

    /**
     * Get the check-in history records for this checkin log
     */
    public function histories()
    {
        return $this->hasMany(CheckinHistory::class, 'checkin_id');
    }
}