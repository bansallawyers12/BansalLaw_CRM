<?php

namespace App\Http\Controllers\AdminConsole\Sms;

use App\Http\Controllers\Controller;
use App\Models\SmsLog;
use App\Models\Staff;
use App\Models\SmsTemplate;
use App\Services\AdminConsoleFormDataService;
use App\Services\Sms\UnifiedSmsManager;
use App\Support\StaffClientVisibility;
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
    public function dashboard(Request $request, AdminConsoleFormDataService $formData)
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
        $recentQuery = SmsLog::with(['client', 'contact', 'sender'])
                           ->orderBy('created_at', 'desc');
        $this->scopeSmsLogsForViewer($recentQuery);
        $recentSms = $recentQuery->limit(10)->get();

        $activeTemplates = $formData->activeSmsTemplates();

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
        $this->scopeSmsLogsForViewer($query);

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
        $smsQuery = SmsLog::with(['client', 'contact', 'sender'])->whereKey($id);
        $this->scopeSmsLogsForViewer($smsQuery);
        $smsLog = $smsQuery->firstOrFail();

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
        $smsQuery = SmsLog::query()->whereKey($smsLogId);
        $this->scopeSmsLogsForViewer($smsQuery);
        if (! $smsQuery->exists()) {
            abort(404);
        }

        $result = $this->smsManager->getDeliveryStatus($smsLogId);

        return response()->json($result);
    }

    /**
     * Normal staff only see SMS already filed on a client or lead they can open.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\SmsLog>  $query
     */
    protected function scopeSmsLogsForViewer($query): void
    {
        $viewer = Auth::guard('admin')->user();
        if (! $viewer instanceof Staff || ! StaffClientVisibility::isAllocationEnforcementEnabled() || StaffClientVisibility::seesAllClientMatters($viewer)) {
            return;
        }

        $table = $query->getModel()->getTable();
        $staffId = (int) $viewer->id;
        $query->where($table.'.client_id', '>', 0)
            ->whereExists(function ($sub) use ($staffId, $table) {
                $sub->select(\Illuminate\Support\Facades\DB::raw('1'))
                    ->from('admins')
                    ->whereColumn('admins.id', $table.'.client_id')
                    ->where(function ($visibility) use ($staffId) {
                        StaffClientVisibility::applyAssignedClientAdminVisibilityClauses(
                            $visibility,
                            $staffId,
                            'admins.id',
                            StaffClientVisibility::isAssignmentOnlyAccessEnabled() ? null : 'admins.user_id',
                            StaffClientVisibility::isAssignmentOnlyAccessEnabled() ? 'admins.type' : null
                        );
                    });
            });
    }
}
