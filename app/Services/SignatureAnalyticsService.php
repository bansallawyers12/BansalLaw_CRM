<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Signer;
use App\Models\Admin;
use Illuminate\Support\Facades\DB;

class SignatureAnalyticsService
{
    /**
     * Get median time to sign (in hours)
     * 
     * @param string|null $documentType Filter by document type
     * @param int|null $ownerId Filter by owner
     * @return float
     */
    public function getMedianTimeToSign($documentType = null, $ownerId = null): float
    {
        $query = Document::where('status', 'signed')
            ->whereHas('signers', fn($q) => $q->whereNotNull('signed_at'));
        
        // document_type column removed - filter removed
        
        if ($ownerId) {
            $query->where('created_by', $ownerId);
        }
        
        $documents = $query->with('signers')->get()->map(function($doc) {
            $lastSigned = $doc->signers->whereNotNull('signed_at')->max('signed_at');
            return $lastSigned ? $doc->created_at->diffInHours($lastSigned) : null;
        })->filter()->sort()->values();
        
        $count = $documents->count();
        if ($count === 0) return 0;
        
        $middle = floor($count / 2);
        
        if ($count % 2 == 0 && $count > 1) {
            return round(($documents[$middle - 1] + $documents[$middle]) / 2, 2);
        }
        
        return round($documents[$middle], 2);
    }

    /**
     * Get top signers (repeat recipients)
     * 
     * @param int $limit
     * @return \Illuminate\Support\Collection
     */
    public function getTopSigners(int $limit = 10)
    {
        return Signer::select('email', 'name')
            ->selectRaw('COUNT(*) as total_signed')
            ->selectRaw('COUNT(CASE WHEN status = \'signed\' THEN 1 END) as completed_count')
            ->selectRaw('AVG(CASE WHEN signed_at IS NOT NULL THEN EXTRACT(EPOCH FROM (signed_at - created_at))/3600 END) as avg_time_hours')
            ->groupBy('email', 'name')
            ->orderByDesc('completed_count')
            ->limit($limit)
            ->get()
            ->map(function($signer) {
                $signer->avg_time_hours = $signer->avg_time_hours ? round($signer->avg_time_hours, 1) : null;
                return $signer;
            });
    }

