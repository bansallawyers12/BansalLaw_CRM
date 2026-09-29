<?php

namespace App\Http\Controllers\CRM;

use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\Staff;
use App\Services\EmailMatchingService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Logging\OutlookAddinLogger;
use App\Models\EmailLog;

class OutlookAddinController extends EmailUploadController
{
    protected ?EmailMatchingService $matchingService = null;

    public function __construct(?EmailMatchingService $matchingService = null)
    {
        // Initialize Python service config without attaching mandatory auth:admin middleware
        $this->pythonServiceUrl = (string) config('services.python.url', env('PYTHON_SERVICE_URL', 'http://127.0.0.1:5002'));
        $this->pythonServiceTimeout = max(30, (int) config('services.python.timeout', env('PYTHON_SERVICE_TIMEOUT', 180)));
        $this->matchingService = $matchingService ?? app(EmailMatchingService::class);
    }

    /**
     * Render the Outlook Add-in Taskpane view.
     * Sets headers allowing it to be embedded inside Outlook desktop / web frames.
     */
    public function taskpane(Request $request)
    {
        $currentStaff = Auth::guard('admin')->user();
        $staffMembers = Staff::where('status', 1)->select('id', 'first_name', 'last_name', 'email')->orderBy('first_name')->get();

        $response = response()->view('outlook_addin.taskpane', [
            'currentStaff' => $currentStaff,
            'staffMembers' => $staffMembers,
            'baseUrl' => url('/'),
        ]);

        // Office Add-ins are loaded in an iframe/webview. Remove restrictive frame headers.
        $response->headers->remove('X-Frame-Options');
        $response->headers->set(
            'Content-Security-Policy',
            "frame-ancestors 'self' https://*.office.com https://*.office365.com https://*.outlook.com https://*.microsoft.com http://localhost:* https://localhost:*;"
        );

        return $response;
    }

    /**
     * Generate or download the manifest.xml for this CRM instance.
     * Replaces localhost/domain URLs dynamically based on current host or ?base_url query.
     */
    public function manifest(Request $request)
    {
        $configuredBase = config('services.outlook_addin.base_url') ?: config('app.url');
        $baseUrl = rtrim((string) $request->query('base_url', $configuredBase ?: url('/')), '/');

        // Automatically adapt to current host if accessed over HTTPS tunnel or domain without explicit base_url query
        if (!$request->has('base_url')) {
            $host = $request->getHost();
            if ($request->isSecure() || str_contains($host, '.loca.lt') || str_contains($host, '.ngrok')) {
                $baseUrl = "https://{$host}";
            }
        }

        // Microsoft Office strictly requires https:// for ALL URLs in manifest.xml
        if (str_starts_with($baseUrl, 'http://')) {
            $baseUrl = 'https://' . substr($baseUrl, 7);
        }

        $manifestXml = $this->buildManifestXml($baseUrl);

        if ($request->query('download')) {
            return response($manifestXml, 200, [
                'Content-Type' => 'application/xml',
                'Content-Disposition' => 'attachment; filename="manifest-bansallaw-crm.xml"',
            ]);
        }

        return response($manifestXml, 200, [
            'Content-Type' => 'application/xml',
        ]);
    }

