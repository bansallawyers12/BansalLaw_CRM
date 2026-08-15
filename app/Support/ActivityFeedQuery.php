<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Server-side Timeline filters. Mirrors public/js/crm/clients/tabs/activity-feed.js chips
 * so pagination does not hide matching rows that fall outside the current page.
 */
class ActivityFeedQuery
{
    public const PER_PAGE_DEFAULT = 40;

    public const PER_PAGE_MAX = 100;

    /** Matches ActivityFeed TASK_ACTION_SUBJECT_RE (subject text, case-insensitive). */
    public const TASK_ACTION_PATTERN = '(set action for|updated action for|completed action for|action completed for|new action assigned for|deleted([[:space:]]+completed)?[[:space:]]+action|appointment created|booking appointment|converted activity to note|extended note deadline|note added to booking appointment)';

    public const LEAD_CONVERTED_PATTERN = 'lead converted';

    public const RECEIPT_DOCUMENT_PATTERN = '(receipt document|journal receipt document|client receipt document|office receipt document)';

    public const DOCUMENT_SUBJECT_PATTERN = '(document|checklist|uploaded|signed document|placed signature fields)';

    public const ACCOUNTING_SUBJECT_PATTERN = '(invoice|receipt|payment|ledger|account|fee transfer|allocation|allocated|deposit|withdrawal|balance|cost agreement|costs disclosure)';

    public static function apply(Builder $query, Request $request): Builder
    {
        $type = strtolower(trim((string) $request->input('type', 'all')));
        if ($type !== '' && $type !== 'all') {
            self::applyTypeFilter($query, $type);
        }

        $keyword = trim((string) ($request->input('keyword') ?: $request->input('q') ?: ''));
        if ($keyword !== '') {
            $like = '%'.$keyword.'%';
            $query->where(function (Builder $q) use ($like) {
                $q->where('activities_logs.subject', 'ILIKE', $like)
                    ->orWhere('activities_logs.description', 'ILIKE', $like);
            });
        }

        $staff = trim((string) ($request->input('staff') ?: $request->input('user') ?: ''));
        if ($staff !== '') {
            $staffLike = '%'.strtolower($staff).'%';
            $query->whereHas('creator', function (Builder $q) use ($staffLike) {
                $q->whereRaw('LOWER(first_name) LIKE ?', [$staffLike]);
            });
        }

        $from = trim((string) $request->input('date_from', ''));
        $to = trim((string) $request->input('date_to', ''));
        if ($from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $query->whereDate('activities_logs.created_at', '>=', $from);
        }
        if ($to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $query->whereDate('activities_logs.created_at', '<=', $to);
        }

        return $query;
    }

    public static function page(Request $request): int
    {
        return max(1, (int) $request->input('page', 1));
    }

    public static function perPage(Request $request): int
    {
        return max(1, min(self::PER_PAGE_MAX, (int) $request->input('per_page', self::PER_PAGE_DEFAULT)));
    }

    public static function filtersActive(Request $request): bool
    {
        $type = strtolower(trim((string) $request->input('type', 'all')));
        if ($type !== '' && $type !== 'all') {
            return true;
        }

        foreach (['keyword', 'q', 'staff', 'user', 'date_from', 'date_to'] as $key) {
            if (trim((string) $request->input($key, '')) !== '') {
                return true;
            }
        }

        return false;
    }

    private static function applyTypeFilter(Builder $query, string $type): void
    {
        match ($type) {
            'activity' => $query->where(function (Builder $q) {
                $q->whereIn('activities_logs.activity_type', ['activity', 'sms', 'stage', 'lead_converted'])
                    ->orWhereRaw("COALESCE(activities_logs.subject, '') ILIKE ?", ['%'.self::LEAD_CONVERTED_PATTERN.'%'])
                    ->orWhereRaw("COALESCE(activities_logs.subject, '') ~* ?", [self::TASK_ACTION_PATTERN]);
            }),
            'note' => $query->where('activities_logs.activity_type', 'note')
                ->whereRaw("COALESCE(activities_logs.subject, '') NOT ILIKE ?", ['%'.self::LEAD_CONVERTED_PATTERN.'%'])
                ->whereRaw("COALESCE(activities_logs.subject, '') !~* ?", [self::TASK_ACTION_PATTERN]),
            'document' => $query->where(function (Builder $q) {
                $q->where('activities_logs.activity_type', 'document')
                    ->orWhere(function (Builder $inner) {
                        $inner->whereRaw("COALESCE(activities_logs.subject, '') ~* ?", [self::DOCUMENT_SUBJECT_PATTERN])
                            ->whereRaw("COALESCE(activities_logs.subject, '') !~* ?", [self::RECEIPT_DOCUMENT_PATTERN]);
                    });
            }),
            'signature' => $query->where('activities_logs.activity_type', 'signature'),
            'accounting' => $query->where(function (Builder $q) {
                $q->where('activities_logs.activity_type', 'financial')
                    ->orWhereRaw("COALESCE(activities_logs.subject, '') ~* ?", [self::ACCOUNTING_SUBJECT_PATTERN]);
            }),
            default => $query->where('activities_logs.activity_type', $type),
        };
    }
}
