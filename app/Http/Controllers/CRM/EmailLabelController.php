<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Concerns\EnsuresCrmRecordAccess;
use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Services\Email\EmailLabelCatalogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

/**
 * CRM runtime API for email labels on the mail UI.
 *
 * Create/edit of label definitions lives in Admin Console
 * (adminconsole.features.emaillabels.*). This controller only lists active
 * labels and applies/removes them on email logs.
 */
class EmailLabelController extends Controller
{
    use EnsuresCrmRecordAccess;

    public function __construct(
        private readonly EmailLabelCatalogService $emailLabelCatalog
    ) {
        $this->middleware('auth:admin');
    }

    /**
     * Get all labels visible to the current staff member (system + their custom).
     */
    public function index()
    {
        try {
            $labels = $this->emailLabelCatalog->listVisibleForStaff(Auth::id());

            return response()->json([
                'success' => true,
                'labels' => $labels
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch labels', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch labels'
            ], 500);
        }
    }

    /**
     * Apply label to email
     */
    public function apply(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'mail_report_id' => 'required|exists:email_logs,id',
                'label_id' => 'required|exists:email_labels,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $emailLog = EmailLog::findOrFail($request->mail_report_id);
            $this->ensureCrmRecordAccessForOptionalClientId(
                $emailLog->client_id ? (int) $emailLog->client_id : null
            );
            
            // Check if already attached
            if (!$emailLog->labels()->where('email_label_id', $request->label_id)->exists()) {
                $emailLog->labels()->attach($request->label_id);
            }

            return response()->json([
                'success' => true,
                'message' => 'Label applied successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to apply label', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to apply label'
            ], 500);
        }
    }

    /**
     * Remove label from email
     */
    public function remove(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'mail_report_id' => 'required|exists:email_logs,id',
                'label_id' => 'required|exists:email_labels,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $emailLog = EmailLog::findOrFail($request->mail_report_id);
            $this->ensureCrmRecordAccessForOptionalClientId(
                $emailLog->client_id ? (int) $emailLog->client_id : null
            );
            $emailLog->labels()->detach($request->label_id);

            return response()->json([
                'success' => true,
                'message' => 'Label removed successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to remove label', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove label'
            ], 500);
        }
    }
}
