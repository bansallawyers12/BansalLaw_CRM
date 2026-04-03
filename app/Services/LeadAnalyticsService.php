<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LeadAnalyticsService
{
    /**
     * Get conversion funnel statistics using actual pipeline stages
     */
    public function getConversionFunnel($startDate = null, $endDate = null)
    {
        $baseLeadQuery = fn() => Admin::where('type', 'lead')
            ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
            ->when($endDate, fn($q) => $q->where('created_at', '<=', $endDate));

        $totalLeads = $baseLeadQuery()->count();

        $stageNew       = $baseLeadQuery()->where('lead_status', 'new')->count();
        $stageFollowUp  = $baseLeadQuery()->where('lead_status', 'follow_up')->count();
        $stageNotQual   = $baseLeadQuery()->where('lead_status', 'not_qualified')->count();
        $stageHostile   = $baseLeadQuery()->where('lead_status', 'hostile')->count();

        $converted = Admin::where('type', 'client')
            ->where('lead_status', 'converted')
            ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
            ->when($endDate, fn($q) => $q->where('created_at', '<=', $endDate))
            ->count();

        $denominator = max($totalLeads, 1);

        return [
            'total_leads'   => $totalLeads,
            'new'           => ['count' => $stageNew,      'percentage' => round(($stageNew      / $denominator) * 100, 2)],
            'follow_up'     => ['count' => $stageFollowUp, 'percentage' => round(($stageFollowUp / $denominator) * 100, 2)],
            'not_qualified' => ['count' => $stageNotQual,  'percentage' => round(($stageNotQual  / $denominator) * 100, 2)],
            'hostile'       => ['count' => $stageHostile,  'percentage' => round(($stageHostile  / $denominator) * 100, 2)],
            'converted'     => ['count' => $converted,     'percentage' => round(($converted      / $denominator) * 100, 2)],
        ];
    }
    
    /**
     * Get lead source performance
     */
    public function getSourcePerformance($startDate = null, $endDate = null)
    {
        $query = Admin::where('type', 'lead')
            ->select('source', DB::raw('COUNT(*) as total'))
            ->groupBy('source');
        
        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }
        
        $sources = $query->get();
        
        $performance = [];
        foreach ($sources as $source) {
            $converted = Admin::where('type', 'client')
                ->where('source', $source->source)
                ->where('lead_status', 'converted')
                ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
                ->when($endDate, fn($q) => $q->where('created_at', '<=', $endDate))
                ->count();
            
            $performance[] = [
                'source' => $source->source ?: 'Unknown',
                'total_leads' => $source->total,
                'converted' => $converted,
                'conversion_rate' => $source->total > 0 ? round(($converted / $source->total) * 100, 2) : 0,
            ];
        }
        
        // Sort by total leads descending
        usort($performance, function($a, $b) {
            return $b['total_leads'] <=> $a['total_leads'];
        });
        
        return $performance;
    }
    
    /**
     * Get agent performance metrics
     */
    public function getAgentPerformance($startDate = null, $endDate = null)
    {
        $agents = Staff::where('status', 1)->get();

        $performance = [];

        foreach ($agents as $agent) {
            $leadQuery = Admin::where('type', 'lead')->where('user_id', $agent->id)
                ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
                ->when($endDate,   fn($q) => $q->where('created_at', '<=', $endDate));

            $assignedLeads = (clone $leadQuery)->count();

            $convertedLeads = Admin::where('type', 'client')
                ->where('lead_status', 'converted')
                ->where('user_id', $agent->id)
                ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
                ->when($endDate,   fn($q) => $q->where('created_at', '<=', $endDate))
                ->count();

            $overdueFollowups = (clone $leadQuery)
                ->where('lead_status', 'follow_up')
                ->whereNotNull('followup_date')
                ->where('followup_date', '<', now())
                ->count();

            $completedFollowups = DB::table('notes')
                ->where('assigned_to', $agent->id)
                ->where('task_group', 'Follow Up')
                ->where('status', '1')
                ->count();

            $performance[] = [
                'agent_id'               => $agent->id,
                'agent_name'             => $agent->first_name . ' ' . $agent->last_name,
                'assigned_leads'         => $assignedLeads,
                'converted_leads'        => $convertedLeads,
                'conversion_rate'        => $assignedLeads > 0 ? round(($convertedLeads / $assignedLeads) * 100, 2) : 0,
                'completed_followups'    => $completedFollowups,
                'overdue_followups'      => $overdueFollowups,
                'avg_response_time_hours' => 0,
            ];
        }

        usort($performance, fn($a, $b) => $b['conversion_rate'] <=> $a['conversion_rate']);

        return $performance;
    }
    
    /**
     * Calculate average response time for an agent
     * Note: Follow-up system removed, returns 0
     */
    protected function calculateAvgResponseTime($agentId, $startDate = null, $endDate = null)
    {
        return 0; // Follow-up system removed
    }
    
    /**
     * Get time-based lead trends
     */
    public function getLeadTrends($period = 'month', $count = 12)
    {
        $trends = [];
        
        for ($i = $count - 1; $i >= 0; $i--) {
            $date = match($period) {
                'week' => now()->subWeeks($i),
                'month' => now()->subMonths($i),
                'year' => now()->subYears($i),
                default => now()->subMonths($i),
            };
            
            $startDate = match($period) {
                'week'  => $date->copy()->startOfWeek(),
                'year'  => $date->copy()->startOfYear(),
                default => $date->copy()->startOfMonth(),
            };

            $endDate = match($period) {
                'week'  => $date->copy()->endOfWeek(),
                'year'  => $date->copy()->endOfYear(),
                default => $date->copy()->endOfMonth(),
            };
            
            $newLeads = Admin::where('type', 'lead')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();
            
            $converted = Admin::where('type', 'client')
                ->where('lead_status', 'converted')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();
            
            $trends[] = [
                'period' => $startDate->format($period == 'year' ? 'Y' : ($period == 'week' ? 'M d' : 'M Y')),
                'new_leads' => $newLeads,
                'converted' => $converted,
                'conversion_rate' => $newLeads > 0 ? round(($converted / $newLeads) * 100, 2) : 0,
            ];
        }
        
        return $trends;
    }
    
    /**
     * Get lead quality distribution
     */
    public function getLeadQualityDistribution($startDate = null, $endDate = null)
    {
        // lead_quality column removed - return empty distribution
        return [];
    }
    
    /**
     * Get comprehensive dashboard statistics
     */
    public function getDashboardStats($startDate = null, $endDate = null)
    {
        $query = Admin::where('type', 'lead');
        
        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }
        
        $active      = (clone $query)->where('status', 1)->count();
        $activeNew   = (clone $query)->where('lead_status', 'new')->count();
        $followUp    = (clone $query)->where('lead_status', 'follow_up')->count();

        $converted = Admin::where('type', 'client')
            ->where('lead_status', 'converted')
            ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
            ->when($endDate,   fn($q) => $q->where('created_at', '<=', $endDate))
            ->count();

        $overdueFollowups = (clone $query)
            ->where('lead_status', 'follow_up')
            ->whereNotNull('followup_date')
            ->where('followup_date', '<', now())
            ->count();

        return [
            'total_leads'        => (clone $query)->count(),
            'new_this_month'     => Admin::where('type', 'lead')
                ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
            'converted'          => $converted,
            'active'             => $active,
            'active_new'         => $activeNew,
            'active_follow_up'   => $followUp,
            'pending_followups'  => $followUp,
            'overdue_followups'  => $overdueFollowups,
            'avg_conversion_time' => $this->getAvgConversionTime($startDate, $endDate),
        ];
    }
    
    /**
     * Calculate average time to convert a lead
     */
    protected function getAvgConversionTime($startDate = null, $endDate = null)
    {
        $convertedLeads = Admin::where('type', 'client')
            ->where('lead_status', 'converted')
            ->when($startDate, fn($q) => $q->where('created_at', '>=', $startDate))
            ->when($endDate, fn($q) => $q->where('created_at', '<=', $endDate))
            ->whereNotNull('updated_at')
            ->get();
        
        if ($convertedLeads->isEmpty()) {
            return 0;
        }
        
        $totalDays = 0;
        foreach ($convertedLeads as $lead) {
            $totalDays += $lead->created_at->diffInDays($lead->updated_at);
        }
        
        return round($totalDays / $convertedLeads->count(), 1);
    }
}

