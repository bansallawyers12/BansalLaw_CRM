<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffAccessProfile extends Model
{
    public const MODE_FULL = 'full';

    public const MODE_RESTRICTED = 'restricted';

    protected $table = 'staff_access_profiles';

    protected $fillable = [
        'staff_id',
        'access_mode',
        'dashboard_widgets',
        'nav_modules',
        'admin_console_modules',
        'can_view_own_profile',
        'can_view_dashboard',
        'updated_by_staff_id',
    ];

    protected $casts = [
        'dashboard_widgets' => 'array',
        'nav_modules' => 'array',
        'admin_console_modules' => 'array',
        'can_view_own_profile' => 'boolean',
        'can_view_dashboard' => 'boolean',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'updated_by_staff_id');
    }

    public function isRestricted(): bool
    {
        return $this->access_mode === self::MODE_RESTRICTED;
    }

    /**
     * @return list<string>
     */
    public function allowedDashboardWidgets(): array
    {
        return $this->stringListFromJson($this->dashboard_widgets);
    }

    /**
     * Top menu bar modules granted to a restricted staff member.
     * Supports legacy list ["clients","leads"] and map {"clients":["view","edit"]}.
     *
     * @return list<string>
     */
    public function allowedNavModules(): array
    {
        return array_keys($this->navModuleActionsMap());
    }

    /**
     * Actions granted for a top-menu module (view / add / edit / delete).
     *
     * @return list<string>
     */
    public function allowedNavActions(string $moduleKey): array
    {
        return $this->navModuleActionsMap()[$moduleKey] ?? [];
    }

    public function hasNavAction(string $moduleKey, string $action): bool
    {
        return in_array($action, $this->allowedNavActions($moduleKey), true);
    }

    /**
     * @return array<string, list<string>>
     */
    public function navModuleActionsMap(): array
    {
        $raw = $this->nav_modules;
        if (! is_array($raw) || $raw === []) {
            return [];
        }

        // Legacy: ["clients", "leads"] → view only.
        if (array_is_list($raw)) {
            $map = [];
            foreach ($raw as $key) {
                $key = (string) $key;
                if ($key !== '') {
                    $map[$key] = ['view'];
                }
            }

            return $map;
        }

        $map = [];
        foreach ($raw as $key => $actions) {
            $key = (string) $key;
            if ($key === '') {
                continue;
            }
            if (is_bool($actions)) {
                if ($actions) {
                    $map[$key] = ['view'];
                }
                continue;
            }
            if (! is_array($actions)) {
                continue;
            }
            $normalized = array_values(array_unique(array_filter(array_map('strval', $actions))));
            if ($normalized === []) {
                continue;
            }
            if (! in_array('view', $normalized, true)) {
                array_unshift($normalized, 'view');
                $normalized = array_values(array_unique($normalized));
            }
            $map[$key] = $normalized;
        }

        return $map;
    }

    /**
     * Admin Console sidebar modules granted to a restricted staff member.
     *
     * @return list<string>
     */
    public function allowedAdminConsoleModules(): array
    {
        return $this->stringListFromJson($this->admin_console_modules);
    }

    /**
     * @param  mixed  $value
     * @return list<string>
     */
    protected function stringListFromJson($value): array
    {
        return is_array($value) ? array_values(array_filter(array_map('strval', $value))) : [];
    }
}
