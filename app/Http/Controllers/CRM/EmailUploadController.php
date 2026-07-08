<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Concerns\EnsuresCrmRecordAccess;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use App\Models\Document;
use App\Models\EmailLog;
use App\Models\ActivitiesLog;
use App\Models\ClientMatter;
use App\Models\Admin;
use App\Traits\LogsClientActivity;
use Illuminate\Support\Carbon;

/**
 * Modern Email Upload Controller
 * 
 * Uses Python microservice for .msg email parsing and PDF rendering.
 * This provides better performance, modern code, and PHP 8.2+ compatibility.
 */
class EmailUploadController extends Controller
{
    use EnsuresCrmRecordAccess;
    use LogsClientActivity;

    /**
     * Python service configuration
     */
    protected $pythonServiceUrl;
    protected int $pythonServiceTimeout;

    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->pythonServiceUrl = (string) config('services.python.url', env('PYTHON_SERVICE_URL', 'http://127.0.0.1:5002'));
        $this->pythonServiceTimeout = max(30, (int) config('services.python.timeout', env('PYTHON_SERVICE_TIMEOUT', 180)));
    }

    /**
     * S3 disk for .msg email uploads (bundled CA cert for local PHP without curl.cainfo).
     */
    protected function emailUploadStorage()
    {
        static $disk = null;

        if ($disk !== null) {
            return $disk;
        }

        $config = config('filesystems.disks.s3');
        $caBundle = resource_path('certs/cacert.pem');

        if (is_file($caBundle)) {
            $config['http'] = ['verify' => $caBundle];
        }

        $disk = Storage::build($config);

        return $disk;
    }

    /**
     * Append Laravel app timezone so Python formats email dates like the CRM (dd/mm/yyyy h:i a).
     */
    protected function pythonServiceUrlWithTimezone(string $path): string
    {
        $timezone = config('app.timezone', 'Australia/Melbourne');
        $separator = str_contains($path, '?') ? '&' : '?';

        return $this->pythonServiceUrl . $path . $separator . 'timezone=' . urlencode($timezone);
    }

    /**
     * Import a parsed .msg file with explicit client/matter context (smart import flow).
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return array{success: bool, document_id?: int, email_log_id?: int, error?: string}
     */
    public function importEmailFromContext($file, int $clientId, string $mailType, int $clientMatterId, string $recordType = 'client'): array
    {
        $this->ensureCrmRecordAccess($clientId);

        $clientInfo = Admin::select('client_id', 'type')->where('id', $clientId)->first();
        if (! $clientInfo) {
            return ['success' => false, 'error' => 'Client not found.'];
        }

        $clientUniqueId = (string) ($clientInfo->client_id ?? '');
        $resolvedType = in_array($clientInfo->type, ['client', 'lead'], true) ? $clientInfo->type : $recordType;

        $payload = [
            'client_id' => $clientId,
            'type' => $resolvedType,
        ];
        if ($mailType === 'sent') {
            $payload['upload_sent_mail_client_matter_id'] = $clientMatterId;
        } else {
            $payload['upload_inbox_mail_client_matter_id'] = $clientMatterId;
        }

        $request = Request::create('/', 'POST', $payload);

        return $this->processEmailFile($file, $clientId, $clientUniqueId, $mailType, $request);
    }

    /**
     * Upload and process inbox emails using Python microservice
     * 
     * Modern replacement for uploadfetchmail method
     */
    public function uploadInboxEmails(Request $request)
    {
        try {
            set_time_limit(180); // Increase max execution time for uploading emails

            // Validate file input
            $validationResponse = $this->validateEmailUploadRequest($request);
            if ($validationResponse) {
                return $validationResponse;
            }

            $this->ensureCrmRecordAccess((int) $request->client_id);

            $clientId = $request->client_id;
            $clientInfo = Admin::select('client_id')->where('id', $clientId)->first();
            $clientUniqueId = !empty($clientInfo) ? $clientInfo->client_id : "";

            if (!$request->hasfile('email_files')) {
                return response()->json([
                    'status' => false,
                    'message' => 'No files uploaded',
                ], 400);
            }

            // Check maximum file limit (10 emails max)
            $emailFiles = $request->file('email_files');
            $fileCount = is_array($emailFiles) ? count($emailFiles) : 0;
            
            if ($fileCount > 10) {
                return response()->json([
                    'status' => false,
                    'message' => 'Maximum 10 email files allowed per upload. Please select 10 or fewer files.',
                    'uploaded' => 0,
                    'failed' => 0,
                    'errors' => []
                ], 422);
            }

            $uploadedCount = 0;
            $failedCount = 0;
            $errors = [];

            foreach ($request->file('email_files') as $file) {
                try {
                    $result = $this->processEmailFile($file, $clientId, $clientUniqueId, 'inbox', $request);
                    
                    if ($result['success']) {
                        $uploadedCount++;
                    } else {
                        $failedCount++;
                        $errors[] = [
                            'filename' => $this->sanitizedUploadFilename($file),
                            'original_filename' => $file->getClientOriginalName(),
                            'error' => $result['error'],
                            'technical_error' => $result['technical_error'] ?? null,
                            'duplicate' => !empty($result['duplicate']),
                            'existing' => $result['existing'] ?? null,
                        ];
                    }
                } catch (\Exception $e) {
                    $failedCount++;
                    $fileName = $this->sanitizedUploadFilename($file);
                    $errorMsg = $e->getMessage();
                    
                    // Extract user-friendly error if available
                    $userError = $errorMsg;
                    if (is_array($errorMsg) && isset($errorMsg['error'])) {
                        $userError = $errorMsg['error'];
                    }
                    
                    $errors[] = [
                        'filename' => $fileName,
                        'original_filename' => $file->getClientOriginalName(),
                        'error' => $userError,
                        'file_size' => $file->getSize(),
                        'file_type' => $file->getMimeType()
                    ];
                    Log::error('Email upload error', [
                        'file' => $fileName,
                        'original_file' => $file->getClientOriginalName(),
                        'file_size' => $file->getSize(),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            // Build user-friendly message
            $message = '';
            $status = true;
            
            if ($uploadedCount > 0 && $failedCount == 0) {
                $message = "Successfully uploaded {$uploadedCount} email" . ($uploadedCount > 1 ? 's' : '');
                $status = true;
            } elseif ($uploadedCount > 0 && $failedCount > 0) {
                $message = "Partially successful: {$uploadedCount} email" . ($uploadedCount > 1 ? 's' : '') . " uploaded, {$failedCount} failed";
                $status = true; // Partial success is still considered success
            } elseif ($failedCount > 0) {
                $message = "Upload failed: {$failedCount} email" . ($failedCount > 1 ? 's' : '') . " could not be processed";
                $status = false;
            } else {
                $message = "No emails were processed";
                $status = false;
            }
            
            // Always return 200 so the JS processes the JSON body and shows
            // full per-file error details.  The `status` field signals success/failure.
            return response()->json([
                'status' => $status,
                'message' => $message,
                'uploaded' => $uploadedCount,
                'failed' => $failedCount,
                'errors' => $errors,
                'total_files' => $uploadedCount + $failedCount
            ], 200);

        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            
            // Make error messages more user-friendly
            if (strpos($errorMessage, 'Validation failed') !== false) {
                $errorMessage = 'File validation failed. Please ensure you\'re uploading Outlook email files only ('
                    . $this->emailUploadExtensionsLabel() . ', max 30MB each).';
            } elseif (strpos($errorMessage, 'No files uploaded') !== false) {
                $errorMessage = 'No files were selected for upload. Please select at least one Outlook email file.';
            }
            
            Log::error('Email upload error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_friendly_error' => $errorMessage
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Upload failed: ' . $errorMessage,
                'technical_error' => $e->getMessage()
            ], 200);
        }
    }

    /**
     * Upload and process sent emails using Python microservice
     */
    public function uploadSentEmails(Request $request)
    {
        try {
            set_time_limit(180); // Increase max execution time for uploading emails

            // Validate file input
            $validationResponse = $this->validateEmailUploadRequest($request);
            if ($validationResponse) {
                return $validationResponse;
            }

            $this->ensureCrmRecordAccess((int) $request->client_id);

            $clientId = $request->client_id;
            $clientInfo = Admin::select('client_id')->where('id', $clientId)->first();
            $clientUniqueId = !empty($clientInfo) ? $clientInfo->client_id : "";

            if (!$request->hasfile('email_files')) {
                return response()->json([
                    'status' => false,
                    'message' => 'No files uploaded',
                ], 400);
            }

            // Check maximum file limit (10 emails max)
            $emailFiles = $request->file('email_files');
            $fileCount = is_array($emailFiles) ? count($emailFiles) : 0;
            
            if ($fileCount > 10) {
                return response()->json([
                    'status' => false,
                    'message' => 'Maximum 10 email files allowed per upload. Please select 10 or fewer files.',
                    'uploaded' => 0,
                    'failed' => 0,
                    'errors' => []
                ], 422);
            }

            $uploadedCount = 0;
            $failedCount = 0;
            $errors = [];

            foreach ($request->file('email_files') as $file) {
                try {
                    $result = $this->processEmailFile($file, $clientId, $clientUniqueId, 'sent', $request);
                    
                    if ($result['success']) {
                        $uploadedCount++;
                    } else {
                        $failedCount++;
                        $errors[] = [
                            'filename' => $this->sanitizedUploadFilename($file),
                            'original_filename' => $file->getClientOriginalName(),
                            'error' => $result['error'] ?? 'Unknown error occurred while processing email',
                            'duplicate' => !empty($result['duplicate']),
                            'existing' => $result['existing'] ?? null,
                        ];
                    }
                } catch (\Exception $e) {
                    $failedCount++;
                    $fileName = $this->sanitizedUploadFilename($file);
                    $errorMsg = $e->getMessage();
                    
                    // Extract user-friendly error if available
                    $userError = $errorMsg;
                    if (is_array($errorMsg) && isset($errorMsg['error'])) {
                        $userError = $errorMsg['error'];
                    }
                    
                    $errors[] = [
                        'filename' => $fileName,
                        'error' => $userError,
                        'file_size' => $file->getSize(),
                        'file_type' => $file->getMimeType()
                    ];
                    Log::error('Email upload error', [
                        'file' => $fileName,
                        'file_size' => $file->getSize(),
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            // Build user-friendly message
            $message = '';
            $status = true;
            
            if ($uploadedCount > 0 && $failedCount == 0) {
                $message = "Successfully uploaded {$uploadedCount} email" . ($uploadedCount > 1 ? 's' : '');
                $status = true;
            } elseif ($uploadedCount > 0 && $failedCount > 0) {
                $message = "Partially successful: {$uploadedCount} email" . ($uploadedCount > 1 ? 's' : '') . " uploaded, {$failedCount} failed";
                $status = true; // Partial success is still considered success
            } elseif ($failedCount > 0) {
                $message = "Upload failed: {$failedCount} email" . ($failedCount > 1 ? 's' : '') . " could not be processed";
                $status = false;
            } else {
                $message = "No emails were processed";
                $status = false;
            }

            return response()->json([
                'status' => $status,
                'message' => $message,
                'uploaded' => $uploadedCount,
                'failed' => $failedCount,
                'errors' => $errors,
                'total_files' => $uploadedCount + $failedCount
            ], 200);

        } catch (\Exception $e) {
            Log::error('Sent email upload error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Upload failed: ' . $e->getMessage(),
            ], 200);
        }
    }

    /**
     * Parse a .msg/.eml file and return non-inline attachment metadata for storage prompts.
     */
    public function previewEmailAttachments(Request $request)
    {
        try {
            $validationResponse = $this->validateEmailUploadRequest($request);
            if ($validationResponse) {
                return $validationResponse;
            }

            $this->ensureCrmRecordAccess((int) $request->client_id);

            $file = $request->file('email_files')[0] ?? null;
            if (!$file) {
                return response()->json([
                    'status' => false,
                    'message' => 'No file uploaded',
                ], 400);
            }

            $parsedData = $this->parseEmailMetadataWithPython($file);
            if (!$parsedData || isset($parsedData['error']) || (isset($parsedData['success']) && !$parsedData['success'])) {
                return response()->json([
                    'status' => false,
                    'message' => $parsedData['error'] ?? 'Failed to parse email',
                    'technical_error' => $parsedData['technical_error'] ?? null,
                ], 400);
            }

            $attachments = [];
            foreach ($parsedData['attachments'] ?? [] as $index => $attachmentData) {
                if (!$this->shouldPromptAttachmentStorage($attachmentData)) {
                    continue;
                }
                $filename = $attachmentData['display_name'] ?? $attachmentData['filename'] ?? ('attachment_' . ($index + 1));
                $attachments[] = [
                    'index' => $index,
                    'filename' => $filename,
                    'display_name' => $filename,
                    'file_size' => $attachmentData['file_size'] ?? $attachmentData['size'] ?? 0,
                    'content_type' => $attachmentData['content_type'] ?? 'application/octet-stream',
                ];
            }

            return response()->json([
                'status' => true,
                'attachments' => $attachments,
                'has_attachments' => count($attachments) > 0,
            ]);
        } catch (\Exception $e) {
            Log::error('Preview email attachments error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to preview attachments: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Find an existing email log that matches the uploaded file for this client/matter.
     */
    protected function findExistingEmailLog(
        int $clientId,
        ?int $clientMatterId,
        string $mailType,
        string $recordType,
        array $parsedData,
        string $fileHash
    ): ?EmailLog {
        $query = EmailLog::query()
            ->where('client_id', $clientId)
            ->where('mail_body_type', $mailType)
            ->where('type', $recordType);

        if ($clientMatterId) {
            $query->where('client_matter_id', $clientMatterId);
        }

        $byHash = (clone $query)->where('file_hash', $fileHash)->first();
        if ($byHash) {
            return $byHash;
        }

        $messageId = trim((string) ($parsedData['message_id'] ?? ''));
        if ($messageId !== '') {
            $byMessageId = (clone $query)->where('message_id', $messageId)->first();
            if ($byMessageId) {
                return $byMessageId;
            }
        }

        $subject = trim((string) ($parsedData['subject'] ?? ''));
        $sender = trim((string) ($parsedData['sender_email'] ?? ''));
        if ($subject !== '' && $sender !== '') {
            $dupQuery = (clone $query)
                ->where('subject', $subject)
                ->where('from_mail', $sender);

            if (!empty($parsedData['sent_date'])) {
                $sentDate = $this->parseEmailDateTimeForStorage($parsedData['sent_date']);
                if ($sentDate) {
                    $dupQuery->where('fetch_mail_sent_time', $sentDate);
                }
            }

            $existing = $dupQuery->first();
            if ($existing) {
                return $existing;
            }
        }

        return null;
    }

    /**
     * Build a user-facing duplicate email message.
     */
    protected function buildDuplicateErrorMessage(EmailLog $existing): string
    {
        $subject = $existing->subject ?: '(No subject)';
        $from = $existing->from_mail ?: 'Unknown sender';
        $sent = $existing->fetch_mail_sent_time
            ? $existing->fetch_mail_sent_time->format('d/m/Y h:i a')
            : null;

        $message = 'This email already exists for this matter.';
        $message .= ' Subject: "' . $subject . '" from ' . $from;
        if ($sent) {
            $message .= ' (sent ' . $sent . ')';
        }

        return $message;
    }

    /**
     * Process individual email file using Python microservice
     * 
     * @param \Illuminate\Http\UploadedFile $file
     * @param int $clientId
     * @param string $clientUniqueId
     * @param string $mailType (inbox|sent)
     * @param Request $request
     * @return array
     */
    protected function processEmailFile($file, $clientId, $clientUniqueId, $mailType, $request)
    {
        try {
            $fileName = $this->sanitizedUploadFilename($file);
            $fileSize = $file->getSize();

            if ($fileSize <= 0) {
                throw new \Exception(
                    'The email file is empty (0 bytes). Browsers cannot read emails dragged directly from Outlook. '
                    . 'Save the email from Outlook as a .msg or .eml file, then upload the saved file.'
                );
            }
            
            // Sanitized name is used for S3 keys, document records, and Python parsing.
            $sanitizedFileName = $fileName;
            $uniqueFileName = time() . '_' . $sanitizedFileName;
            $docType = 'conversion_email_fetch';

            // 1. Parse email using Python microservice (before storage to allow duplicate check without S3 upload)
            $parsedData = $this->parseEmailWithPython($file);

            if (!$parsedData || isset($parsedData['error']) || (isset($parsedData['success']) && !$parsedData['success'])) {
                throw new \Exception($parsedData['error'] ?? 'Failed to parse email');
            }

            $fileHash = md5_file($file->getRealPath());
            $matterId = $mailType === 'sent'
                ? $request->upload_sent_mail_client_matter_id
                : $request->upload_inbox_mail_client_matter_id;
            $matterId = empty($matterId) ? null : (int) $matterId;

            if (!$request->boolean('force_upload')) {
                $existing = $this->findExistingEmailLog(
                    (int) $clientId,
                    $matterId,
                    $mailType,
                    $request->type ?? 'client',
                    $parsedData,
                    $fileHash
                );
                if ($existing) {
                    return [
                        'success' => false,
                        'duplicate' => true,
                        'error' => $this->buildDuplicateErrorMessage($existing),
                        'existing' => [
                            'id' => $existing->id,
                            'subject' => $existing->subject,
                            'from_mail' => $existing->from_mail,
                            'sent_date' => $existing->fetch_mail_sent_time
                                ? $existing->fetch_mail_sent_time->format('d/m/Y h:i a')
                                : null,
                        ],
                    ];
                }
            }
            
            // 2. Upload file to S3 (use sanitized filename in path)
            // Ensure all path components are sanitized to prevent 403 errors
            $sanitizedClientId = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $clientUniqueId);
            $filePath = $sanitizedClientId . '/' . $docType . '/' . $mailType . '/' . $uniqueFileName;
            
            // Upload to S3 with error handling
            try {
                $fileContents = file_get_contents($file->getPathname());
                if ($fileContents === false) {
                    throw new \Exception('Failed to read email file contents');
                }
                
                $uploadResult = $this->emailUploadStorage()->put($filePath, $fileContents);
                if (!$uploadResult) {
                    throw new \Exception('Failed to upload file to storage. Please check storage configuration.');
                }
            } catch (\Exception $s3Exception) {
                Log::error('S3 upload failed for email', [
                    'file' => $fileName,
                    's3_path' => $filePath,
                    'error' => $s3Exception->getMessage()
                ]);
                throw new \Exception('File storage error: ' . $s3Exception->getMessage());
            }
            
            // Generate S3 URL - use Storage method which handles encoding properly
            try {
                $fileUrl = $this->emailUploadStorage()->url($filePath);
                if (empty($fileUrl)) {
                    throw new \Exception('Failed to generate file URL');
                }
            } catch (\Exception $urlException) {
                Log::error('S3 URL generation failed', [
                    'file' => $fileName,
                    's3_path' => $filePath,
                    'error' => $urlException->getMessage()
                ]);
                throw new \Exception('File URL generation error: ' . $urlException->getMessage());
            }

            // 3. Save document record
            $document = new Document();
            $document->file_name = pathinfo($fileName, PATHINFO_FILENAME);
            $document->filetype = pathinfo($fileName, PATHINFO_EXTENSION);
            $document->user_id = Auth::user()->id;
            $document->myfile = $fileUrl;
            $document->myfile_key = $uniqueFileName;
            $document->client_id = $clientId;
            $document->type = $request->type;
            $document->mail_type = $mailType;
            $document->file_size = $fileSize;
            $document->doc_type = $docType;
            $matterId = $mailType === 'sent' 
                ? $request->upload_sent_mail_client_matter_id 
                : $request->upload_inbox_mail_client_matter_id;
            $document->client_matter_id = empty($matterId) ? null : $matterId;
            try {
                $document->save();
            } catch (QueryException $e) {
                Log::error('Failed to save Document record', [
                    'file' => $fileName,
                    'document_data' => $document->toArray(),
                    'error' => $e->getMessage(),
                    'error_info' => $e->errorInfo ?? []
                ]);
                throw new \Exception('Failed to save document record: ' . ($e->errorInfo[2] ?? $e->getMessage()));
            }

            $pdfDocumentId = $this->saveEmailPdfDocument(
                $parsedData,
                $fileName,
                $sanitizedClientId,
                $docType,
                $mailType,
                $uniqueFileName,
                $clientId,
                $request,
                $document->client_matter_id
            );

            // 4. Save to EmailLog
            $mailReport = new EmailLog();
            $mailReport->user_id = Auth::user()->id;
            $mailReport->from_mail = $parsedData['sender_email'] ?? '';
            $mailReport->to_mail = $this->formatParsedRecipientList($parsedData, 'to_recipients', 'recipients');
            $mailReport->cc = $this->formatParsedRecipientList($parsedData, 'cc_recipients');
            $mailReport->subject = $parsedData['subject'] ?? '';
            $mailReport->message = $this->sanitizeEmailBodyForStorage($parsedData['html_content'] ?? $parsedData['text_content'] ?? null); // Full body stored in database as requested
            $mailReport->mail_type = 1;
            $mailReport->type = $request->type; // Set type to 'client' or 'lead' as required by filter
            $mailReport->client_id = $clientId;
            $mailReport->conversion_type = $docType;
            $mailReport->mail_body_type = $mailType;
            $mailReport->uploaded_doc_id = $document->id;
            $mailReport->pdf_doc_id = $pdfDocumentId;
            $mailReport->client_matter_id = $document->client_matter_id;

            if (!empty($parsedData['text_preview'])) {
                $mailReport->text_preview = $parsedData['text_preview'];
            }
            
            // Sent time: store a real datetime for PostgreSQL — never locale strings like d/m/Y (DateStyle-dependent)
            if (!empty($parsedData['sent_date'])) {
                $mailReport->fetch_mail_sent_time = $this->parseEmailDateTimeForStorage($parsedData['sent_date']);
            }
            
            // NEW: Add Python AI analysis
            $analysisData = $this->analyzeEmailWithPython($parsedData);
            if ($analysisData && isset($analysisData['success']) && $analysisData['success']) {
                // Ensure JSON fields are properly formatted arrays (not objects or strings)
                $mailReport->python_analysis = is_array($analysisData) ? $analysisData : null;
                $mailReport->sentiment = $analysisData['sentiment'] ?? 'neutral';
                $mailReport->language = $analysisData['language'] ?? null;
                // Ensure these are arrays or null for JSON columns
                $mailReport->security_issues = isset($analysisData['security_issues']) 
                    ? (is_array($analysisData['security_issues']) ? $analysisData['security_issues'] : null)
                    : null;
                $mailReport->thread_info = isset($analysisData['thread_info'])
                    ? (is_array($analysisData['thread_info']) ? $analysisData['thread_info'] : null)
                    : null;
                $mailReport->processed_at = now();
            }
            
            // NEW: Add metadata
            $mailReport->message_id = $parsedData['message_id'] ?? null;
            $mailReport->thread_id = $parsedData['thread_id'] ?? null;
            
            if (!empty($parsedData['received_date'])) {
                $mailReport->received_date = $this->parseEmailDateTimeForStorage($parsedData['received_date']);
            } else {
                $mailReport->received_date = now();
            }
            
            $mailReport->file_hash = $fileHash;
            
            try {
                $mailReport->save();
            } catch (QueryException $e) {
                Log::error('Failed to save EmailLog record', [
                    'file' => $fileName,
                    'document_id' => $document->id,
                    'email_log_data' => $mailReport->toArray(),
                    'error' => $e->getMessage(),
                    'error_info' => $e->errorInfo ?? [],
                    'sql' => $e->getSql() ?? 'N/A'
                ]);
                throw new \Exception('Failed to save email record: ' . ($e->errorInfo[2] ?? $e->getMessage()));
            }

            $attachmentStorageMap = $this->parseAttachmentStorageMap($request);

            // NEW: Save attachments
            if (isset($parsedData['attachments']) && is_array($parsedData['attachments'])) {
                Log::info('Processing attachments', [
                    'count' => count($parsedData['attachments']),
                    'email_log_id' => $mailReport->id
                ]);
                
                foreach ($parsedData['attachments'] as $index => $attachmentData) {
                    try {
            $origName = $attachmentData['filename'] ?? '';
            $storageConfig = $attachmentStorageMap[$origName]
                ?? $attachmentStorageMap[$attachmentData['display_name'] ?? '']
                ?? null;
                        $this->saveAttachment(
                            $mailReport->id,
                            $attachmentData,
                            $clientUniqueId,
                            $storageConfig,
                            $request,
                            (int) $clientId,
                            $document->client_matter_id
                        );
                        
                        // Free memory for large attachments, useful if there are >10 attachments
                        unset($parsedData['attachments'][$index]['content']);
                        unset($parsedData['attachments'][$index]['data']);
                    } catch (\Exception $e) {
                        Log::error('Error in saveAttachment loop', [
                            'error' => $e->getMessage(),
                            'attachment' => $attachmentData['filename'] ?? 'unknown'
                        ]);
                        // Continue processing other attachments
                    }
                }
            } else {
                Log::info('No attachments found in parsed data', [
                    'has_attachments_key' => isset($parsedData['attachments']),
                    'email_log_id' => $mailReport->id
                ]);
            }

            // NEW: Auto-assign labels
            $this->autoAssignLabels($mailReport, $mailType);

            // 5. Update client matter timestamp
            $matterId = $document->client_matter_id;
            if (!empty($matterId)) {
                $matter = ClientMatter::find($matterId);
                if ($matter) {
                    $matter->updated_at = now();
                    $matter->save();
                }
            }

            // 6. Create activity log
            if ($request->type == 'client') {
                // Get matter reference
                $matterReference = '';
                if ($matterId) {
                    $matter = ClientMatter::find($matterId);
                    if ($matter && $matter->client_unique_matter_no) {
                        $matterReference = $matter->client_unique_matter_no;
                    }
                }
                
                // Fall back to latest active matter if none found
                if (empty($matterReference)) {
                    $latestMatter = ClientMatter::where('client_id', $clientId)
                        ->where('matter_status', 1)
                        ->orderBy('id', 'desc')
                        ->first();
                    if ($latestMatter && $latestMatter->client_unique_matter_no) {
                        $matterReference = $latestMatter->client_unique_matter_no;
                    }
                }
                
                // Format subject with matter reference
                $emailSubject = $parsedData['subject'] ?? 'Email';
                $subject = !empty($matterReference)
                    ? "uploaded Email: {$emailSubject} - {$matterReference}"
                    : "uploaded Email: {$emailSubject}";
                
                // Truncate long subjects
                if (strlen($subject) > 100) {
                    $subject = substr($subject, 0, 97) . '...';
                }
                
                $from = $parsedData['sender_email'] ?? $parsedData['sender_name'] ?? 'Unknown';
                $description = "<p>From: {$from}</p>";
                
                $this->logClientActivity(
                    $clientId,
                    $subject,
                    $description,
                    'email'
                );
            }

            return [
                'success' => true,
                'document_id' => $document->id,
                'email_log_id' => $mailReport->id
            ];

        } catch (\Illuminate\Database\QueryException $e) {
            $errorMessage = $e->getMessage();
            $fileName = $file->getClientOriginalName();
            
            // Extract more specific database error information
            $errorCode = $e->getCode();
            $errorInfo = $e->errorInfo ?? [];
            
            // PostgreSQL specific errors
            if (isset($errorInfo[0]) && $errorInfo[0] === '23502') {
                $errorMessage = "Database constraint error: Required field is missing. Please check the email data.";
            } elseif (isset($errorInfo[0]) && $errorInfo[0] === '23505') {
                $errorMessage = "Duplicate entry: This email may already exist in the database.";
            } elseif (isset($errorInfo[0]) && $errorInfo[0] === '22P02' || strpos($errorMessage, 'invalid input syntax') !== false) {
                $errorMessage = "Data format error: Invalid data format for one or more fields. The email may contain invalid characters or formatting.";
            } elseif (strpos($errorMessage, 'json') !== false || strpos($errorMessage, 'JSON') !== false) {
                $errorMessage = "JSON data error: Unable to save email metadata. Please try again or contact support.";
            } else {
                $errorMessage = "Database error: " . ($errorInfo[2] ?? $errorMessage);
            }
            
            Log::error('Process email file database error', [
                'file' => $fileName,
                'error' => $e->getMessage(),
                'error_code' => $errorCode,
                'error_info' => $errorInfo,
                'sql' => $e->getSql() ?? 'N/A',
                'bindings' => $e->getBindings() ?? [],
                'trace' => $e->getTraceAsString(),
                'user_friendly_error' => $errorMessage
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
                'technical_error' => $e->getMessage() // Include original for debugging
            ];
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $fileName = $file->getClientOriginalName();
            
            // Make error messages more user-friendly while preserving specific Python/service errors.
            if (strpos($errorMessage, 'Failed to connect') !== false || strpos($errorMessage, 'Connection refused') !== false) {
                $errorMessage = "Cannot connect to email processing service. Please ensure the Python service is running at {$this->pythonServiceUrl}";
            } elseif (
                $errorMessage === 'Failed to parse email'
                || $errorMessage === 'Email parsing failed'
            ) {
                $errorMessage = 'Failed to parse email file. The file may be corrupted or in an unsupported format.';
            } elseif (preg_match('/^Python service returned status:\s*\d+$/', $errorMessage)) {
                $errorMessage = 'Email processing service returned an error. Check that the Python service is running and up to date.';
            } elseif (stripos($errorMessage, 'timed out') !== false || stripos($errorMessage, 'timeout') !== false) {
                $errorMessage = 'Email processing timed out. The file may be very large — try again or upload a smaller .msg file.';
            } elseif (strpos($errorMessage, 'S3') !== false || strpos($errorMessage, 'AWS') !== false || strpos($errorMessage, 'storage') !== false) {
                $errorMessage = "File storage error. Please check S3 configuration or try again.";
            } elseif (strpos($errorMessage, 'database') !== false || strpos($errorMessage, 'SQL') !== false) {
                $errorMessage = "Database error. Please try again or contact support if the issue persists.";
            }
            
            Log::error('Process email file error', [
                'file' => $fileName,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
                'user_friendly_error' => $errorMessage
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
                'technical_error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Save generated PDF to S3 and create a documents row (soft-fail returns null).
     *
     * @return int|null Document id for the PDF, or null if PDF was not generated/saved
     */
    protected function saveEmailPdfDocument(
        array $parsedData,
        string $fileName,
        string $sanitizedClientId,
        string $docType,
        string $mailType,
        string $uniqueFileName,
        int $clientId,
        Request $request,
        $clientMatterId
    ): ?int {
        if (empty($parsedData['pdf_base64'])) {
            if (!empty($parsedData['pdf_error'])) {
                Log::warning('Email PDF not generated', [
                    'file' => $fileName,
                    'error' => $parsedData['pdf_error'],
                ]);
            }
            return null;
        }

        try {
            $pdfBytes = base64_decode($parsedData['pdf_base64'], true);
            if ($pdfBytes === false || strlen($pdfBytes) === 0) {
                Log::warning('Failed to decode email PDF from Python service', ['file' => $fileName]);
                return null;
            }

            $pdfUniqueFileName = preg_replace('/\.(msg|eml)$/i', '.pdf', $uniqueFileName);
            if ($pdfUniqueFileName === $uniqueFileName) {
                $pdfUniqueFileName = pathinfo($uniqueFileName, PATHINFO_FILENAME) . '.pdf';
            }

            $pdfFilePath = $sanitizedClientId . '/' . $docType . '/' . $mailType . '/' . $pdfUniqueFileName;

            $uploadResult = $this->emailUploadStorage()->put($pdfFilePath, $pdfBytes);
            if (!$uploadResult) {
                Log::warning('Failed to upload email PDF to S3', [
                    'file' => $fileName,
                    's3_path' => $pdfFilePath,
                ]);
                return null;
            }

            $pdfFileUrl = $this->emailUploadStorage()->url($pdfFilePath);

            $pdfDocument = new Document();
            $pdfDocument->file_name = pathinfo($fileName, PATHINFO_FILENAME);
            $pdfDocument->filetype = 'pdf';
            $pdfDocument->user_id = Auth::user()->id;
            $pdfDocument->myfile = $pdfFileUrl;
            $pdfDocument->myfile_key = $pdfUniqueFileName;
            $pdfDocument->client_id = $clientId;
            $pdfDocument->type = $request->type;
            $pdfDocument->mail_type = $mailType;
            $pdfDocument->file_size = strlen($pdfBytes);
            $pdfDocument->doc_type = $docType;
            $pdfDocument->client_matter_id = $clientMatterId;
            $pdfDocument->save();

            return (int) $pdfDocument->id;
        } catch (\Exception $e) {
            Log::warning('Email PDF save failed (upload continues)', [
                'file' => $fileName,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Parse email metadata only (no PDF render) — used for attachment preview before upload.
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return array|null
     */
    protected function parseEmailMetadataWithPython($file)
    {
        return $this->callPythonEmailEndpoint($file, '/email/parse', $this->pythonServiceTimeout, true);
    }

    /**
     * Parse email file using Python microservice
     * 
     * @param \Illuminate\Http\UploadedFile $file
     * @return array|null
     */
    protected function parseEmailWithPython($file)
    {
        return $this->callPythonEmailEndpoint(
            $file,
            '/email/parse-render-pdf',
            max($this->pythonServiceTimeout, 180)
        );
    }

    /**
     * @return array{success: false, error: string, technical_error?: string}|array<string, mixed>
     */
    protected function callPythonEmailEndpoint($file, string $path, int $timeout, bool $metadataOnly = false)
    {
        try {
            $originalFileName = $this->sanitizedUploadFilename($file);
            $sanitizedFileName = $originalFileName;
            $fileContents = file_get_contents($file->getPathname());

            if ($fileContents === false || $fileContents === '') {
                return [
                    'success' => false,
                    'error' => 'Uploaded file is empty or could not be read.',
                ];
            }

            $payload = [
                'timezone' => config('app.timezone', 'Australia/Melbourne'),
            ];
            if ($metadataOnly) {
                $payload['metadata_only'] = '1';
            }

            $response = Http::timeout($timeout)
                ->attach('file', $fileContents, $sanitizedFileName)
                ->post($this->pythonServiceUrlWithTimezone($path), $payload);

            if ($response->successful()) {
                try {
                    $result = $response->json();
                } catch (\Exception $jsonException) {
                    Log::error('Failed to parse Python service response as JSON', [
                        'path' => $path,
                        'status' => $response->status(),
                        'content_type' => $response->header('Content-Type'),
                        'body_preview' => substr($response->body(), 0, 500),
                        'error' => $jsonException->getMessage(),
                    ]);

                    return [
                        'success' => false,
                        'error' => 'Invalid response from email processing service. The service may be experiencing issues.',
                    ];
                }

                if (isset($result['error']) || (isset($result['success']) && ! $result['success'])) {
                    $technicalError = (string) ($result['error'] ?? $result['detail'] ?? 'Email parsing failed');

                    return [
                        'success' => false,
                        'error' => $technicalError,
                        'technical_error' => $technicalError,
                    ];
                }

                return $result;
            }

            $technicalError = $this->extractPythonServiceError($response);

            Log::error('Python service error', [
                'path' => $path,
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 1000),
                'technical_error' => $technicalError,
            ]);

            return [
                'success' => false,
                'error' => $technicalError,
                'technical_error' => $technicalError,
            ];
        } catch (\Exception $e) {
            Log::error('Python service connection error', [
                'path' => $path,
                'error' => $e->getMessage(),
                'url' => $this->pythonServiceUrl,
            ]);

            return [
                'success' => false,
                'error' => 'Failed to connect to Python service: ' . $e->getMessage(),
                'technical_error' => $e->getMessage(),
            ];
        }
    }

    protected function extractPythonServiceError(\Illuminate\Http\Client\Response $response): string
    {
        $body = trim((string) $response->body());

        if ($body !== '') {
            try {
                $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    foreach (['error', 'detail', 'message'] as $key) {
                        if (! empty($decoded[$key]) && is_string($decoded[$key])) {
                            return $decoded[$key];
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Fall through to status-based message.
            }
        }

        if ($response->status() === 400) {
            return 'Invalid email file type or payload rejected by the email processing service.';
        }

        if ($response->status() === 413) {
            return 'Email file is too large for the processing service.';
        }

        if ($response->status() >= 500) {
            return 'Email processing service error (HTTP ' . $response->status() . '). Ensure the Python service is running and up to date.';
        }

        return 'Python service returned status: ' . $response->status();
    }

    /**
     * Check if Python service is available
     * 
     * @return array
     */
    public function checkPythonService()
    {
        try {
            $response = Http::timeout(5)->get($this->pythonServiceUrl . '/health');

            return [
                'status' => $response->successful(),
                'url' => $this->pythonServiceUrl,
                'response' => $response->successful() ? $response->json() : null
            ];

        } catch (\Exception $e) {
            return [
                'status' => false,
                'url' => $this->pythonServiceUrl,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Analyze email content with Python AI service
     * 
     * @param array $parsedData
     * @return array|null
     */
    protected function analyzeEmailWithPython($parsedData)
    {
        try {
            $response = Http::timeout(30)->post($this->pythonServiceUrl . '/email/analyze', [
                'subject' => $parsedData['subject'] ?? '',
                'text_content' => $parsedData['text_content'] ?? '',
                'html_content' => $parsedData['html_content'] ?? '',
                'sender_email' => $parsedData['sender_email'] ?? '',
                'recipients' => $parsedData['recipients'] ?? [],
            ]);

            if ($response->successful()) {
                return $response->json();
            }
            
            Log::warning('Python analyzer service unavailable', ['status' => $response->status()]);
            return null;
        } catch (\Exception $e) {
            Log::warning('Python analyzer service error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Parse attachment storage preferences sent from the upload UI.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function parseAttachmentStorageMap(Request $request): array
    {
        if (!$request->filled('attachment_storage')) {
            return [];
        }

        $decoded = json_decode((string) $request->input('attachment_storage'), true);
        if (!is_array($decoded)) {
            return [];
        }

        $map = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }
            $key = $item['original_filename'] ?? $item['filename'] ?? null;
            if ($key) {
                $map[$key] = $item;
            }
        }

        return $map;
    }

    /**
     * Save attachment to database and S3
     * 
     * @param int $mailReportId
     * @param array $attachmentData
     * @param string $clientUniqueId
     * @param array|null $storageConfig
     * @param Request|null $request
     * @param int|null $clientId
     * @param int|null $clientMatterId
     */
    protected function saveAttachment(
        $mailReportId,
        $attachmentData,
        $clientUniqueId,
        $storageConfig = null,
        $request = null,
        $clientId = null,
        $clientMatterId = null
    )
    {
        $s3Path = null;
        $s3Key = null;
        $fileSize = $attachmentData['file_size'] ?? $attachmentData['size'] ?? 0;
        $originalFilename = $attachmentData['filename'] ?? 'unknown';
        $contentType = $attachmentData['content_type'] ?? 'application/octet-stream';
        $customStem = is_array($storageConfig) && !empty($storageConfig['file_name'])
            ? (string) $storageConfig['file_name']
            : null;
        $displayName = $this->buildAttachmentDisplayName($originalFilename, $contentType, $customStem);
        
        try {
            // Check for both 'content' and 'data' keys (Python service uses 'data')
            $attachmentContent = $attachmentData['content'] ?? $attachmentData['data'] ?? null;
            
            Log::info('Processing attachment data', [
                'filename' => $attachmentData['filename'] ?? 'unknown',
                'has_content' => !empty($attachmentContent),
                'content_length' => !empty($attachmentContent) ? strlen($attachmentContent) : 0,
                'expected_size' => $fileSize
            ]);
            
            $decodedData = null;
            if (!empty($attachmentContent)) {
                // Decode base64-encoded attachment data
                $decodedData = base64_decode($attachmentContent, true);
                
                // Validate base64 decode succeeded
                if ($decodedData === false) {
                    Log::warning('Failed to decode base64 attachment data', [
                        'filename' => $attachmentData['filename'] ?? 'unknown',
                        'content_length' => strlen($attachmentContent)
                    ]);
                    // Continue to create attachment record without file
                } else {
                    // Validate decoded data size matches expected size (with some tolerance for base64 padding)
                    $expectedSize = $fileSize;
                    $actualSize = strlen($decodedData);
                    
                    // Allow up to 3 bytes difference (base64 padding can cause small differences)
                    if ($expectedSize > 0) {
                        $sizeDifference = abs($actualSize - $expectedSize);
                        if ($sizeDifference > 3) {
                            Log::warning('Attachment size mismatch', [
                                'filename' => $attachmentData['filename'] ?? 'unknown',
                                'expected' => $expectedSize,
                                'actual' => $actualSize,
                                'difference' => $sizeDifference
                            ]);
                            // Continue anyway, but log the warning
                        }
                    }
                    
                    // Validate minimum size (empty files are suspicious)
                    if ($actualSize === 0) {
                        Log::warning('Decoded attachment data is empty', [
                            'filename' => $attachmentData['filename'] ?? 'unknown'
                        ]);
                        $decodedData = null;
                    }
                }
            } else {
                Log::info('Attachment has no content data, creating record without file', [
                    'filename' => $attachmentData['filename'] ?? 'unknown'
                ]);
            }

            $storageType = is_array($storageConfig) ? ($storageConfig['storage_type'] ?? 'email') : 'email';
            if ($decodedData !== null && in_array($storageType, ['personal', 'matter'], true)) {
                $docResult = $this->saveEmailAttachmentAsDocument(
                    $attachmentData,
                    $storageConfig,
                    $clientUniqueId,
                    (int) $clientId,
                    $clientMatterId ? (int) $clientMatterId : null,
                    $request?->type ?? 'client',
                    $decodedData
                );
                if ($docResult) {
                    $s3Path = $docResult['file_path'];
                    $s3Key = $docResult['s3_key'];
                    $fileSize = $docResult['file_size'];
                    $displayName = $docResult['display_name'];
                }
            } elseif ($decodedData !== null) {
                // Default: store under client attachments path (email-only)
                $attachmentFileName = $displayName;
                $sanitizedAttachmentFileName = $this->sanitizeFilename($attachmentFileName);
                $s3Key = $clientUniqueId . '/attachments/' . time() . '_' . uniqid() . '_' . $sanitizedAttachmentFileName;

                try {
                    $uploadSuccess = $this->emailUploadStorage()->put($s3Key, $decodedData);
                    if (!$uploadSuccess) {
                        throw new \Exception('S3 upload returned false');
                    }
                    if (!$this->emailUploadStorage()->exists($s3Key)) {
                        throw new \Exception('File not found in S3 after upload');
                    }
                    $s3Path = $this->emailUploadStorage()->url($s3Key);
                    $fileSize = strlen($decodedData);
                } catch (\Exception $s3Exception) {
                    Log::error('S3 upload failed for attachment', [
                        'filename' => $attachmentData['filename'] ?? 'unknown',
                        's3_key' => $s3Key,
                        'error' => $s3Exception->getMessage(),
                    ]);
                    $s3Key = null;
                    $s3Path = null;
                }
            }

            // Always create attachment record (even if file upload failed)
            $resolvedExtension = pathinfo($displayName, PATHINFO_EXTENSION);
            if ($resolvedExtension === '') {
                $resolvedExtension = $this->resolveAttachmentExtension($displayName, $contentType);
            }

            \App\Models\EmailLogAttachment::create([
                'email_log_id' => $mailReportId,
                'filename' => $displayName,
                'display_name' => $displayName,
                'content_type' => $contentType,
                'file_path' => $s3Path,
                's3_key' => $s3Key,
                'file_size' => $decodedData !== null ? strlen($decodedData) : $fileSize,
                'content_id' => $attachmentData['content_id'] ?? null,
                'is_inline' => $attachmentData['is_inline'] ?? false,
                'extension' => $resolvedExtension,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to save attachment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'attachment' => $attachmentData['filename'] ?? 'unknown'
            ]);
            // Don't re-throw - allow email upload to continue even if attachment fails
            // Attachment record will still be created (if we got that far) but without file
        }
    }

    /**
     * Store an email attachment in personal or matter document folders.
     *
     * @return array{file_path: string, s3_key: string, file_size: int, display_name: string}|null
     */
    protected function saveEmailAttachmentAsDocument(
        array $attachmentData,
        array $storageConfig,
        string $clientUniqueId,
        int $clientId,
        ?int $clientMatterId,
        string $recordType,
        string $decodedData
    ): ?array {
        $storageType = $storageConfig['storage_type'] ?? '';
        if (!in_array($storageType, ['personal', 'matter'], true)) {
            return null;
        }

        $folderId = (string) ($storageConfig['folder_id'] ?? '');
        if ($folderId === '') {
            return null;
        }

        $originalFilename = $attachmentData['filename'] ?? 'attachment';
        $contentType = $attachmentData['content_type'] ?? 'application/octet-stream';
        $displayName = $this->buildAttachmentDisplayName(
            $originalFilename,
            $contentType,
            (string) ($storageConfig['file_name'] ?? pathinfo($originalFilename, PATHINFO_FILENAME))
        );
        $extension = $this->resolveAttachmentExtension($displayName, $contentType);
        $customStem = pathinfo($displayName, PATHINFO_FILENAME);

        $sanitizedClientId = preg_replace('/[^a-zA-Z0-9\-_\.]/', '_', $clientUniqueId);
        $uniqueFileName = time() . '_' . uniqid() . '_' . $this->sanitizeFilename($displayName);
        $filePath = $sanitizedClientId . '/' . $storageType . '/' . $uniqueFileName;

        $uploadSuccess = $this->emailUploadStorage()->put($filePath, $decodedData);
        if (!$uploadSuccess) {
            throw new \Exception('Failed to upload attachment to document storage.');
        }

        $fileUrl = $this->emailUploadStorage()->url($filePath);
        $fileSize = strlen($decodedData);

        $document = new Document();
        $document->file_name = $customStem;
        $document->filetype = $extension ?: pathinfo($displayName, PATHINFO_EXTENSION);
        $document->user_id = Auth::user()->id;
        $document->myfile = $fileUrl;
        $document->myfile_key = $uniqueFileName;
        $document->client_id = $clientId;
        $document->type = $recordType;
        $document->file_size = $fileSize;
        $document->doc_type = $storageType;
        $document->folder_name = $folderId;
        $document->checklist = $customStem;
        $document->client_matter_id = $storageType === 'matter' ? $clientMatterId : null;
        $document->save();

        return [
            'file_path' => $fileUrl,
            's3_key' => $filePath,
            'file_size' => $fileSize,
            'display_name' => $displayName,
        ];
    }

    /**
     * Sanitize a user-provided attachment display/file name.
     */
    protected function sanitizeAttachmentDisplayName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/[^a-zA-Z0-9_\-\.\s\$\(\),&+]/', '_', $name);
        $name = preg_replace('/_+/', '_', trim((string) $name, '_'));

        return $name !== '' ? $name : 'attachment';
    }

    /**
     * Infer a file extension from MIME type when Outlook omits it from the filename.
     */
    protected function resolveAttachmentExtension(string $filename, ?string $contentType = null): string
    {
        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        if ($extension !== '') {
            return preg_replace('/[^a-z0-9]/', '', $extension);
        }

        $mime = strtolower(trim((string) $contentType));
        if ($mime !== '' && str_contains($mime, ';')) {
            $mime = trim(strtok($mime, ';'));
        }

        $map = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/pjpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/bmp' => 'bmp',
            'image/webp' => 'webp',
            'image/tiff' => 'tif',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        ];

        return $map[$mime] ?? '';
    }

    /**
     * Build a display/storage filename with a usable extension for image and document attachments.
     */
    protected function buildAttachmentDisplayName(string $filename, ?string $contentType, ?string $customStem = null): string
    {
        $originalFilename = $filename !== '' ? $filename : 'attachment';
        $extension = $this->resolveAttachmentExtension($originalFilename, $contentType);

        if ($extension === '') {
            $extension = $this->resolveAttachmentExtension('file.' . pathinfo($originalFilename, PATHINFO_EXTENSION), $contentType);
        }

        $stemSource = $customStem ?? pathinfo($originalFilename, PATHINFO_FILENAME);
        $stem = $this->sanitizeAttachmentDisplayName((string) $stemSource);
        if ($stem === '') {
            $stem = 'attachment';
        }

        return $extension !== '' ? ($stem . '.' . $extension) : $stem;
    }

    /**
     * Strip null bytes from email body content before PostgreSQL storage.
     */
    protected function sanitizeEmailBodyForStorage(?string $content): ?string
    {
        if ($content === null || $content === '') {
            return $content;
        }

        return str_replace("\0", '', $content);
    }

    /**
     * Auto-assign labels based on sender domain
     * 
     * @param \App\Models\EmailLog $mailReport
     * @param string $mailType
     */
    protected function autoAssignLabels($mailReport, $mailType)
    {
        try {
            // Company domains that indicate emails WE sent
            $companyDomains = [
                '@bansalimmigration.com.au',
                '@bansaleducation.com.au',
                '@bansallawyers.com.au'
            ];
            
            // Check if email is from our company domains
            $isFromCompany = false;
            $senderEmail = strtolower($mailReport->from_mail);
            
            foreach ($companyDomains as $domain) {
                if (str_contains($senderEmail, $domain)) {
                    $isFromCompany = true;
                    break;
                }
            }
            
            // Assign "Sent" label if from company domain, otherwise "Inbox" label
            $labelName = $isFromCompany ? 'Sent' : 'Inbox';
            
            $label = \App\Models\EmailLabel::where('name', $labelName)
                ->where('type', 'system')
                ->first();
            
            if ($label) {
                $mailReport->labels()->attach($label->id);
                
                Log::info('Auto-assigned label', [
                    'email_id' => $mailReport->id,
                    'sender' => $mailReport->from_mail,
                    'label' => $labelName,
                    'is_from_company' => $isFromCompany
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to auto-assign label', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Sanitize filename for use in S3 file paths
     * Prevents 403 errors caused by special characters in filenames
     * 
     * @param string $filename Original filename
     * @return string Sanitized filename safe for S3 paths
     */
    protected function sanitizeFilename(string $filename): string
    {
        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        $extension = preg_replace('/[^a-z0-9]/', '', $extension);
        $nameWithoutExt = (string) pathinfo($filename, PATHINFO_FILENAME);

        // Keep only filesystem-safe characters; spaces and symbols become underscores.
        $sanitizedName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $nameWithoutExt);
        $sanitizedName = preg_replace('/_+/', '_', $sanitizedName);
        $sanitizedName = trim($sanitizedName, '_');

        if ($sanitizedName === '') {
            $sanitizedName = 'email_' . time();
        }

        $sanitizedFilename = $extension !== '' ? $sanitizedName . '.' . $extension : $sanitizedName;

        if (strlen($sanitizedFilename) > 255) {
            $maxNameLength = 255 - strlen($extension) - ($extension !== '' ? 1 : 0);
            if ($maxNameLength > 0) {
                $sanitizedName = substr($sanitizedName, 0, $maxNameLength);
                $sanitizedFilename = $extension !== '' ? $sanitizedName . '.' . $extension : $sanitizedName;
            } else {
                $sanitizedFilename = 'email_' . time() . ($extension !== '' ? '.' . $extension : '');
            }
        }

        return $sanitizedFilename;
    }

    protected function sanitizedUploadFilename($file): string
    {
        $resolvedName = $this->resolveEmailUploadFilename($file);

        return $this->sanitizeFilename($resolvedName !== '' ? $resolvedName : 'email.eml');
    }

    /**
     * Join parsed recipient lists from the Python service for email_logs storage.
     *
     * @param array  $parsedData
     * @param string $primaryKey   e.g. to_recipients, cc_recipients
     * @param string|null $fallbackKey Legacy key (recipients = To only)
     */
    protected function formatParsedRecipientList(array $parsedData, string $primaryKey, ?string $fallbackKey = null): string
    {
        $list = $parsedData[$primaryKey] ?? null;
        if ((! is_array($list) || $list === []) && $fallbackKey !== null) {
            $list = $parsedData[$fallbackKey] ?? [];
        }
        if (! is_array($list) || $list === []) {
            return '';
        }

        $normalized = [];
        foreach ($list as $entry) {
            if ($entry === null) {
                continue;
            }
            $entry = trim((string) $entry);
            if ($entry === '' || str_contains($entry, 'object at 0x')) {
                continue;
            }
            $normalized[] = $entry;
        }

        return $normalized !== [] ? implode(',', array_values(array_unique($normalized))) : '';
    }

    /**
     * Parse date strings from the Python parser into a Carbon instance for DB storage.
     * Never pass locale display strings (e.g. d/m/Y) to PostgreSQL — server DateStyle (MDY vs DMY) differs
     * between machines; bind proper timestamps only.
     */
    protected function parseEmailDateTimeForStorage(string $dateString): Carbon
    {
        $dateString = trim($dateString);
        $appTimezone = config('app.timezone', 'Australia/Melbourne');

        if ($dateString === '') {
            return now($appTimezone);
        }

        try {
            if (preg_match('/^\d{4}-\d{2}-\d{2}T/', $dateString) || preg_match('/[+-]\d{2}:\d{2}$|Z$/', $dateString)) {
                return Carbon::parse($dateString)->timezone($appTimezone);
            }
        } catch (\Throwable $e) {
            // fall through to explicit formats
        }

        $formats = ['d/m/Y h:i a', 'd/m/Y g:i a', 'd/m/Y H:i', 'Y-m-d H:i:s'];
        foreach ($formats as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, $dateString, new \DateTimeZone('UTC'));
            if ($dt !== false) {
                return Carbon::instance($dt)->timezone($appTimezone);
            }
        }

        try {
            return Carbon::parse($dateString, 'UTC')->timezone($appTimezone);
        } catch (\Throwable $e) {
            Log::warning('Could not parse email date, using now()', ['raw' => $dateString, 'error' => $e->getMessage()]);

            return now($appTimezone);
        }
    }

    /**
     * Validate email upload payload and enforce allowed Outlook email extensions.
     *
     * Relying on MIME-only validation can reject valid Outlook files because
     * some systems report .msg as generic application/octet-stream.
     *
     * @return list<string>
     */
    protected function allowedEmailUploadExtensions(): array
    {
        $extensions = config('crm.email_upload_allowed_extensions', ['msg', 'eml']);

        return is_array($extensions) && $extensions !== []
            ? array_values(array_unique(array_map(
                static fn ($ext) => strtolower(ltrim((string) $ext, '.')),
                $extensions
            )))
            : ['msg', 'eml'];
    }

    protected function emailUploadExtensionsLabel(): string
    {
        return implode(', ', array_map(
            static fn (string $ext) => '.' . $ext,
            $this->allowedEmailUploadExtensions()
        ));
    }

    protected function isAllowedEmailUploadExtension(string $extension): bool
    {
        return in_array(
            strtolower(ltrim($extension, '.')),
            $this->allowedEmailUploadExtensions(),
            true
        );
    }

    /**
     * Detect .msg vs .eml from file contents when drag-and-drop omits the extension.
     */
    protected function detectEmailUploadExtension($file): ?string
    {
        $originalExtension = strtolower(ltrim((string) $file->getClientOriginalExtension(), '.'));
        if ($this->isAllowedEmailUploadExtension($originalExtension)) {
            return $originalExtension;
        }

        $path = $file->getRealPath();
        if (!$path || !is_readable($path)) {
            return null;
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return null;
        }

        $header = fread($handle, 4096) ?: '';
        fclose($handle);

        if (strlen($header) >= 4 && $header[0] === "\xD0" && $header[1] === "\xCF" && $header[2] === "\x11" && $header[3] === "\xE0") {
            return 'msg';
        }

        if (preg_match('/^(From:|Return-Path:|Received:|MIME-Version:|Date:|X-)/im', $header)) {
            return 'eml';
        }

        return null;
    }

    protected function resolveEmailUploadFilename($file): string
    {
        $originalName = $file instanceof \Illuminate\Http\UploadedFile
            ? (string) $file->getClientOriginalName()
            : (string) $file;

        $originalName = $originalName !== '' ? $originalName : 'email.eml';
        $extension = strtolower(ltrim((string) pathinfo($originalName, PATHINFO_EXTENSION), '.'));

        if (!$this->isAllowedEmailUploadExtension($extension) && $file instanceof \Illuminate\Http\UploadedFile) {
            $detected = $this->detectEmailUploadExtension($file);
            if ($detected) {
                $stem = pathinfo($originalName, PATHINFO_FILENAME);
                $stem = $stem !== '' ? $stem : 'email_' . time();

                return $stem . '.' . $detected;
            }
        }

        return $originalName;
    }

    /**
     * Decide whether an attachment should appear in the pre-upload storage prompt.
     */
    protected function shouldPromptAttachmentStorage(array $attachmentData): bool
    {
        if (empty($attachmentData['is_inline'])) {
            return true;
        }

        $disposition = strtolower((string) ($attachmentData['content_disposition'] ?? ''));

        return str_contains($disposition, 'attachment');
    }

    protected function validateEmailUploadRequest(Request $request)
    {
        $allowedLabel = $this->emailUploadExtensionsLabel();

        $validator = Validator::make($request->all(), [
            'email_files' => 'required|array|min:1',
            'email_files.*' => 'file|max:' . (int) config('crm.email_upload_max_kb', 30720),
            'client_id' => 'required',
            'type' => 'required|in:client,lead',
        ], [
            'email_files.required' => 'Please choose at least one Outlook email file (' . $allowedLabel . ').',
            'email_files.min' => 'Please choose at least one Outlook email file (' . $allowedLabel . ').',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $invalidFiles = [];
        $emptyFiles = [];
        foreach ($request->file('email_files', []) as $file) {
            $originalName = (string) $file->getClientOriginalName();

            if ($file->getSize() <= 0) {
                $emptyFiles[] = $originalName ?: 'Unknown file';
                continue;
            }

            $extension = strtolower((string) $file->getClientOriginalExtension());

            if (!$this->isAllowedEmailUploadExtension($extension)) {
                $detected = $this->detectEmailUploadExtension($file);
                if (!$detected) {
                    $invalidFiles[] = $originalName ?: 'Unknown file';
                }
            }
        }

        if (!empty($emptyFiles)) {
            return response()->json([
                'status' => false,
                'message' => 'One or more uploaded files are empty (0 bytes). '
                    . 'Browsers cannot read emails dragged directly from Outlook. '
                    . 'Save the email from Outlook as a .msg or .eml file, then upload the saved file.',
                'errors' => [
                    'email_files' => array_map(
                        fn (string $name) => $name . ': file is empty',
                        $emptyFiles
                    ),
                ],
                'empty_files' => $emptyFiles,
            ], 422);
        }

        if (!empty($invalidFiles)) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => [
                    'email_files' => [
                        'Only Outlook email files are allowed (' . $allowedLabel . ').',
                    ],
                ],
                'invalid_files' => $invalidFiles,
            ], 422);
        }

        return null;
    }
}