    /**
     * Get document type statistics
     * 
     * @return \Illuminate\Support\Collection
     */
    public function getDocumentTypeStats()
    {
        return Document::selectRaw("'general' as document_type")
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN status = \'signed\' THEN 1 ELSE 0 END) as signed')
            ->selectRaw('SUM(CASE WHEN status = \'sent\' THEN 1 ELSE 0 END) as pending')
            ->selectRaw('SUM(CASE WHEN status = \'draft\' THEN 1 ELSE 0 END) as draft')
            ->selectRaw('AVG(CASE WHEN status = \'signed\' AND (SELECT MAX(s.signed_at) FROM signers s WHERE s.document_id = documents.id AND s.signed_at IS NOT NULL) IS NOT NULL THEN EXTRACT(EPOCH FROM ((SELECT MAX(s.signed_at) FROM signers s WHERE s.document_id = documents.id) - documents.created_at))/3600 END) as avg_time_hours')
            ->notArchived()
            ->get()
            ->map(function($stat) {
                $stat->avg_time_hours = $stat->avg_time_hours ? round($stat->avg_time_hours, 1) : null;
                $stat->completion_rate = $stat->total > 0 ? round(($stat->signed / $stat->total) * 100, 1) : 0;
                return $stat;
            });
    }

    /**
     * Get overdue documents with details
     * 
     * @return \Illuminate\Support\Collection
     */
    public function getOverdueAnalytics()
    {
        return collect(); // due_at column removed - no overdue analytics
    }

    /**
     * Get completion rate for a date range
     * 
     * @param string $startDate
     * @param string $endDate
     * @return float
     */
    public function getCompletionRate($startDate, $endDate): float
    {
        $total = Document::whereBetween('created_at', [$startDate, $endDate])
            ->whereIn('status', ['sent', 'signed'])
            ->notArchived()
            ->count();
        
        if ($total === 0) return 0;
        
        $signed = Document::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'signed')
            ->notArchived()
            ->count();
        
        return round(($signed / $total) * 100, 1);
    }

    /**
     * Get average number of reminders sent
     * 
     * @param string $startDate
     * @param string $endDate
     * @return float
     */
    public function getAverageReminders($startDate, $endDate): float
    {
        $avg = Signer::whereHas('document', function($q) use ($startDate, $endDate) {
                $q->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->avg('reminder_count');
        
        return round($avg ?? 0, 1);
    }

    /**
     * Get overdue count
     * 
     * @return int
     */
    public function getOverdueCount(): int
    {
        return 0; // due_at column removed
    }

    /**
     * Get signature trend data for charts
     * 
     * @param string $startDate
     * @param string $endDate
     * @param string $interval (day, week, month)
     * @return array
     */
    public function getSignatureTrend($startDate, $endDate, $interval = 'day'): array
    {
        $dateFormat = match($interval) {
            'day' => 'YYYY-MM-DD',
            'week' => 'IYYY-IW',
            'month' => 'YYYY-MM',
            default => 'YYYY-MM-DD'
        };
        
        $sent = Document::whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw("TO_CHAR(created_at, '{$dateFormat}') as period")
            ->selectRaw('COUNT(*) as count')
            ->groupBy('period')
            ->orderBy('period')
            ->pluck('count', 'period')
            ->toArray();
        
        $signed = Document::whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'signed')
            ->selectRaw("TO_CHAR(created_at, '{$dateFormat}') as period")
            ->selectRaw('COUNT(*) as count')
            ->groupBy('period')
            ->orderBy('period')
            ->pluck('count', 'period')
            ->toArray();
        
        return [
            'labels' => array_keys($sent),
            'sent' => array_values($sent),
            'signed' => array_values($signed),
        ];
    }

    /**
     * Get dashboard summary statistics
     * 
     * @param int|null $userId Filter by user
     * @return array
     */
    public function getDashboardStats($userId = null): array
    {
        $query = Document::notArchived();
        
        if ($userId) {
            $query->where('created_by', $userId);
        }
        
        $totalSent = (clone $query)->whereIn('status', ['sent', 'signed'])->count();
        $signed = (clone $query)->where('status', 'signed')->count();
        $pending = (clone $query)->where('status', 'sent')->count();
        $overdue = 0; // due_at column removed
        
        return [
            'total_sent' => $totalSent,
            'signed' => $signed,
            'pending' => $pending,
            'overdue' => $overdue,
            'completion_rate' => $totalSent > 0 ? round(($signed / $totalSent) * 100, 1) : 0,
            'median_time_hours' => $this->getMedianTimeToSign(null, $userId),
        ];
    }

    /**
     * Get user performance comparison
     * 
     * @return \Illuminate\Support\Collection
     */
    public function getUserPerformance()
    {
        return \App\Models\Staff::query()  // Staff only (leads/clients in admins)
            ->select('id', 'first_name', 'last_name', 'email')
            ->get()
            ->map(function($user) {
                $stats = $this->getDashboardStats($user->id);
                
                return [
                    'user_id' => $user->id,
                    'name' => $user->first_name . ' ' . $user->last_name,
                    'email' => $user->email,
                    'total_sent' => $stats['total_sent'],
                    'signed' => $stats['signed'],
                    'pending' => $stats['pending'],
                    'completion_rate' => $stats['completion_rate'],
                    'median_time_hours' => $stats['median_time_hours'],
                ];
            })
            ->sortByDesc('total_sent')
            ->values();
    }

    /**
     * Get activity by hour of day (for optimization)
     * 
     * @return array
     */
    public function getActivityByHour(): array
    {
        $created = Document::selectRaw('EXTRACT(HOUR FROM created_at) as hour')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->pluck('count', 'hour')
            ->toArray();
        
        $signed = Signer::where('status', 'signed')
            ->whereNotNull('signed_at')
            ->selectRaw('EXTRACT(HOUR FROM signed_at) as hour')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('hour')
            ->orderBy('hour')
            ->pluck('count', 'hour')
            ->toArray();
        
        // Fill in missing hours with 0
        $hours = range(0, 23);
        $result = [];
        
        foreach ($hours as $hour) {
            $result[] = [
                'hour' => $hour,
                'created' => $created[$hour] ?? 0,
                'signed' => $signed[$hour] ?? 0,
            ];
        }
        
        return $result;
    }
}

