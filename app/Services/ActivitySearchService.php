<?php

namespace App\Services;

use App\Models\ActivitiesLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Activity Search query builder + streamed CSV export (no full in-memory load).
 */
class ActivitySearchService
{
    public function exportLimit(): int
    {
        return max(100, min(20000, (int) config('crm.admin_console.activity_export_limit', 5000)));
    }

    public function exportChunkSize(): int
    {
        return max(100, min(2000, (int) config('crm.admin_console.activity_export_chunk_size', 500)));
    }

    public function buildQuery(Request $request): Builder
    {
        $query = ActivitiesLog::query()
            ->select(
                'activities_logs.id',
                'activities_logs.client_id',
                'activities_logs.created_at',
                'activities_logs.activity_type',
                'activities_logs.task_group',
                'activities_logs.task_status',
                'activities_logs.subject',
                'activities_logs.description',
                'activities_logs.followup_date',
                'creator.first_name as creator_first_name',
                'creator.last_name as creator_last_name',
                'creator.email as creator_email',
                'assignee.first_name as assignee_first_name',
                'assignee.last_name as assignee_last_name',
                'assignee.email as assignee_email',
                'client.first_name as client_first_name',
                'client.last_name as client_last_name',
                'client.email as client_email'
            )
            ->leftJoin('staff as creator', 'activities_logs.created_by', '=', 'creator.id')
            ->leftJoin('staff as assignee', $this->assigneeJoinOn())
            ->leftJoin('admins as client', 'activities_logs.client_id', '=', 'client.id');

        if ($request->filled('assigner_id')) {
            $query->where('activities_logs.created_by', $request->assigner_id);
        }

        if ($request->filled('assignee_id')) {
            $query->where('activities_logs.use_for', (string) $request->assignee_id);
        }

        if ($request->filled('client_id')) {
            $query->where('activities_logs.client_id', $request->client_id);
        }

        if ($request->filled('activity_type')) {
            $query->where('activities_logs.activity_type', $request->activity_type);
        }

        if ($request->filled('task_status')) {
            $query->where('activities_logs.task_status', $request->task_status);
        }

        if ($request->filled('task_group')) {
            $query->where('activities_logs.task_group', $request->task_group);
        }

        if ($request->filled('date_from')) {
            $dateFrom = Carbon::parse($request->date_from)->startOfDay();
            $query->where('activities_logs.created_at', '>=', $dateFrom);
        }

        if ($request->filled('date_to')) {
            $dateTo = Carbon::parse($request->date_to)->endOfDay();
            $query->where('activities_logs.created_at', '<=', $dateTo);
        }

        if ($request->filled('keyword')) {
            $keyword = $request->keyword;
            $query->where(function ($q) use ($keyword) {
                $q->where('activities_logs.subject', 'ILIKE', '%' . $keyword . '%')
                    ->orWhere('activities_logs.description', 'ILIKE', '%' . $keyword . '%');
            });
        }

        return $query;
    }

    public function streamCsvExport(Request $request): StreamedResponse
    {
        $limit = $this->exportLimit();
        $chunkSize = $this->exportChunkSize();
        $filename = 'activity_search_' . date('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $baseQuery = $this->buildQuery($request)
            ->orderByDesc('activities_logs.created_at')
            ->orderByDesc('activities_logs.id')
            ->limit($limit);

        return response()->stream(function () use ($baseQuery, $chunkSize) {
            $file = fopen('php://output', 'w');
            fputcsv($file, [
                'Activity ID',
                'Date & Time',
                'Assigner Name',
                'Assigner Email',
                'Assignee Name',
                'Assignee Email',
                'Client Name',
                'Client Email',
                'Activity Type',
                'Task Category',
                'Status',
                'Subject',
                'Description',
                'Follow-up Date',
            ]);

            $baseQuery->clone()->lazy($chunkSize)->each(function ($activity) use ($file) {
                $assignerName = trim(($activity->creator_first_name ?? '') . ' ' . ($activity->creator_last_name ?? ''));
                $assigneeName = $activity->assignee_first_name
                    ? trim($activity->assignee_first_name . ' ' . ($activity->assignee_last_name ?? ''))
                    : 'N/A';
                $clientName = trim(($activity->client_first_name ?? '') . ' ' . ($activity->client_last_name ?? ''));

                $status = 'N/A';
                if ($activity->task_group) {
                    $status = (int) $activity->task_status === 1 ? 'Completed' : 'Incomplete';
                }

                $createdAt = $activity->created_at;
                $followupDate = $activity->followup_date;

                fputcsv($file, [
                    $activity->id,
                    $createdAt ? Carbon::parse($createdAt)->format('Y-m-d H:i:s') : '',
                    $assignerName,
                    $activity->creator_email ?? '',
                    $assigneeName,
                    $activity->assignee_email ?? '',
                    $clientName,
                    $activity->client_email ?? '',
                    $activity->activity_type ?? 'N/A',
                    $activity->task_group ?? 'N/A',
                    $status,
                    $activity->subject ?? '',
                    strip_tags($activity->description ?? ''),
                    $followupDate ? Carbon::parse($followupDate)->format('Y-m-d H:i:s') : '',
                ]);
            });

            fclose($file);
        }, 200, $headers);
    }

    /**
     * use_for is VARCHAR (staff id as text, or e.g. "matter"). PostgreSQL cannot compare varchar to bigint;
     * join by comparing string form of staff.id to use_for.
     */
    private function assigneeJoinOn(): \Closure
    {
        $driver = DB::connection()->getDriverName();

        return function ($join) use ($driver) {
            if ($driver === 'pgsql') {
                $join->whereRaw('assignee.id::text = activities_logs.use_for');

                return;
            }
            if ($driver === 'mysql') {
                $join->whereRaw('CAST(assignee.id AS CHAR) = activities_logs.use_for');

                return;
            }
            $join->whereRaw('CAST(assignee.id AS TEXT) = activities_logs.use_for');
        };
    }
}
