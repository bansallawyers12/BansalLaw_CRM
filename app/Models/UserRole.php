<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use App\Traits\SortableTrait;

class UserRole extends Authenticatable
{
    use Notifiable;
	use SortableTrait;

	protected $fillable = [
        'id', 'name', 'description', 'module_access', 'is_active', 'created_at', 'updated_at'
    ];

	protected $casts = [
		'is_active' => 'boolean',
	];
	
	public $sortable = ['id', 'name'];

	/**
	 * Roles with a non-empty display name, A–Z. Pass $alwaysIncludeRoleId on edit forms so the
	 * current assignment remains visible even if the row is malformed (legacy data).
	 */
	public static function orderedForSelect(?int $alwaysIncludeRoleId = null): Collection
	{
		$hasActiveFlag = Schema::hasColumn('user_roles', 'is_active');

		return static::query()
			->where(function (Builder $q) use ($alwaysIncludeRoleId, $hasActiveFlag) {
				$q->where(function (Builder $visible) use ($hasActiveFlag) {
					$visible->whereRaw("TRIM(COALESCE(name, '')) <> ''");
					if ($hasActiveFlag) {
						$visible->where(function (Builder $active) {
							$active->where('is_active', true)->orWhereNull('is_active');
						});
					}
				});
				if ($alwaysIncludeRoleId !== null && $alwaysIncludeRoleId > 0) {
					$q->orWhere('id', $alwaysIncludeRoleId);
				}
			})
			->orderBy('name')
			->orderBy('id')
			->get();
	}
}