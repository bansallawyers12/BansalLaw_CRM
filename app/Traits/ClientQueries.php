<?php

namespace App\Traits;

use App\Models\Admin;
use App\Support\CrmListingTextSearch;
use App\Support\StaffClientVisibility;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

trait ClientQueries
{
    /**
     * Get base query for clients
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function getBaseClientQuery()
    {
        $query = Admin::where('is_archived', '=', '0')
            ->where('type', '=', 'client')
            ->whereNull('is_deleted');

        StaffClientVisibility::restrictAdminEloquentQuery($query);

        return $query;
    }

    /**
     * Non-archived clients and leads (Master List).
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function getBaseMasterListQuery()
    {
        $query = Admin::where('is_archived', '=', '0')
            ->whereIn('type', ['client', 'lead'])
            ->whereNull('is_deleted');

        StaffClientVisibility::restrictAdminEloquentQuery($query);

        return $query;
    }

    /**
     * Get empty client query (for no access scenarios)
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function getEmptyClientQuery()
    {
        return Admin::whereRaw('1 = 0');
    }

    /**
     * Apply search filters to client query
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function applyClientFilters($query, $request)
    {
        if ($request->has('client_id')) {
            $client_id = $request->input('client_id');
            if (trim($client_id) != '') {
                $query->where('client_id', '=', $client_id);
            }
        }

        if ($request->has('type')) {
            $type = $request->input('type');
            if (trim($type) != '') {
                $query->where('type', 'LIKE', $type);
            }
        }

        if ($request->has('name')) {
            $name = trim($request->input('name'));
            if ($name != '') {
                CrmListingTextSearch::applyToAdminsTableQuery($query, $name);
            }
        }

        if ($request->has('email')) {
            $email = $request->input('email');
            if (trim($email) != '') {
                // For universal email (demo@gmail.com), also search for timestamped versions
                if ($email === 'demo@gmail.com') {
                    $emailLower = strtolower($email);
                    $query->where(function($q) use ($email, $emailLower) {
                        $q->whereRaw('LOWER(email) = ?', [$emailLower])
                          ->orWhereRaw('LOWER(email) LIKE ?', ['demo_%@gmail.com']);
                    });
                } else {
                    $query->where('email', $email);
                }
            }
        }

        if ($request->has('phone')) {
            $phone = trim($request->input('phone'));
            if ($phone != '') {
                // For universal phone (4444444444), also search for timestamped versions
                if ($phone === '4444444444') {
                    $query->where(function ($q) use ($phone) {
                        $q->where(function($phoneQuery) use ($phone) {
                            $phoneQuery->where('phone', $phone)
                                      ->orWhere('phone', 'LIKE', $phone . '_%');
                        })
                        ->orWhere('country_code', 'LIKE', '%' . $phone . '%')
                        ->orWhereRaw("(COALESCE(country_code, '') || COALESCE(phone, '')) LIKE ?", ["%{$phone}%"]);
                    });
                } else {
                    $query->where(function ($q) use ($phone) {
                        $q->where('phone', 'LIKE', '%' . $phone . '%')
                          ->orWhere('country_code', 'LIKE', '%' . $phone . '%')
                          ->orWhereRaw("(COALESCE(country_code, '') || COALESCE(phone, '')) LIKE ?", ["%{$phone}%"]);
                    });
                }
            }
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // rating column dropped Phase 4 - filter removed

        if (
            $request->filled('quick_date_range') ||
            $request->filled('from_date') ||
            $request->filled('to_date')
        ) {
            $dateColumn = $this->resolveClientDateColumn($request->input('date_filter_field'));
            [$start, $end] = $this->resolveClientDateRange($request);

            if ($start && $end) {
                $query->whereBetween($dateColumn, [$start, $end]);
            }
        }

        return $query;
    }

    /**
     * Active client_matters joined to admins — one row per matter (Clients tab).
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function getClientsTabMatterListingQuery()
    {
        $closedStages = ['file closed', 'withdrawn', 'refund', 'discontinued'];

        $query = DB::table('client_matters as cm')
            ->join('admins as ad', 'cm.client_id', '=', 'ad.id')
            ->leftJoin('workflow_stages as ws', 'cm.workflow_stage_id', '=', 'ws.id')
            ->select(
                'ad.*',
                'cm.id as listing_client_matter_id',
                'cm.client_unique_matter_no as listing_matter_ref',
                'cm.updated_at as matter_row_updated_at'
            )
            ->where('ad.is_archived', '=', '0')
            ->where('ad.type', '=', 'client')
            ->whereNull('ad.is_deleted')
            ->where('cm.matter_status', '=', '1')
            ->whereNotNull('cm.client_unique_matter_no')
            ->where('cm.client_unique_matter_no', '!=', '')
            ->where(function ($q) use ($closedStages) {
                $q->whereNull('ws.name')
                    ->orWhereRaw(
                        'LOWER(TRIM(ws.name)) NOT IN (' . implode(',', array_fill(0, count($closedStages), '?')) . ')',
                        $closedStages
                    );
            });

        StaffClientVisibility::applyExcludeSuperAdminOnlyLockedClientsOnAdminJoin($query, 'ad');
        StaffClientVisibility::restrictMatterListToAllocatedClients($query, 'cm', 'ad');

        return $query;
    }

    /**
     * Filters for Clients tab matter listing (admins + client_matters join).
     *
     * @param \Illuminate\Database\Query\Builder $query
     * @return \Illuminate\Database\Query\Builder
     */
    protected function applyClientMatterListingFilters($query, $request)
    {
        if ($request->has('client_id')) {
            $client_id = $request->input('client_id');
            if (trim((string) $client_id) !== '') {
                $query->where('ad.client_id', '=', $client_id);
            }
        }

        if ($request->has('name')) {
            $name = trim((string) $request->input('name'));
            if ($name !== '') {
                CrmListingTextSearch::applyToClientMattersJoinQuery($query, $name);
            }
        }

        if ($request->has('email')) {
            $email = $request->input('email');
            if (trim((string) $email) !== '') {
                $email = (string) $email;
                if ($email === 'demo@gmail.com') {
                    $emailLower = strtolower($email);
                    $query->where(function ($q) use ($email, $emailLower) {
                        $q->whereRaw('LOWER(ad.email) = ?', [$emailLower])
                            ->orWhereRaw('LOWER(ad.email) LIKE ?', ['demo_%@gmail.com']);
                    });
                } else {
                    $query->where('ad.email', $email);
                }
            }
        }

        if ($request->has('phone')) {
            $phone = trim((string) $request->input('phone'));
            if ($phone !== '') {
                if ($phone === '4444444444') {
                    $query->where(function ($q) use ($phone) {
                        $q->where(function ($phoneQuery) use ($phone) {
                            $phoneQuery->where('ad.phone', $phone)
                                ->orWhere('ad.phone', 'LIKE', $phone . '_%');
                        })
                            ->orWhere('ad.country_code', 'LIKE', '%' . $phone . '%')
                            ->orWhereRaw("(COALESCE(ad.country_code, '') || COALESCE(ad.phone, '')) LIKE ?", ["%{$phone}%"]);
                    });
                } else {
                    $query->where(function ($q) use ($phone) {
                        $q->where('ad.phone', 'LIKE', '%' . $phone . '%')
                            ->orWhere('ad.country_code', 'LIKE', '%' . $phone . '%')
                            ->orWhereRaw("(COALESCE(ad.country_code, '') || COALESCE(ad.phone, '')) LIKE ?", ["%{$phone}%"]);
                    });
                }
            }
        }

        if ($request->filled('status')) {
            $query->where('ad.status', $request->input('status'));
        }

        if (
            $request->filled('quick_date_range') ||
            $request->filled('from_date') ||
            $request->filled('to_date')
        ) {
            $dateField = $request->input('date_filter_field', 'created_at') === 'updated_at'
                ? 'cm.updated_at'
                : 'cm.created_at';
            [$start, $end] = $this->resolveClientDateRange($request);

            if ($start && $end) {
                $query->whereBetween($dateField, [$start, $end]);
            }
        }

        return $query;
    }

