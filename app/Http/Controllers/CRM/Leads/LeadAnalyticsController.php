<?php

namespace App\Http\Controllers\CRM\Leads;

use App\Http\Controllers\Controller;
use App\Services\LeadAnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeadAnalyticsController extends Controller
{
    protected $analyticsService;
    
    public function __construct(LeadAnalyticsService $analyticsService)
    {
        $this->middleware('auth:admin');
        $this->analyticsService = $analyticsService;
    }
    
    /**
     * Display analytics dashboard
     * Only admin and super admin can access (roles 1, 12)
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!in_array($user->role ?? 0, [1, 12])) {
            return redirect()->back()->with('error', 'Only admin and super admin can view analytics.');
        }
        
        // Get date range from request or default to last 30 days
        $startDate = $request->filled('start_date') ? Carbon::parse($request->get('start_date'))->startOfDay() : now()->subDays(30)->startOfDay();
        $endDate   = $request->filled('end_date')   ? Carbon::parse($request->get('end_date'))->endOfDay()     : now()->endOfDay();
        
        // Get comprehensive statistics
        $dashboardStats = $this->analyticsService->getDashboardStats($startDate, $endDate);
        $conversionFunnel = $this->analyticsService->getConversionFunnel($startDate, $endDate);
        $sourcePerformance = $this->analyticsService->getSourcePerformance($startDate, $endDate);
        $agentPerformance = $this->analyticsService->getAgentPerformance($startDate, $endDate);
        $leadQuality = $this->analyticsService->getLeadQualityDistribution($startDate, $endDate);
        
        return view('crm.leads.analytics.dashboard', compact(
            'dashboardStats',
            'conversionFunnel',
            'sourcePerformance',
            'agentPerformance',
            'leadQuality',
            'startDate',
            'endDate'
        ));
    }
    
    /**
     * Get trends data for charts (AJAX)
     * Only admin and super admin can access (roles 1, 12)
     */
    public function getTrends(Request $request)
    {
        if (!in_array(Auth::user()->role ?? 0, [1, 12])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        $period = $request->get('period', 'month');
        $count = $request->get('count', 12);
        
        $trends = $this->analyticsService->getLeadTrends($period, $count);
        
        return response()->json($trends);
    }
    
    /**
     * Export analytics report
     * Only admin and super admin can export (roles 1, 12)
     */
    public function export(Request $request)
    {
        if (!in_array(Auth::user()->role ?? 0, [1, 12])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        $startDate = $request->filled('start_date') ? Carbon::parse($request->get('start_date'))->startOfDay() : now()->subDays(30)->startOfDay();
        $endDate   = $request->filled('end_date')   ? Carbon::parse($request->get('end_date'))->endOfDay()     : now()->endOfDay();

        $data = [
            'dashboard_stats' => $this->analyticsService->getDashboardStats($startDate, $endDate),
            'conversion_funnel' => $this->analyticsService->getConversionFunnel($startDate, $endDate),
            'source_performance' => $this->analyticsService->getSourcePerformance($startDate, $endDate),
            'agent_performance' => $this->analyticsService->getAgentPerformance($startDate, $endDate),
            'lead_quality' => $this->analyticsService->getLeadQualityDistribution($startDate, $endDate),
        ];
        
        // For now, return JSON. Can be enhanced to export as PDF/Excel
        return response()->json($data);
    }
    
    /**
     * Get agent comparison data (AJAX)
     * Only admin and super admin can compare agents (roles 1, 12)
     */
    public function compareAgents(Request $request)
    {
        if (!in_array(Auth::user()->role ?? 0, [1, 12])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        $agentIds = $request->get('agent_ids', []);
        $startDate = $request->filled('start_date') ? Carbon::parse($request->get('start_date'))->startOfDay() : now()->subDays(30)->startOfDay();
        $endDate   = $request->filled('end_date')   ? Carbon::parse($request->get('end_date'))->endOfDay()     : now()->endOfDay();

        $comparison = [];
        
        // Get performance for each agent
        $allAgents = $this->analyticsService->getAgentPerformance($startDate, $endDate);
        
        foreach ($allAgents as $agent) {
            if (in_array($agent['agent_id'], $agentIds)) {
                $comparison[] = $agent;
            }
        }
        
        return response()->json($comparison);
    }
}

