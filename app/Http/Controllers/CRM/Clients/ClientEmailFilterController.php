<?php

namespace App\Http\Controllers\CRM\Clients;

use App\Http\Controllers\Concerns\EnsuresCrmRecordAccess;
use App\Http\Controllers\Controller;
use App\Models\ClientMatter;
use App\Models\EmailLog;
use App\Models\Staff;
use App\Services\Email\ClientEmailListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ClientEmailFilterController extends Controller
{
    use EnsuresCrmRecordAccess;

    public function __construct(
        private readonly ClientEmailListService $lists
    ) {
        $this->middleware('auth:admin');
    }

    public function filterEmails(Request $request): JsonResponse
    {
        try {
            $matterId = $request->input('client_matter_id');
            if (! $matterId) {
                return response()->json(['status' => 'error', 'message' => 'Matter ID is required'], 400);
            }

            if (! Schema::hasColumn('email_logs', 'client_matter_id')) {
                return response()->json([
                    'status' => 'success',
                    'emails' => [],
                    'message' => 'Email integration not configured yet',
                ]);
            }

            $this->ensureMatterAccess((int) $matterId);

            $paginator = $this->lists->paginateInbox($request->all(), 5);

            return $this->paginatedJson($paginator);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            Log::error('Error in filterEmails: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while fetching emails: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function filterSentEmails(Request $request): JsonResponse
    {
        try {
            $matterId = $request->input('client_matter_id');
            if (! $matterId) {
                return response()->json(['status' => 'error', 'message' => 'Matter ID is required'], 400);
            }

            $this->ensureMatterAccess((int) $matterId);

            $paginator = $this->lists->paginateSent($request->all(), 5);

            return $this->paginatedJson($paginator);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            Log::error('Error in filterSentEmails: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while fetching emails',
            ], 500);
        }
    }

    public function filterLeadEmails(Request $request): JsonResponse
    {
        try {
            $clientId = $request->input('client_id');
            if (! $clientId) {
                return response()->json(['status' => 'error', 'message' => 'Lead ID is required'], 400);
            }

            $this->ensureCrmRecordAccess((int) $clientId);

            $paginator = $this->lists->paginateLeadEmails($request->all(), 5);

            return $this->paginatedJson($paginator);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            Log::error('Error in filterLeadEmails: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while fetching lead emails',
            ], 500);
        }
    }

    /**
     * Deferred full body for list rows that omit `message`.
     */
    public function body(int $id): JsonResponse
    {
        $email = EmailLog::query()->find($id, ['id', 'client_id', 'client_matter_id', 'message', 'text_preview']);
        if (! $email) {
            return response()->json(['success' => false, 'error' => 'Email not found'], 404);
        }

        $clientId = (int) ($email->client_id ?? 0);
        if ($clientId <= 0 && ! empty($email->client_matter_id)) {
            $clientId = (int) ClientMatter::where('id', $email->client_matter_id)->value('client_id');
        }

        if ($clientId > 0) {
            $this->ensureCrmRecordAccess($clientId);
        } else {
            $staff = Auth::guard('admin')->user();
            if (! ($staff instanceof Staff && ($staff->canViewSyncedInboxMail() || $staff->canSyncInboxEmails() || $staff->hasEffectiveSuperAdminPrivileges()))) {
                return response()->json(['success' => false, 'error' => 'Unauthorized'], 403);
            }
        }

        return response()->json($this->lists->bodyPayload($email));
    }

    private function ensureMatterAccess(int $matterId): void
    {
        $clientId = (int) ClientMatter::where('id', $matterId)->value('client_id');
        if ($clientId > 0) {
            $this->ensureCrmRecordAccess($clientId);
        }
    }

    private function paginatedJson($paginator): JsonResponse
    {
        return response()->json([
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'emails' => $this->lists->mapPaginatorItems($paginator),
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
