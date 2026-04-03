<?php

namespace App\Traits;

use App\Models\Admin;
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
                $nameLower = strtolower($name);
                $query->where(function ($q) use ($nameLower) {
                    $q->whereRaw('LOWER(first_name) LIKE ?', ['%' . $nameLower . '%'])
                      ->orWhereRaw('LOWER(last_name) LIKE ?', ['%' . $nameLower . '%'])
                      ->orWhereRaw("LOWER(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '')) LIKE ?", ['%' . $nameLower . '%']);
                });
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