    /**
     * Ensure only allowed date columns are used for filtering.
     */
    protected function resolveClientDateColumn(?string $field): string
    {
        $allowedColumns = ['created_at', 'updated_at'];

        return in_array($field, $allowedColumns, true) ? $field : 'created_at';
    }

    /**
     * Resolve the requested date range from quick selections or manual inputs.
     *
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    protected function resolveClientDateRange($request): array
    {
        $quickRange = $request->input('quick_date_range');

        if (!empty($quickRange)) {
            $range = $this->getQuickDateRangeBounds($quickRange);
            if ($range[0] && $range[1]) {
                return $range;
            }
        }

        $from = $this->parseClientDate($request->input('from_date'));
        $to = $this->parseClientDate($request->input('to_date'), true);

        if ($from || $to) {
            $start = $from ?? Carbon::now()->subYears(20)->startOfDay();
            $end = $to ?? Carbon::now()->endOfDay();

            return [$start, $end];
        }

        return [null, null];
    }

    /**
     * Map quick filter options to Carbon date ranges.
     *
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    protected function getQuickDateRangeBounds(string $range): array
    {
        $now = Carbon::now();

        switch ($range) {
            case 'today':
                $start = $now->copy()->startOfDay();
                $end = $now->copy()->endOfDay();
                break;
            case 'this_week':
                $start = $now->copy()->startOfWeek();
                $end = $now->copy()->endOfWeek();
                break;
            case 'this_month':
                $start = $now->copy()->startOfMonth();
                $end = $now->copy()->endOfMonth();
                break;
            case 'last_month':
                $start = $now->copy()->subMonth()->startOfMonth();
                $end = $now->copy()->subMonth()->endOfMonth();
                break;
            case 'last_30_days':
                $start = $now->copy()->subDays(30)->startOfDay();
                $end = $now->copy()->endOfDay();
                break;
            case 'last_90_days':
                $start = $now->copy()->subDays(90)->startOfDay();
                $end = $now->copy()->endOfDay();
                break;
            case 'this_year':
                $start = $now->copy()->startOfYear();
                $end = $now->copy()->endOfYear();
                break;
            case 'last_year':
                $start = $now->copy()->subYear()->startOfYear();
                $end = $now->copy()->subYear()->endOfYear();
                break;
            default:
                return [null, null];
        }

        return [$start, $end];
    }

    /**
     * Parse incoming date strings supporting multiple formats.
     */
    protected function parseClientDate(?string $value, bool $endOfDay = false): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        $formats = ['d/m/Y', 'Y-m-d'];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                return $endOfDay ? $date->endOfDay() : $date->startOfDay();
            } catch (\Throwable $th) {
                continue;
            }
        }

        return null;
    }
}

