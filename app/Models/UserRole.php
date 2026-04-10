<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Kyslik\ColumnSortable\Sortable;

class UserRole extends Authenticatable
{
    use Notifiable;
	use Sortable;

	protected $fillable = [
        'id', 'name', 'description', 'module_access', 'created_at', 'updated_at'
    ];
	
	public $sortable = ['id', 'name'];

	/**
	 * Roles with a non-empty display name, A–Z. Pass $alwaysIncludeRoleId on edit forms so the
	 * current assignment remains visible even if the row is malformed (legacy data).
	 */
	public static function orderedForSelect(?int $alwaysIncludeRoleId = null): Collection
	{
		return static::query()
			->where(function (Builder $q) use ($alwaysIncludeRoleId) {
				$q->whereRaw("TRIM(COALESCE(name, '')) <> ''");
				if ($alwaysIncludeRoleId !== null && $alwaysIncludeRoleId > 0) {
					$q->orWhere('id', $alwaysIncludeRoleId);
				}
			})
			->orderBy('name')
			->orderBy('id')
			->get();
	}
}