    /**
     * Match matter and client from email metadata (subject, sender, etc.)
     */
    public function match(Request $request)
    {
        $subject = trim((string) $request->input('subject', ''));
        $senderEmail = trim((string) $request->input('from_email', ''));
        $senderName = trim((string) $request->input('from_name', ''));

        $suggestions = [];
        $bestMatch = null;

        // 1. Try to match by explicit pattern: [CLIENT_REF] / [MATTER_NO] or FAM_1
        // Example: "GURM2600071 / FAM_1"
        if (preg_match('/([A-Z]{3,}\d+)\s*[\/|\-]\s*([A-Z0-9_\-]+)/i', $subject, $m)) {
            $clientRef = strtoupper($m[1]);
            $matterNo = strtoupper($m[2]);

            $matter = ClientMatter::where('client_unique_matter_no', $matterNo)
                ->whereHas('client', function ($q) use ($clientRef) {
                    $q->where('client_id', $clientRef);
                })
                ->with(['client', 'matter'])
                ->first();

            if ($matter && $matter->client) {
                $bestMatch = $this->formatMatterMatch($matter, 98, "Matched Subject Reference ({$clientRef} / {$matterNo})");
                $suggestions[] = $bestMatch;
            }
        }

        // 2. Try match by matter code alone (e.g. FAM_1, CIV_2, IMM_10)
        if (!$bestMatch && preg_match('/\b([A-Z]{2,6}_\d+)\b/i', $subject, $m)) {
            $matterNo = strtoupper($m[1]);
            $matters = ClientMatter::where('client_unique_matter_no', $matterNo)
                ->with(['client', 'matter'])
                ->get();

            foreach ($matters as $matter) {
                if ($matter->client) {
                    $match = $this->formatMatterMatch($matter, 92, "Matched Matter Code ({$matterNo})");
                    $suggestions[] = $match;
                    if (!$bestMatch) {
                        $bestMatch = $match;
                    }
                }
            }
        }

        // 3. Try match by client ref alone (e.g. GURM2600071)
        if (preg_match('/\b([A-Z]{3,8}\d{4,10})\b/i', $subject, $m)) {
            $clientRef = strtoupper($m[1]);
            $client = Admin::where('client_id', $clientRef)->whereIn('type', ['client', 'lead'])->first();

            if ($client) {
                $clientMatters = ClientMatter::where('client_id', $client->id)->with(['client', 'matter'])->get();
                foreach ($clientMatters as $matter) {
                    $match = $this->formatMatterMatch($matter, 88, "Matched Client ID ({$clientRef})");
                    $this->addUniqueSuggestion($suggestions, $match);
                    if (!$bestMatch) {
                        $bestMatch = $match;
                    }
                }
            }
        }

        // 4. Try match by sender email address
        if ($senderEmail) {
            $client = Admin::where('email', $senderEmail)->whereIn('type', ['client', 'lead'])->first();
            if ($client) {
                $clientMatters = ClientMatter::where('client_id', $client->id)->with(['client', 'matter'])->get();
                foreach ($clientMatters as $matter) {
                    $match = $this->formatMatterMatch($matter, 85, "Matched Sender Email ({$senderEmail})");
                    $this->addUniqueSuggestion($suggestions, $match);
                    if (!$bestMatch) {
                        $bestMatch = $match;
                    }
                }
            }
        }

        // 5. Fallback: run EmailMatchingService if available
        if (!$bestMatch && $this->matchingService) {
            try {
                $serviceResult = $this->matchingService->suggestMatches([
                    'subject' => $subject,
                    'sender_email' => $senderEmail,
                    'from_mail' => $senderEmail,
                    'text_preview' => $subject . ' ' . $senderName,
                ]);

                if (!empty($serviceResult['best']['client_matter_id'])) {
                    $matter = ClientMatter::with(['client', 'matter'])->find($serviceResult['best']['client_matter_id']);
                    if ($matter && $matter->client) {
                        $bestMatch = $this->formatMatterMatch(
                            $matter,
                            $serviceResult['confidence'] ?? 80,
                            implode(', ', $serviceResult['matched_by'] ?? ['Content match'])
                        );
                        $this->addUniqueSuggestion($suggestions, $bestMatch);
                    }
                }
            } catch (\Throwable $e) {
                OutlookAddinLogger::error('Email Matching Service Exception', [
                    'subject' => $subject,
                    'from_email' => $senderEmail,
                ], $e);
            }
        }

        OutlookAddinLogger::info('Match Evaluated', [
            'subject' => $subject,
            'from_email' => $senderEmail,
            'from_name' => $senderName,
            'matched' => $bestMatch !== null,
            'best_match' => $bestMatch ? ($bestMatch['label'] ?? null) : null,
            'suggestions_count' => count($suggestions),
        ]);

        return response()->json([
            'success' => true,
            'matched' => $bestMatch !== null,
            'best' => $bestMatch,
            'suggestions' => $suggestions,
        ]);
    }

