<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class StaffRecordGrant extends Model
{
    public const TYPE_CLIENT = 'client';

    public const TYPE_LEAD = 'lead';

    public const TYPE_MATTER = 'matter';

    /** Closed matter grant (client_matters.id); enforced like matter access. */
    public const TYPE_CLOSED_MATTER = 'closed_matter';

    public const SCOPE_VIEW = 'view';

    /**
     * Resource types that grant access to a client_matters row.
     *
     * @return list<string>
     */
    public static function matterResourceTypes(): array
    {
        return [self::TYPE_MATTER, self::TYPE_CLOSED_MATTER];
    }

    public static function isMatterResourceType(string $type): bool
    {
        return in_array($type, self::matterResourceTypes(), true);
    }

    /**
     * @return list<string>
     */
    public static function grantableResourceTypes(): array
    {
        return [
            self::TYPE_CLIENT,
            self::TYPE_LEAD,
            self::TYPE_MATTER,
            self::TYPE_CLOSED_MATTER,
        ];
    }

    public const SCOPE_EMAILS = 'emails';

    public const SCOPE_DOCUMENTS = 'documents';

    public const SCOPE_NOTES = 'notes';

    protected $table = 'staff_record_grants';

    protected $fillable = [
        'staff_id',
        'resource_type',
        'resource_id',
        'scopes',
        'granted_by_staff_id',
        'starts_at',
        'ends_at',
        'revoked_at',
        'notes',
    ];

    protected $casts = [
        'scopes' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'granted_by_staff_id');
    }

    public function isActive(?Carbon $at = null): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        $at = $at ?? now();

        if ($this->starts_at !== null && $at->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at !== null && $at->gt($this->ends_at)) {
            return false;
        }

        return true;
    }

    public function hasScope(string $scope): bool
    {
        $scopes = is_array($this->scopes) ? $this->scopes : [];

        return in_array($scope, $scopes, true);
    }

    /**
     * Active (non-revoked, within optional window) grants for a staff member.
     */
    public function scopeActive(Builder $query, ?Carbon $at = null): Builder
    {
        $at = $at ?? now();

        return $query
            ->whereNull('revoked_at')
            ->where(function (Builder $q) use ($at) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $at);
            })
            ->where(function (Builder $q) use ($at) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $at);
            });
    }
}
