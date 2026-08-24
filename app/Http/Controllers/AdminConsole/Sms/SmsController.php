<?php

namespace App\Http\Controllers\AdminConsole\Sms;

use App\Http\Controllers\Controller;
use App\Models\SmsLog;
use App\Models\SmsTemplate;
use App\Services\Sms\UnifiedSmsManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * SmsController
 * 
 * Handles SMS dashboard, history, and manual sending for AdminConsole
 */
class SmsController extends Controller
{
    protected $smsManager;

    public function __construct(UnifiedSmsManager $smsManager)
    {
        $this->middleware('auth:admin');
        $this->smsManager = $smsManager;
    }

    /**
     * Show SMS dashboard
     */
    public function dashboard(Request $request)
    {
        // Get today's statistics
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        
        $stats = [
            'total_today' => SmsLog::whereBetween('created_at', [$todayStart, $todayEnd])->count(),
            'cellcast_today' => SmsLog::whereBetween('created_at', [$todayStart, $todayEnd])
                                      ->where('provider', 'cellcast')->count(),
            'failed_today' => SmsLog::whereBetween('created_at', [$todayStart, $todayEnd])
                                    ->where('status', 'failed')->count(),
        ];
        
        // Get recent SMS activity (last 10)
        $recentSms = SmsLog::with(['client', 'contact', 'sender'])
                           ->orderBy('created_at', 'desc')
                           ->limit(10)
                           ->get();

        $activeTemplates = SmsTemplate::where('is_active', true)->orderBy('title')->get();

        if ($request->wantsJson() || $request->ajax() || $request->has('json')) {
            return response()->json([
                'success' => true,
                'stats' => $stats,
                'recentSms' => $recentSms,
                'templates' => $activeTemplates
            ]);
        }
        
        return view('AdminConsole.features.sms.dashboard', compact('stats', 'recentSms', 'activeTemplates'));
    }

    /**
     * Show SMS history
     */
    public function history(Request $request)
    {
        $query = SmsLog::with(['client', 'contact', 'sender'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('provider')) {
            $query->where('provider', $request->provider);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('recipient_phone', 'like', "%{$search}%")
                  ->orWhere('formatted_phone', 'like', "%{$search}%")
                  ->orWhere('message_content', 'like', "%{$search}%");
            });
        }

        $smsLogs = $query->paginate(20);

        if ($request->wantsJson() || $request->ajax() || $request->has('json')) {
            return response()->json([
                'success' => true,
                'data' => $smsLogs
            ]);
        }

        return view('AdminConsole.features.sms.history.index', compact('smsLogs'));
    }

    /**
     * Show single SMS details
     */
    public function show($id)
    {
        $smsLog = SmsLog::with(['client', 'contact', 'sender'])->findOrFail($id);

        if (request()->wantsJson() || request()->ajax() || request()->has('json')) {
            return response()->json([
                'success' => true,
                'data' => $smsLog
            ]);
        }
        
        return view('AdminConsole.features.sms.history.show', compact('smsLog'));
    }

    /**
     * Get SMS statistics (API endpoint)
     */
    public function statistics(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $stats = $this->smsManager->getStatistics($startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Check SMS delivery status (API endpoint)
     */
    public function checkStatus($smsLogId)
    {
        $result = $this->smsManager->getDeliveryStatus($smsLogId);

        return response()->json($result);
    }
}