    /**
     * Search matters for manual selection / override in the taskpane.
     */
    public function searchMatters(Request $request)
    {
        $q = trim((string) $request->input('q', ''));

        $query = ClientMatter::with(['client', 'matter'])
            ->whereHas('client', function ($clientQ) use ($q) {
                $clientQ->where('status', 1);
                if ($q) {
                    $clientQ->where(function ($sub) use ($q) {
                        $sub->where('client_id', 'like', "%{$q}%")
                            ->orWhere('first_name', 'like', "%{$q}%")
                            ->orWhere('last_name', 'like', "%{$q}%")
                            ->orWhere('email', 'like', "%{$q}%");
                    });
                }
            });

        if ($q) {
            $query->orWhere('client_unique_matter_no', 'like', "%{$q}%");
        }

        $matters = $query->orderBy('updated_at', 'desc')->limit(25)->get();

        $results = [];
        foreach ($matters as $m) {
            if ($m->client) {
                $results[] = $this->formatMatterMatch($m, 100, 'Search result');
            }
        }

        OutlookAddinLogger::info('Matter Search Queried', [
            'query' => $q,
            'result_count' => count($results),
        ]);

        return response()->json([
            'success' => true,
            'matters' => $results,
        ]);
    }

    /**
     * Save the email directly to the CRM client matter.
     * Accepts either a direct .eml file upload OR email JSON payload.
     */
    public function saveEmail(Request $request)
    {
        try {
            $clientId = (int) $request->input('client_id');
            $clientMatterId = (int) $request->input('client_matter_id');
            $mailType = strtolower(trim((string) $request->input('mail_type', 'inbox')));
            $staffId = (int) $request->input('staff_id');

            if ($mailType !== 'sent') {
                $mailType = 'inbox';
            }

            if ($clientId <= 0) {
                OutlookAddinLogger::error('Save Email Validation Failed', [
                    'reason' => 'Please select a Client before saving.',
                    'client_id' => $clientId,
                    'client_matter_id' => $clientMatterId,
                    'staff_id' => $staffId,
                ]);
                return response()->json([
                    'success' => false,
                    'error' => 'Please select a Client before saving.',
                ], 422);
            }

            // Authenticate staff for access check
            if ($staffId > 0 && (!Auth::guard('admin')->check() || Auth::guard('admin')->id() !== $staffId)) {
                $staff = Staff::find($staffId);
                if ($staff) {
                    Auth::guard('admin')->login($staff);
                }
            }

            if (!Auth::guard('admin')->check()) {
                if (app()->environment('local')) {
                    // Find first active admin staff to establish context in local dev/test
                    $fallbackStaff = Staff::where('status', 1)->first();
                    if ($fallbackStaff) {
                        Auth::guard('admin')->login($fallbackStaff);
                    }
                } else {
                    OutlookAddinLogger::error('Save Email Authentication Failed', [
                        'reason' => 'Authentication required: please select your staff profile or log into BansalLaw CRM.',
                        'client_id' => $clientId,
                        'staff_id' => $staffId,
                    ]);
                    return response()->json([
                        'success' => false,
                        'error' => 'Authentication required: please select your staff profile or log into BansalLaw CRM.',
                    ], 401);
                }
            }

            // Prepare attachment storage mapping (folder choices)
            $attachmentStorage = $request->input('attachment_storage');
            if (is_string($attachmentStorage)) {
                $attachmentStorage = json_decode($attachmentStorage, true);
            }
            if (!is_array($attachmentStorage)) {
                $attachmentStorage = [];
            }

            $defaultFolderChoice = (string) $request->input('default_attachment_folder', 'matter:1');
            $attachments = (array) $request->input('attachments', []);

            if (empty($attachmentStorage) && $defaultFolderChoice !== '') {
                $storageType = 'matter';
                $folderId = '1';
                if (str_contains($defaultFolderChoice, ':')) {
                    [$storageType, $folderId] = explode(':', $defaultFolderChoice, 2);
                } else {
                    $folderId = $defaultFolderChoice;
                }

                foreach ($attachments as $att) {
                    $fname = trim((string) ($att['name'] ?? ''));
                    if ($fname) {
                        $attachmentStorage[] = [
                            'original_filename' => $fname,
                            'filename' => $fname,
                            'storage_type' => $storageType,
                            'folder_id' => $folderId,
                        ];
                    }
                }
            }

            // Determine assignment tag: 'auto_assigned' (Auto assigned) vs 'manual_upload' (Manual upload)
            $isAutoAssigned = false;
            if ($request->has('is_auto_matched')) {
                $isAutoAssigned = filter_var($request->input('is_auto_matched'), FILTER_VALIDATE_BOOLEAN);
            } elseif ($request->input('assignment_type') === 'auto' || $request->input('sync_assignment_status') === 'auto_assigned') {
                $isAutoAssigned = true;
            } else {
                $isAutoAssigned = $this->isMatterAutoMatchedFromEmail(
                    (string) $request->input('subject', ''),
                    (string) $request->input('from_email', ''),
                    $clientMatterId
                );
            }

            $syncMeta = [
                'sync_assignment_status' => $isAutoAssigned ? 'auto_assigned' : null,
                'sync_source' => EmailLog::SYNC_SOURCE_UPLOAD,
            ];

            // Case A: A file is uploaded (.eml or .msg from getAsFileAsync)
            if ($request->hasFile('email_file')) {
                $file = $request->file('email_file');
                $result = $this->importEmailFromContext($file, $clientId, $mailType, $clientMatterId, 'client', $attachmentStorage, $syncMeta);

                if (!empty($result['success'])) {
                    OutlookAddinLogger::success('Save Email (Direct File Upload)', [
                        'client_id' => $clientId,
                        'client_matter_id' => $clientMatterId,
                        'mail_type' => $mailType,
                        'staff_id' => $staffId,
                        'file_name' => $file->getClientOriginalName(),
                        'document_id' => $result['document_id'] ?? null,
                        'email_log_id' => $result['email_log_id'] ?? null,
                        'attachments_stored' => count($attachmentStorage),
                        'assignment_tag' => $isAutoAssigned ? 'Auto assigned' : 'Manual upload',
                        'sync_assignment_status' => $syncMeta['sync_assignment_status'],
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Email saved to matter successfully!',
                        'detail_url' => $this->resolveMatterUrl($clientId, $clientMatterId),
                        'document_id' => $result['document_id'] ?? null,
                        'email_log_id' => $result['email_log_id'] ?? null,
                        'assignment_tag' => $isAutoAssigned ? 'Auto assigned' : 'Manual upload',
                        'is_auto_matched' => $isAutoAssigned,
                    ]);
                }

                OutlookAddinLogger::error('Save Email (Direct File Upload) Failed', [
                    'client_id' => $clientId,
                    'client_matter_id' => $clientMatterId,
                    'mail_type' => $mailType,
                    'staff_id' => $staffId,
                    'error' => $result['error'] ?? 'Failed to save email.',
                    'technical_error' => $result['technical_error'] ?? null,
                ]);

                return response()->json([
                    'success' => false,
                    'error' => $result['error'] ?? 'Failed to save email.',
                    'technical_error' => $result['technical_error'] ?? null,
                ], 422);
            }

            // Case B: JSON payload provided from Office.js fallback
            $subject = trim((string) $request->input('subject', 'Untitled Email'));
            $bodyHtml = (string) $request->input('body_html', '');
            $fromName = trim((string) $request->input('from_name', ''));
            $fromEmail = trim((string) $request->input('from_email', ''));
            $toRecipients = trim((string) $request->input('to_recipients', ''));
            $date = trim((string) $request->input('date', 'now'));

            $tempEmlPath = tempnam(sys_get_temp_dir(), 'crm_eml_') . '.eml';
            $emlContent = $this->constructEmlString($subject, $bodyHtml, $fromName, $fromEmail, $toRecipients, $date, $attachments);
            File::put($tempEmlPath, $emlContent);

            $sanitizedName = Str::slug(substr($subject, 0, 50)) ?: 'outlook_email';
            $uploadedFile = new UploadedFile(
                $tempEmlPath,
                $sanitizedName . '.eml',
                'message/rfc822',
                null,
                true
            );

            $result = $this->importEmailFromContext($uploadedFile, $clientId, $mailType, $clientMatterId, 'client', $attachmentStorage, $syncMeta);
            @unlink($tempEmlPath);

            if (!empty($result['success'])) {
                $attCount = count($attachments);
                $msg = $attCount > 0
                    ? "Email and {$attCount} attachment" . ($attCount > 1 ? 's' : '') . " saved to matter successfully!"
                    : "Email saved to matter successfully!";

                OutlookAddinLogger::success('Save Email (Office.js Payload)', [
                    'client_id' => $clientId,
                    'client_matter_id' => $clientMatterId,
                    'subject' => $subject,
                    'from_email' => $fromEmail,
                    'from_name' => $fromName,
                    'staff_id' => $staffId,
                    'mail_type' => $mailType,
                    'attachment_count' => $attCount,
                    'attachment_storage' => $attachmentStorage,
                    'document_id' => $result['document_id'] ?? null,
                    'email_log_id' => $result['email_log_id'] ?? null,
                    'assignment_tag' => $isAutoAssigned ? 'Auto assigned' : 'Manual upload',
                    'sync_assignment_status' => $syncMeta['sync_assignment_status'],
                ]);

                return response()->json([
                    'success' => true,
                    'message' => $msg,
                    'attachment_count' => $attCount,
                    'detail_url' => $this->resolveMatterUrl($clientId, $clientMatterId),
                    'document_id' => $result['document_id'] ?? null,
                    'email_log_id' => $result['email_log_id'] ?? null,
                    'assignment_tag' => $isAutoAssigned ? 'Auto assigned' : 'Manual upload',
                    'is_auto_matched' => $isAutoAssigned,
                ]);
            }

            OutlookAddinLogger::error('Save Email (Office.js Payload) Failed', [
                'client_id' => $clientId,
                'client_matter_id' => $clientMatterId,
                'subject' => $subject,
                'from_email' => $fromEmail,
                'staff_id' => $staffId,
                'error' => $result['error'] ?? 'Failed to save email.',
                'technical_error' => $result['technical_error'] ?? null,
            ]);

            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Failed to save email.',
                'technical_error' => $result['technical_error'] ?? null,
            ], 422);

        } catch (\Throwable $e) {
            OutlookAddinLogger::error('Save Email Exception', [
                'client_id' => $clientId ?? null,
                'client_matter_id' => $clientMatterId ?? null,
                'staff_id' => $staffId ?? null,
                'subject' => $subject ?? null,
            ], $e);

            return response()->json([
                'success' => false,
                'error' => 'An error occurred while saving the email: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Switch or authenticate staff user in the taskpane session.
     */
    public function setStaff(Request $request)
    {
        $staffId = (int) $request->input('staff_id');
        $staff = Staff::where('status', 1)->find($staffId);

        if (!$staff) {
            return response()->json(['success' => false, 'error' => 'Staff member not found.'], 404);
        }

        Auth::guard('admin')->login($staff);

        OutlookAddinLogger::info('Staff Switched', [
            'staff_id' => $staff->id,
            'staff_name' => $staff->first_name . ' ' . $staff->last_name,
            'email' => $staff->email,
        ]);

        return response()->json([
            'success' => true,
            'staff' => [
                'id' => $staff->id,
                'name' => $staff->first_name . ' ' . $staff->last_name,
                'email' => $staff->email,
            ],
        ]);
    }

    /**
     * Endpoint for the Outlook taskpane frontend to report client-side errors and events.
     */
    public function logClient(Request $request)
    {
        $level = strtolower((string) $request->input('level', 'info'));
        $action = (string) $request->input('action', 'Client Event');
        $context = (array) $request->input('context', []);
        $context['client_ip'] = $request->ip();

        if ($level === 'error') {
            OutlookAddinLogger::error($action, $context);
        } elseif ($level === 'success') {
            OutlookAddinLogger::success($action, $context);
        } else {
            OutlookAddinLogger::info($action, $context);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Build standard RFC822 .eml format string with optional attachments.
     */
    protected function constructEmlString(string $subject, string $bodyHtml, string $fromName, string $fromEmail, string $toRecipients, string $date, array $attachments = []): string
    {
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encodedFromName = $fromName ? '=?UTF-8?B?' . base64_encode($fromName) . '?= ' : '';
        $fromLine = $fromEmail ? "From: {$encodedFromName}<{$fromEmail}>" : "From: {$encodedFromName}<unknown@bansallawyers.com.au>";
        $toLine = $toRecipients ? "To: {$toRecipients}" : "To: undisclosed-recipients:;";
        $dateFormatted = date('r', strtotime($date ?: 'now'));

        // If no attachments, simple single-part HTML email
        if (empty($attachments)) {
            $lines = [
                $fromLine,
                $toLine,
                "Subject: {$encodedSubject}",
                "Date: {$dateFormatted}",
                "MIME-Version: 1.0",
                "Content-Type: text/html; charset=utf-8",
                "Content-Transfer-Encoding: base64",
                "",
                chunk_split(base64_encode($bodyHtml ?: '<p>(No content)</p>')),
            ];

            return implode("\r\n", $lines);
        }

        // Multi-part MIME with attachments
        $boundary = '=_BansalCrm_' . md5(uniqid((string) mt_rand(), true));

        $lines = [
            $fromLine,
            $toLine,
            "Subject: {$encodedSubject}",
            "Date: {$dateFormatted}",
            "MIME-Version: 1.0",
            "Content-Type: multipart/mixed; boundary=\"{$boundary}\"",
            "",
            "--{$boundary}",
            "Content-Type: text/html; charset=utf-8",
            "Content-Transfer-Encoding: base64",
            "",
            chunk_split(base64_encode($bodyHtml ?: '<p>(No content)</p>')),
        ];

        foreach ($attachments as $att) {
            $filename = trim((string) ($att['name'] ?? 'attachment'));
            $mimeType = trim((string) ($att['content_type'] ?? 'application/octet-stream'));
            $contentBase64 = (string) ($att['content_base64'] ?? '');

            if (!$contentBase64) {
                continue;
            }

            // Clean up any data URL prefix
            if (str_contains($contentBase64, ',')) {
                $contentBase64 = substr($contentBase64, strpos($contentBase64, ',') + 1);
            }

            $sanitizedFilename = preg_replace('/[^\w\.\-]/', '_', $filename);
            $lines[] = "--{$boundary}";
            $lines[] = "Content-Type: {$mimeType}; name=\"{$sanitizedFilename}\"";
            $lines[] = "Content-Transfer-Encoding: base64";
            $lines[] = "Content-Disposition: attachment; filename=\"{$sanitizedFilename}\"";
            $lines[] = "";
            $lines[] = chunk_split($contentBase64);
        }

        $lines[] = "--{$boundary}--";
        $lines[] = "";

        return implode("\r\n", $lines);
    }

    /**
     * Get available document folders for a client & matter.
     */
    public function folders(Request $request)
    {
        $clientId = (int) $request->input('client_id');
        $clientMatterId = (int) $request->input('client_matter_id');

        $service = app(\App\Services\Email\EmailOutlookViewService::class);
        $matterFolders = ($clientId > 0 && $clientMatterId > 0)
            ? $service->matterFolders($clientId, $clientMatterId)
            : [];

        if (empty($matterFolders)) {
            $matterFolders = \App\Models\VisaDocumentType::where('status', 1)
                ->whereNull('client_id')
                ->whereNull('client_matter_id')
                ->orderBy('id')
                ->get()
                ->map(fn ($r) => ['id' => (string) $r->id, 'title' => (string) $r->title])
                ->all();
        }

        $personalFolders = $clientId > 0 ? $service->personalFolders($clientId) : [];

        OutlookAddinLogger::info('Folders Queried', [
            'client_id' => $clientId,
            'client_matter_id' => $clientMatterId,
            'matter_folders_count' => count($matterFolders),
            'personal_folders_count' => count($personalFolders),
        ]);

        return response()->json([
            'success' => true,
            'matter_folders' => $matterFolders,
            'personal_folders' => $personalFolders,
        ]);
    }

    protected function formatMatterMatch(ClientMatter $matter, int $confidence, string $matchedBy): array
    {
        $client = $matter->client;
        $clientName = trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? ''));
        $clientRef = (string) ($client->client_id ?? '');
        $matterNo = (string) ($matter->client_unique_matter_no ?? '');
        $matterTitle = (string) ($matter->matter->title ?? $matter->case_detail ?? 'General Matter');

        $service = app(\App\Services\Email\EmailOutlookViewService::class);
        $matterFolders = $service->matterFolders((int) $client->id, (int) $matter->id);
        if (empty($matterFolders)) {
            $matterFolders = \App\Models\VisaDocumentType::where('status', 1)
                ->whereNull('client_id')
                ->whereNull('client_matter_id')
                ->orderBy('id')
                ->get()
                ->map(fn ($r) => ['id' => (string) $r->id, 'title' => (string) $r->title])
                ->all();
        }

        $personalFolders = $service->personalFolders((int) $client->id);

        return [
            'client_id' => $client->id,
            'client_matter_id' => $matter->id,
            'client_ref' => $clientRef,
            'client_name' => $clientName,
            'client_email' => $client->email ?? '',
            'matter_no' => $matterNo,
            'matter_title' => $matterTitle,
            'confidence' => $confidence,
            'matched_by' => $matchedBy,
            'label' => "{$clientRef} / {$matterNo} — {$clientName}",
            'matter_folders' => $matterFolders,
            'personal_folders' => $personalFolders,
        ];
    }

    protected function addUniqueSuggestion(array &$suggestions, array $newMatch): void
    {
        foreach ($suggestions as $existing) {
            if ($existing['client_matter_id'] === $newMatch['client_matter_id']) {
                return;
            }
        }
        $suggestions[] = $newMatch;
    }

    protected function resolveMatterUrl(int $clientId, int $clientMatterId): string
    {
        $matter = ClientMatter::find($clientMatterId);
        $encodedClientId = base64_encode((string) $clientId);
        $matterNo = $matter ? $matter->client_unique_matter_no : '';

        if ($matterNo) {
            return url("/clients/detail/{$encodedClientId}/{$matterNo}/emails");
        }

        return url("/clients/detail/{$encodedClientId}");
    }

    /**
     * Helper to check if an email automatically matches a given client matter
     * based on subject reference number, matter code, or client registered email.
     */
    protected function isMatterAutoMatchedFromEmail(string $subject, string $senderEmail, int $clientMatterId): bool
    {
        if ($clientMatterId <= 0) {
            return false;
        }

        $subject = trim($subject);
        $senderEmail = trim($senderEmail);

        $matter = ClientMatter::with('client')->find($clientMatterId);
        if (!$matter || !$matter->client) {
            return false;
        }

        $clientRef = strtoupper((string) ($matter->client->client_id ?? ''));
        $matterNo = strtoupper((string) ($matter->client_unique_matter_no ?? ''));

        // 1. Explicit pattern [CLIENT_REF] / [MATTER_NO]
        if ($clientRef && $matterNo && stripos($subject, $clientRef) !== false && stripos($subject, $matterNo) !== false) {
            return true;
        }

        // 2. Matter code alone in subject (e.g. FAM_1, CRM_1)
        if ($matterNo && preg_match('/\b' . preg_quote($matterNo, '/') . '\b/i', $subject)) {
            return true;
        }

        // 3. Sender email matches client email
        if ($senderEmail && !empty($matter->client->email) && strcasecmp($senderEmail, trim((string) $matter->client->email)) === 0) {
            return true;
        }

        return false;
    }

    /**
     * XML Manifest builder for Office.js Add-in.
     */
    public function buildManifestXml(string $baseUrl): string
    {
        if (str_starts_with($baseUrl, 'http://')) {
            $baseUrl = 'https://' . substr($baseUrl, 7);
        }
        $taskpaneUrl = "{$baseUrl}/outlook-addin/taskpane";
        $logoUrl = "{$baseUrl}/img/logo_new.png";

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<OfficeApp
  xmlns="http://schemas.microsoft.com/office/appforoffice/1.1"
  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
  xmlns:bt="http://schemas.microsoft.com/office/officeappbasictypes/1.0"
  xmlns:mailappversionoverrides="http://schemas.microsoft.com/office/mailappversionoverrides"
  xsi:type="MailApp">

  <Id>b29f0412-8d76-4c31-9f2a-7e1d5a89c34b</Id>
  <Version>1.0.0.0</Version>
  <ProviderName>Bansal Lawyers</ProviderName>
  <DefaultLocale>en-US</DefaultLocale>
  <DisplayName DefaultValue="Save to BansalLaw CRM" />
  <Description DefaultValue="Save and link Outlook emails directly to client matters in BansalLaw CRM without manual downloading or dragging." />
  <IconUrl DefaultValue="{$logoUrl}" />
  <HighResolutionIconUrl DefaultValue="{$logoUrl}" />
  <SupportUrl DefaultValue="{$baseUrl}" />

  <Hosts>
    <Host Name="Mailbox" />
  </Hosts>

  <Requirements>
    <Sets>
      <Set Name="Mailbox" MinVersion="1.5" />
    </Sets>
  </Requirements>

  <FormSettings>
    <Form xsi:type="ItemRead">
      <DesktopSettings>
        <SourceLocation DefaultValue="{$taskpaneUrl}" />
        <RequestedHeight>450</RequestedHeight>
      </DesktopSettings>
    </Form>
  </FormSettings>

  <Permissions>ReadWriteItem</Permissions>
  <Rule xsi:type="RuleCollection" Mode="Or">
    <Rule xsi:type="ItemIs" ItemType="Message" FormType="Read" />
  </Rule>
  <DisableEntityHighlighting>false</DisableEntityHighlighting>

  <VersionOverrides xmlns="http://schemas.microsoft.com/office/mailappversionoverrides" xsi:type="VersionOverridesV1_0">
    <VersionOverrides xmlns="http://schemas.microsoft.com/office/mailappversionoverrides/1.1" xsi:type="VersionOverridesV1_1">
      <Requirements>
        <bt:Sets DefaultMinVersion="1.5">
          <bt:Set Name="Mailbox" />
        </bt:Sets>
      </Requirements>
      <Hosts>
        <Host xsi:type="MailHost">
          <DesktopFormFactor>
            <ExtensionPoint xsi:type="MessageReadCommandSurface">
              <OfficeTab id="TabDefault">
                <Group id="msgReadGroup">
                  <Label resid="GroupLabel" />
                  <Control xsi:type="Button" id="msgReadOpenTaskpane">
                    <Label resid="TaskpaneButton.Label" />
                    <Supertip>
                      <Title resid="TaskpaneButton.Label" />
                      <Description resid="TaskpaneButton.Tooltip" />
                    </Supertip>
                    <Icon>
                      <bt:Image size="16" resid="Icon.16x16" />
                      <bt:Image size="32" resid="Icon.32x32" />
                      <bt:Image size="80" resid="Icon.80x80" />
                    </Icon>
                    <Action xsi:type="ShowTaskpane">
                      <SourceLocation resid="Taskpane.Url" />
                      <SupportsPinning>true</SupportsPinning>
                    </Action>
                  </Control>
                </Group>
              </OfficeTab>
            </ExtensionPoint>
          </DesktopFormFactor>
        </Host>
      </Hosts>

      <Resources>
        <bt:Images>
          <bt:Image id="Icon.16x16" DefaultValue="{$logoUrl}" />
          <bt:Image id="Icon.32x32" DefaultValue="{$logoUrl}" />
          <bt:Image id="Icon.80x80" DefaultValue="{$logoUrl}" />
        </bt:Images>
        <bt:Urls>
          <bt:Url id="Taskpane.Url" DefaultValue="{$taskpaneUrl}" />
        </bt:Urls>
        <bt:ShortStrings>
          <bt:String id="GroupLabel" DefaultValue="BansalLaw CRM" />
          <bt:String id="TaskpaneButton.Label" DefaultValue="Save to CRM" />
        </bt:ShortStrings>
        <bt:LongStrings>
          <bt:String id="TaskpaneButton.Tooltip" DefaultValue="Save this email directly to a Client Matter in BansalLaw CRM." />
        </bt:LongStrings>
      </Resources>
    </VersionOverrides>
  </VersionOverrides>

</OfficeApp>
XML;
    }
}
