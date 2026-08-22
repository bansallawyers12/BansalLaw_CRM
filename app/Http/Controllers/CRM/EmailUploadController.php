<?php

namespace App\Http\Controllers\CRM;

use App\Exceptions\EmailUploadException;
use App\Http\Controllers\Concerns\EnsuresCrmRecordAccess;
use App\Http\Controllers\Controller;
use Aws\Exception\AwsException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;
use App\Models\Document;
use App\Models\EmailLog;
use App\Models\ActivitiesLog;
use App\Models\ClientMatter;
use App\Models\Admin;
use App\Logging\EmailUploadErrorLogger;
use App\Services\Email\EmailCalendarMergeService;
use App\Support\EmailTimelineActivity;
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
     * @return array{success: bool, document_id?: int, email_log_id?: int, warnings?: list<string>, notices?: list<string>, error?: string, error_code?: string, technical_error?: string|null, reference?: string}
     */
    public function importEmailFromContext($file, int $clientId, string $mailType, int $clientMatterId, string $recordType = 'client'): array
    {
        $this->ensureCrmRecordAccess($clientId);

        $clientInfo = Admin::select('client_id', 'type')->where('id', $clientId)->first();
        if (! $clientInfo) {
            return ['success' => false, 'error_code' => 'client_not_found', 'error' => 'Client not found.'];
        }

        $clientUniqueId = (string) ($clientInfo->client_id ?? '');
        $resolvedType = in_array($clientInfo->type, ['client', 'lead'], true) ? $clientInfo->type : $recordType;

        if ($clientMatterId > 0) {
            $belongs = \App\Models\ClientMatter::where('id', $clientMatterId)->where('client_id', $clientId)->exists();
            if (!$belongs) {
                return [
                    'success' => false,
                    'error_code' => 'invalid_matter',
                    'error' => 'Selected matter does not belong to this client.',
                ];
            }
        }

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
        return $this->handleEmailUploadRequest($request, 'inbox');
    }

    /**
     * Upload and process sent emails using Python microservice
     */
    public function uploadSentEmails(Request $request)
    {
        return $this->handleEmailUploadRequest($request, 'sent');
    }

    /**
     * Shared inbox/sent upload handler so both endpoints return identical
     * error shapes (error_code, technical_error, reference, warnings).
     */
    protected function handleEmailUploadRequest(Request $request, string $mailType)
    {
        try {
            set_time_limit(180); // Increase max execution time for uploading emails

            // Validate file input
            $validationResponse = $this->validateEmailUploadRequest($request);
            if ($validationResponse) {
                $this->logEmailUploadFailure('validation', [
                    'mail_type' => $mailType,
                    'client_id' => $request->client_id,
                    'response' => json_decode($validationResponse->getContent(), true),
                ]);
                return $validationResponse;
            }

            $this->ensureCrmRecordAccess((int) $request->client_id);

            $clientId = $request->client_id;
            $clientInfo = Admin::select('client_id')->where('id', $clientId)->first();
            $clientUniqueId = !empty($clientInfo) ? $clientInfo->client_id : "";

            if (!$request->hasfile('email_files')) {
                $this->logEmailUploadFailure('no_files', [
                    'mail_type' => $mailType,
                    'client_id' => $clientId,
                ]);
                return response()->json([
                    'status' => false,
                    'message' => 'No files uploaded',
                    'error_code' => 'no_files',
                ], 400);
            }

            // Check maximum file limit (10 emails max)
            $emailFiles = $request->file('email_files');
            $fileCount = is_array($emailFiles) ? count($emailFiles) : 0;

            if ($fileCount > 10) {
                return response()->json([
                    'status' => false,
                    'message' => 'Maximum 10 email files allowed per upload. Please select 10 or fewer files.',
                    'error_code' => 'too_many_files',
                    'uploaded' => 0,
                    'failed' => 0,
                    'errors' => []
                ], 422);
            }

            $uploadedCount = 0;
            $failedCount = 0;
            $errors = [];
            $warnings = [];
            $notices = [];

            foreach ($request->file('email_files') as $file) {
                try {
                    $result = $this->processEmailFile($file, $clientId, $clientUniqueId, $mailType, $request);

                    if ($result['success']) {
                        $uploadedCount++;
                        if (!empty($result['warnings'])) {
                            $warnings[] = [
                                'filename' => $this->sanitizedUploadFilename($file),
                                'original_filename' => $file->getClientOriginalName(),
                                'warnings' => $result['warnings'],
                            ];
                        }
                        if (!empty($result['notices'])) {
                            $notices[] = [
                                'filename' => $this->sanitizedUploadFilename($file),
                                'original_filename' => $file->getClientOriginalName(),
                                'notices' => $result['notices'],
                            ];
                        }
                    } else {
                        $failedCount++;
                        $errors[] = [
                            'filename' => $this->sanitizedUploadFilename($file),
                            'original_filename' => $file->getClientOriginalName(),
                            'error' => $result['error'] ?? 'Unknown error occurred while processing email',
                            'error_code' => $result['error_code'] ?? 'unknown',
                            'technical_error' => $result['technical_error'] ?? null,
                            'reference' => $result['reference'] ?? null,
                            'duplicate' => !empty($result['duplicate']),
                            'existing' => $result['existing'] ?? null,
                        ];
                    }
                } catch (\Exception $e) {
                    $failedCount++;
                    $fileName = $this->sanitizedUploadFilename($file);
                    $reference = $this->newUploadErrorReference();

                    $errors[] = [
                        'filename' => $fileName,
                        'original_filename' => $file->getClientOriginalName(),
                        'error' => $e->getMessage(),
                        'error_code' => 'unknown',
                        'technical_error' => $e->getMessage(),
                        'reference' => $reference,
                        'file_size' => $file->getSize(),
                        'file_type' => $file->getMimeType()
                    ];
                    $this->logEmailUploadFailure('file_exception', [
                        'mail_type' => $mailType,
                        'client_id' => $clientId,
                        'filename' => $fileName,
                        'original_filename' => $file->getClientOriginalName(),
                        'file_size' => $file->getSize(),
                        'file_type' => $file->getMimeType(),
                        'reference' => $reference,
                    ], $e);
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
                'warnings' => $warnings,
                'notices' => $notices,
                'total_files' => $uploadedCount + $failedCount
            ], 200);

        } catch (HttpResponseException $e) {
            // Permission failures (ensureCrmRecordAccess) must keep their 403 response.
            throw $e;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            // abort(403) etc. must keep their HTTP status instead of becoming a 200 JSON.
            throw $e;
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $reference = $this->newUploadErrorReference();

            if (strpos($errorMessage, 'Validation failed') !== false) {
                $errorMessage = 'File validation failed. Please ensure you\'re uploading Outlook email files only ('
                    . $this->emailUploadExtensionsLabel() . ', max 30MB each).';
            } elseif (strpos($errorMessage, 'No files uploaded') !== false) {
                $errorMessage = 'No files were selected for upload. Please select at least one Outlook email file.';
            }

            $this->logEmailUploadFailure('request_exception', [
                'mail_type' => $mailType,
                'client_id' => $request->client_id,
                'user_friendly_error' => $errorMessage,
                'reference' => $reference,
            ], $e);

            return response()->json([
                'status' => false,
                'message' => 'Upload failed: ' . $errorMessage,
                'error_code' => 'unknown',
                'technical_error' => $e->getMessage(),
                'reference' => $reference,
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
                $this->logEmailUploadFailure('preview_validation', [
                    'client_id' => $request->client_id,
                    'response' => json_decode($validationResponse->getContent(), true),
                ]);
                return $validationResponse;
            }

            $this->ensureCrmRecordAccess((int) $request->client_id);

            $file = $request->file('email_files')[0] ?? null;
            if (!$file) {
                $this->logEmailUploadFailure('preview_no_file', [
                    'client_id' => $request->client_id,
                ]);
                return response()->json([
                    'status' => false,
                    'message' => 'No file uploaded',
                ], 400);
            }

            $parsedData = $this->parseEmailMetadataWithPython($file);
            if (!$parsedData || isset($parsedData['error']) || (isset($parsedData['success']) && !$parsedData['success'])) {
                $this->logEmailUploadFailure('preview_parse', [
                    'client_id' => $request->client_id,
                    'filename' => $file->getClientOriginalName(),
                    'error' => $parsedData['error'] ?? 'Failed to parse email',
                    'technical_error' => $parsedData['technical_error'] ?? null,
                ]);
                return response()->json([
                    'status' => false,
                    'message' => $parsedData['error'] ?? 'Failed to parse email',
                    'error_code' => $parsedData['error_code'] ?? 'parse_failed',
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
            $this->logEmailUploadFailure('preview_exception', [
                'client_id' => $request->client_id,
            ], $e);

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
     * Duplicate guard for IMAP-synced imports (same message across Sent/Inbox or catch-up re-fetch).
     *
     * @param  array<string, mixed>  $parsedData
     * @param  array<string, mixed>  $syncMeta
     */
    protected function findExistingSyncedEmailLog(array $parsedData, string $fileHash, array $syncMeta): ?EmailLog
    {
        $mailboxEmail = strtolower(trim((string) ($syncMeta['mailbox_email'] ?? '')));
        $syncedEmailId = (int) ($syncMeta['synced_email_id'] ?? 0);
        $imapUid = (int) ($syncMeta['imap_uid'] ?? 0);
        $mailType = (string) ($syncMeta['mail_body_type'] ?? $syncMeta['mail_type'] ?? 'inbox');

        $scope = EmailLog::query()->where(function ($q) use ($syncedEmailId, $mailboxEmail) {
            if ($syncedEmailId > 0) {
                $q->where('synced_email_id', $syncedEmailId);
            }
            if ($mailboxEmail !== '') {
                $q->orWhereRaw('LOWER(mailbox_email) = ?', [$mailboxEmail]);
            }
        });

        if ($fileHash !== '') {
            $byHash = (clone $scope)->where('file_hash', $fileHash)->first();
            if ($byHash) {
                return $byHash;
            }
        }

        $messageId = trim((string) ($parsedData['message_id'] ?? ''));
        if ($messageId !== '') {
            $normalized = trim($messageId, '<>');
            $byMessageId = (clone $scope)->where(function ($q) use ($messageId, $normalized) {
                $q->where('message_id', $messageId)
                    ->orWhere('message_id', $normalized)
                    ->orWhere('message_id', '<' . $normalized . '>');
            })->first();
            if ($byMessageId) {
                return $byMessageId;
            }
        }

        if ($syncedEmailId > 0 && $imapUid > 0) {
            $byUid = (clone $scope)
                ->where('imap_uid', $imapUid)
                ->where('mail_body_type', $mailType)
                ->first();
            if ($byUid) {
                return $byUid;
            }
        }

        $subject = strtolower(trim((string) ($parsedData['subject'] ?? '')));
        $sender = strtolower(trim((string) ($parsedData['sender_email'] ?? $parsedData['from_mail'] ?? '')));
        if ($subject === '') {
            return null;
        }

        $fuzzy = (clone $scope)->whereRaw('LOWER(subject) = ?', [$subject]);
        if ($sender !== '') {
            $fuzzy->where(function ($q) use ($sender, $mailboxEmail) {
                $q->whereRaw('LOWER(from_mail) LIKE ?', ['%' . $sender . '%']);
                if ($mailboxEmail !== '') {
                    $q->orWhereRaw('LOWER(from_mail) LIKE ?', ['%' . $mailboxEmail . '%']);
                }
            });
        }

        if (! empty($parsedData['sent_date'])) {
            try {
                $sentAt = $this->parseEmailDateTimeForStorage((string) $parsedData['sent_date']);
                $fuzzy->whereBetween('fetch_mail_sent_time', [
                    $sentAt->copy()->subMinutes(5),
                    $sentAt->copy()->addMinutes(5),
                ]);
            } catch (\Throwable) {
                // Ignore unparseable sent dates.
            }
        }

        return $fuzzy->first();
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
     * @param array<string, mixed>|null $syncMeta
     * @return array
     */
    protected function processEmailFile($file, $clientId, $clientUniqueId, $mailType, $request, ?array $syncMeta = null)
    {
        try {
            $clientId = $clientId > 0 ? (int) $clientId : null;
            $actingUserId = (int) ($syncMeta['staff_user_id'] ?? Auth::id());
            $fileName = $this->sanitizedUploadFilename($file);
            $fileSize = $file->getSize();

            if ($fileSize <= 0) {
                throw new EmailUploadException(
                    'file_empty',
                    'The email file is empty (0 bytes). Browsers cannot read emails dragged directly from Outlook. '
                    . 'Save the email from Outlook as a .msg or .eml file, then upload the saved file.'
                );
            }

            $warnings = [];
            $notices = [];

            // Sanitized name is used for S3 keys, document records, and Python parsing.
            $sanitizedFileName = $fileName;
            $uniqueFileName = time() . '_' . $sanitizedFileName;
            $docType = 'conversion_email_fetch';

            // 1. Parse email using Python microservice (before storage to allow duplicate check without S3 upload)
            $parsedData = $this->parseEmailWithPython($file);

            if (!$parsedData || isset($parsedData['error']) || (isset($parsedData['success']) && !$parsedData['success'])) {
                $parseError = (string) ($parsedData['error'] ?? 'Failed to parse email');
                if (in_array($parseError, ['Failed to parse email', 'Email parsing failed'], true)) {
                    $parseError = 'Failed to parse email file. The file may be corrupted or in an unsupported format.';
                }
                throw new EmailUploadException(
                    (string) ($parsedData['error_code'] ?? 'parse_failed'),
                    $parseError,
                    $parsedData['technical_error'] ?? ($parsedData['error'] ?? null)
                );
            }

            $fileHash = md5_file($file->getRealPath());
            $matterId = $mailType === 'sent'
                ? ($request->upload_sent_mail_client_matter_id ?? $request->client_matter_id ?? $request->matter_id)
                : ($request->upload_inbox_mail_client_matter_id ?? $request->client_matter_id ?? $request->matter_id);
            $matterId = empty($matterId) ? null : (int) $matterId;

            if ($matterId && !empty($clientId)) {
                $belongs = \App\Models\ClientMatter::where('id', $matterId)->where('client_id', $clientId)->exists();
                if (!$belongs) {
                    return [
                        'success' => false,
                        'error_code' => 'invalid_matter',
                        'error' => 'Selected matter does not belong to this client.',
                    ];
                }
            }

            if (!$request->boolean('force_upload') && empty($syncMeta)) {
                $existing = $this->findExistingEmailLog(
                    (int) ($clientId ?? 0),
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
                        'error_code' => 'duplicate',
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

            if (! $request->boolean('force_upload') && ! empty($syncMeta)) {
                $existingSynced = $this->findExistingSyncedEmailLog($parsedData, $fileHash, $syncMeta);
                if ($existingSynced) {
                    return [
                        'success' => true,
                        'skipped' => true,
                        'duplicate' => true,
                        'existing_id' => $existingSynced->id,
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
                    throw new EmailUploadException(
                        'storage_read_failed',
                        'The uploaded email file could not be read from the server\'s temporary storage. Try uploading again.'
                    );
                }

                $uploadResult = $this->emailUploadStorage()->put($filePath, $fileContents);
                if (!$uploadResult) {
                    throw new EmailUploadException(
                        'storage_upload_failed',
                        'The email file could not be written to S3 storage (the storage service rejected the write). '
                        . 'Check the S3 bucket configuration and credentials.'
                    );
                }
            } catch (EmailUploadException $e) {
                throw $e;
            } catch (\Exception $s3Exception) {
                throw $this->buildStorageException($s3Exception, 'uploading the email file to storage');
            }

            // Generate S3 URL - use Storage method which handles encoding properly
            try {
                $fileUrl = $this->emailUploadStorage()->url($filePath);
                if (empty($fileUrl)) {
                    throw new EmailUploadException(
                        'storage_url_failed',
                        'The email file was stored but its URL could not be generated. Check the S3 URL configuration.'
                    );
                }
            } catch (EmailUploadException $e) {
                throw $e;
            } catch (\Exception $urlException) {
                throw new EmailUploadException(
                    'storage_url_failed',
                    'The email file was stored but its URL could not be generated: ' . $urlException->getMessage(),
                    $urlException->getMessage(),
                    $urlException
                );
            }

            // 3. Save document record
            $document = new Document();
            $document->file_name = pathinfo($fileName, PATHINFO_FILENAME);
            $document->filetype = pathinfo($fileName, PATHINFO_EXTENSION);
            $document->user_id = $actingUserId;
            $document->myfile = $fileUrl;
            $document->myfile_key = $uniqueFileName;
            $document->client_id = $clientId;
            $document->type = $request->type;
            $document->mail_type = $mailType;
            $document->file_size = $fileSize;
            $document->doc_type = $docType;
            $document->client_matter_id = $matterId;
            try {
                $document->save();
            } catch (QueryException $e) {
                throw $this->buildDatabaseException($e, 'saving the document record');
            }

            $pdfWarning = null;
            $pdfDocumentId = $this->saveEmailPdfDocument(
                $parsedData,
                $fileName,
                $sanitizedClientId,
                $docType,
                $mailType,
                $uniqueFileName,
                $clientId,
                $request,
                $document->client_matter_id,
                $pdfWarning
            );
            if ($pdfWarning !== null) {
                $warnings[] = $pdfWarning;
            }

            // 4. Save to EmailLog
            $mailReport = new EmailLog();
            $mailReport->user_id = $actingUserId;
            $mailReport->from_mail = $parsedData['sender_email'] ?? '';
            $mailReport->to_mail = $this->formatParsedRecipientList($parsedData, 'to_recipients', 'recipients');
            // IMAP/Zoho BCC or list deliveries may omit To; keep the receiving mailbox visible.
            if ($mailReport->to_mail === '' && ! empty($syncMeta['mailbox_email'])) {
                $mailboxFallback = strtolower(trim((string) $syncMeta['mailbox_email']));
                if ($mailboxFallback !== '' && filter_var($mailboxFallback, FILTER_VALIDATE_EMAIL)) {
                    $mailReport->to_mail = $mailboxFallback;
                }
            }
            $mailReport->cc = $this->formatParsedRecipientList($parsedData, 'cc_recipients');
            $mailReport->bcc = $this->formatParsedRecipientList($parsedData, 'bcc_recipients');
            $mailReport->subject = $parsedData['subject'] ?? '';
            $mailReport->message = $this->sanitizeEmailBodyForStorage(
                $this->preferRenderableEmailBody(
                    $parsedData['html_content'] ?? null,
                    $parsedData['text_content'] ?? null
                )
            );
            $mailReport->mail_type = 1;
            $mailReport->type = $request->type; // Set type to 'client' or 'lead' as required by filter
            $mailReport->client_id = $clientId;
            $mailReport->conversion_type = $docType;
            $mailReport->mail_body_type = $mailType;
            $mailReport->uploaded_doc_id = $document->id;
            $mailReport->pdf_doc_id = $pdfDocumentId;
            $mailReport->client_matter_id = $document->client_matter_id;

            if (!empty($parsedData['text_preview'])) {
                $preview = (string) $parsedData['text_preview'];
                $mailReport->text_preview = \App\Models\EmailLog::isCalendarPayload($preview)
                    ? \App\Models\EmailLog::plainTextPreview($preview, 200)
                    : $preview;
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
            
            if (!empty($parsedData['received_date'])) {
                $mailReport->received_date = $this->parseEmailDateTimeForStorage($parsedData['received_date']);
            } else {
                $mailReport->received_date = now();
            }
            
            $mailReport->file_hash = $fileHash;

            if (! empty($syncMeta)) {
                $mailReport->mailbox_email = $syncMeta['mailbox_email'] ?? null;
                $mailReport->synced_email_id = $syncMeta['synced_email_id'] ?? null;
                $mailReport->sync_assignment_status = $syncMeta['sync_assignment_status'] ?? null;
                $mailReport->imap_uid = $syncMeta['imap_uid'] ?? null;
                if (\Illuminate\Support\Facades\Schema::hasColumn('email_logs', 'sync_source')) {
                    $mailReport->sync_source = $syncMeta['sync_source'] ?? null;
                }
                if (array_key_exists('mail_is_read', $syncMeta) && $syncMeta['mail_is_read'] !== null) {
                    $mailReport->mail_is_read = (bool) $syncMeta['mail_is_read'];
                }
            } else {
                // Explicit pure file upload (not IMAP): no reassignment / unlink path.
                $mailReport->synced_email_id = null;
                $mailReport->sync_assignment_status = null;
                $mailReport->imap_uid = null;
                $mailReport->mailbox_email = null;
                if (\Illuminate\Support\Facades\Schema::hasColumn('email_logs', 'sync_source')) {
                    $mailReport->sync_source = EmailLog::SYNC_SOURCE_UPLOAD;
                }
            }
            
            try {
                $mailReport->save();
            } catch (QueryException $e) {
                throw $this->buildDatabaseException($e, 'saving the email record');
            }

            $attachmentStorageMap = $this->parseAttachmentStorageMap($request);
            $icsAttachments = [];
            $calendarMergeService = app(EmailCalendarMergeService::class);

            // NEW: Save attachments
            if (isset($parsedData['attachments']) && is_array($parsedData['attachments'])) {
                foreach ($parsedData['attachments'] as $index => $attachmentData) {
                    try {
            $origName = $attachmentData['filename'] ?? '';
            if ($calendarMergeService->isCalendarAttachment(
                $origName,
                $attachmentData['content_type'] ?? '',
                pathinfo($origName, PATHINFO_EXTENSION)
            )) {
                $rawIcs = $attachmentData['content'] ?? $attachmentData['data'] ?? null;
                if (! empty($rawIcs)) {
                    $decodedIcs = base64_decode((string) $rawIcs, true);
                    if ($decodedIcs !== false && trim($decodedIcs) !== '') {
                        $icsAttachments[] = [
                            'filename' => $origName,
                            'content' => $decodedIcs,
                        ];
                    }
                }
            }
            $storageConfig = $attachmentStorageMap[$origName]
                ?? $attachmentStorageMap[$attachmentData['display_name'] ?? '']
                ?? null;
                        $attachmentWarning = $this->saveAttachment(
                            $mailReport->id,
                            $attachmentData,
                            $clientUniqueId,
                            $storageConfig,
                            $request,
                            $clientId !== null ? (int) $clientId : null,
                            $document->client_matter_id
                        );
                        if ($attachmentWarning !== null) {
                            $warnings[] = $attachmentWarning;
                        }

                        // Free memory for large attachments, useful if there are >10 attachments
                        unset($parsedData['attachments'][$index]['content']);
                        unset($parsedData['attachments'][$index]['data']);
                    } catch (\Exception $e) {
                        $warnings[] = 'Attachment "' . ($attachmentData['filename'] ?? 'unknown')
                            . '" could not be saved: ' . $e->getMessage();
                        $this->logEmailUploadFailure('attachment_save', [
                            'mail_type' => $mailType,
                            'client_id' => $clientId,
                            'email_log_id' => $mailReport->id,
                            'filename' => $fileName,
                            'attachment' => $attachmentData['filename'] ?? 'unknown',
                        ], $e);
                        // Continue processing other attachments
                    }
                }
            }

            // NEW: Auto-assign labels
            $this->autoAssignLabels($mailReport, $mailType);

            if ($mailType === 'inbox') {
                try {
                    $calendarResult = $calendarMergeService->mergeFromEmail(
                        $mailReport->fresh(['attachments']),
                        $actingUserId,
                        $icsAttachments
                    );
                    if (($calendarResult['merged'] ?? 0) > 0 || ($calendarResult['pending'] ?? 0) > 0) {
                        // Informational only — not a save failure; keep out of warnings[].
                        $notices[] = 'Detected schedule date(s) in this email'
                            . (($calendarResult['merged'] ?? 0) > 0 ? ' and added to calendar.' : ' (will merge when assigned to a client).');
                    }
                } catch (\Throwable $calendarException) {
                    Log::warning('Email calendar merge skipped after upload', [
                        'email_log_id' => $mailReport->id,
                        'error' => $calendarException->getMessage(),
                    ]);
                }
            }

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
            if ($clientId && $request->type == 'client') {
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
                
                // Format subject with matter reference — inbox mail linked to a client is "received"
                $emailSubject = $parsedData['subject'] ?? 'Email';
                $subject = $mailType === 'inbox'
                    ? EmailTimelineActivity::subjectReceived($emailSubject, $matterReference ?: null)
                    : EmailTimelineActivity::subjectUploaded($emailSubject, $matterReference ?: null);

                $from = $parsedData['sender_email'] ?? $parsedData['sender_name'] ?? 'Unknown';
                $description = EmailTimelineActivity::descriptionFrom((string) $from);

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
                'email_log_id' => $mailReport->id,
                'warnings' => $warnings,
                'notices' => $notices,
            ];

        } catch (EmailUploadException $e) {
            $reference = $this->newUploadErrorReference();
            $technicalError = $e->technicalError ?? $e->getPrevious()?->getMessage();

            $this->logEmailUploadFailure('process_file', [
                'mail_type' => $mailType,
                'client_id' => $clientId,
                'filename' => $file->getClientOriginalName(),
                'error_code' => $e->errorCode,
                'user_friendly_error' => $e->getMessage(),
                'technical_error' => $technicalError,
                'reference' => $reference,
            ], $e);

            return [
                'success' => false,
                'error_code' => $e->errorCode,
                'error' => $e->getMessage(),
                'technical_error' => $technicalError,
                'reference' => $reference,
            ];
        } catch (QueryException $e) {
            // Database failures outside the explicit save() wrappers (matter update, activity log, labels).
            $dbException = $this->buildDatabaseException($e, 'saving the email');
            $reference = $this->newUploadErrorReference();

            $this->logEmailUploadFailure('database', [
                'mail_type' => $mailType,
                'client_id' => $clientId,
                'filename' => $file->getClientOriginalName(),
                'error_code' => $dbException->errorCode,
                'error_info' => $e->errorInfo ?? [],
                'sql' => $e->getSql() ?? 'N/A',
                'bindings' => $e->getBindings() ?? [],
                'user_friendly_error' => $dbException->getMessage(),
                'reference' => $reference,
            ], $e);

            return [
                'success' => false,
                'error_code' => $dbException->errorCode,
                'error' => $dbException->getMessage(),
                'technical_error' => $e->getMessage(),
                'reference' => $reference,
            ];
        } catch (\Exception $e) {
            // Unexpected failure: classify connectivity/timeouts, otherwise keep the raw message
            // so the actual cause is never hidden behind a generic string.
            $rawMessage = $e->getMessage();
            $errorCode = 'unknown';
            $errorMessage = $rawMessage;

            if (stripos($rawMessage, 'Failed to connect') !== false || stripos($rawMessage, 'Connection refused') !== false) {
                $errorCode = 'service_unreachable';
                $errorMessage = "Cannot connect to the email processing service at {$this->pythonServiceUrl}. "
                    . "Ensure the Python service is running. ({$rawMessage})";
            } elseif (stripos($rawMessage, 'timed out') !== false || stripos($rawMessage, 'timeout') !== false) {
                $errorCode = 'timeout';
                $errorMessage = "The upload timed out. The file may be very large — try again or upload a smaller file. ({$rawMessage})";
            }

            $reference = $this->newUploadErrorReference();

            $this->logEmailUploadFailure('process_file', [
                'mail_type' => $mailType,
                'client_id' => $clientId,
                'filename' => $file->getClientOriginalName(),
                'error_code' => $errorCode,
                'user_friendly_error' => $errorMessage,
                'technical_error' => $rawMessage,
                'reference' => $reference,
            ], $e);

            return [
                'success' => false,
                'error_code' => $errorCode,
                'error' => $errorMessage,
                'technical_error' => $rawMessage,
                'reference' => $reference,
            ];
        }
    }

    /**
     * Short reference ID included in both the error log entry and the user-facing
     * error so support can find the full details quickly.
     */
    protected function newUploadErrorReference(): string
    {
        return 'EU-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
    }

    /**
     * Map storage exceptions (AWS SDK error codes when available) to a specific,
     * actionable message instead of a generic "storage error".
     */
    protected function buildStorageException(\Throwable $e, string $context): EmailUploadException
    {
        $aws = $e;
        while ($aws !== null && !$aws instanceof AwsException) {
            $aws = $aws->getPrevious();
        }

        if ($aws instanceof AwsException) {
            $awsCode = (string) $aws->getAwsErrorCode();
            $awsMessage = (string) ($aws->getAwsErrorMessage() ?? $aws->getMessage());

            $message = match (true) {
                in_array($awsCode, ['AccessDenied', 'InvalidAccessKeyId', 'SignatureDoesNotMatch', 'ExpiredToken', 'TokenRefreshRequired'], true)
                    => "Storage access denied while {$context}. The S3 credentials are invalid, expired, or missing permission for this bucket.",
                $awsCode === 'NoSuchBucket'
                    => "Storage bucket not found while {$context}. Check the configured S3 bucket name.",
                in_array($awsCode, ['RequestTimeout', 'SlowDown', 'ServiceUnavailable'], true)
                    => "The storage service timed out or is throttling requests while {$context}. Please try again.",
                default
                    => "Storage error while {$context}" . ($awsCode !== '' ? " ({$awsCode})" : '') . ': ' . $awsMessage,
            };

            return new EmailUploadException('storage_upload_failed', $message, $e->getMessage(), $e);
        }

        return new EmailUploadException(
            'storage_upload_failed',
            "Storage error while {$context}: " . $e->getMessage(),
            $e->getMessage(),
            $e
        );
    }

    /**
     * Map QueryExceptions to a specific message (PostgreSQL SQLSTATE aware) with an error code.
     */
    protected function buildDatabaseException(QueryException $e, string $context): EmailUploadException
    {
        $errorInfo = $e->errorInfo ?? [];
        $sqlState = (string) ($errorInfo[0] ?? '');
        $detail = (string) ($errorInfo[2] ?? $e->getMessage());

        [$errorCode, $message] = match (true) {
            $sqlState === '23502'
                => ['db_constraint', "Database error while {$context}: a required field is missing ({$detail})."],
            $sqlState === '23505'
                => ['db_constraint', "Database error while {$context}: duplicate entry — this email may already exist ({$detail})."],
            $sqlState === '22P02' || str_contains($e->getMessage(), 'invalid input syntax')
                => ['db_constraint', "Database error while {$context}: invalid data format for one or more fields ({$detail})."],
            stripos($e->getMessage(), 'json') !== false
                => ['db_error', "Database error while {$context}: the email metadata could not be saved as JSON ({$detail})."],
            default
                => ['db_error', "Database error while {$context}: {$detail}"],
        };

        return new EmailUploadException($errorCode, $message, $e->getMessage(), $e);
    }

    /**
     * Save generated PDF to S3 and create a documents row (soft-fail returns null).
     * When the PDF cannot be generated or stored, $warning is set so the user can be told.
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
        ?int $clientId,
        Request $request,
        $clientMatterId,
        ?string &$warning = null
    ): ?int {
        $warning = null;

        if (empty($parsedData['pdf_base64'])) {
            if (!empty($parsedData['pdf_error'])) {
                $warning = 'A PDF preview could not be generated for this email: ' . $parsedData['pdf_error'];
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
                $warning = 'A PDF preview could not be generated for this email (invalid PDF data from the parsing service).';
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
                $warning = 'The PDF preview for this email could not be stored (the storage service rejected the write).';
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
            $pdfDocument->user_id = Auth::id();
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
            $warning = 'The PDF preview for this email could not be saved: ' . $e->getMessage();
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
                    'error_code' => 'file_empty',
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
                    return [
                        'success' => false,
                        'error_code' => 'service_invalid_response',
                        'error' => 'The email processing service returned an invalid response. The service may be experiencing issues.',
                        'technical_error' => $jsonException->getMessage(),
                    ];
                }

                if (isset($result['error']) || (isset($result['success']) && ! $result['success'])) {
                    $technicalError = (string) ($result['error'] ?? $result['detail'] ?? 'Email parsing failed');

                    return [
                        'success' => false,
                        'error_code' => 'parse_failed',
                        'error' => $technicalError,
                        'technical_error' => $technicalError,
                    ];
                }

                return $result;
            }

            $technicalError = $this->extractPythonServiceError($response);

            return [
                'success' => false,
                'error_code' => $this->pythonServiceErrorCode($response->status()),
                'error' => $technicalError,
                'technical_error' => $technicalError,
            ];
        } catch (\Exception $e) {
            $rawMessage = $e->getMessage();
            $isTimeout = stripos($rawMessage, 'timed out') !== false || stripos($rawMessage, 'timeout') !== false;

            return [
                'success' => false,
                'error_code' => $isTimeout ? 'timeout' : 'service_unreachable',
                'error' => $isTimeout
                    ? 'Email processing timed out. The file may be very large — try again or upload a smaller file. (' . $rawMessage . ')'
                    : 'Cannot connect to the email processing service at ' . $this->pythonServiceUrl
                        . '. Ensure the Python service is running. (' . $rawMessage . ')',
                'technical_error' => $rawMessage,
            ];
        }
    }

    /**
     * Machine-readable code for a failed Python service HTTP response.
     */
    protected function pythonServiceErrorCode(int $status): string
    {
        return match (true) {
            $status === 413 => 'file_too_large',
            $status === 400 => 'parse_failed',
            $status >= 500 => 'service_error',
            default => 'service_error',
        };
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
     * @return string|null Warning message when the attachment file could not be stored (record may still exist)
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
        $warning = null;
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
            
            $decodedData = null;
            if (!empty($attachmentContent)) {
                // Decode base64-encoded attachment data
                $decodedData = base64_decode($attachmentContent, true);
                
                // Validate base64 decode succeeded
                if ($decodedData === false) {
                    $warning = 'Attachment "' . $displayName . '" could not be decoded and was recorded without its file.';
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
                        $warning = 'Attachment "' . $displayName . '" was empty after decoding and was recorded without its file.';
                        Log::warning('Decoded attachment data is empty', [
                            'filename' => $attachmentData['filename'] ?? 'unknown'
                        ]);
                        $decodedData = null;
                    }
                }
            } else {
                // Attachment metadata only — no binary payload in parsed email.
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
                    $warning = 'Attachment "' . $displayName . '" could not be stored: ' . $s3Exception->getMessage();
                    $this->logEmailUploadFailure('attachment_s3', [
                        'client_id' => $clientId,
                        'email_log_id' => $mailReportId,
                        'attachment' => $attachmentData['filename'] ?? 'unknown',
                        's3_key' => $s3Key,
                    ], $s3Exception);
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

            return $warning;
        } catch (\Exception $e) {
            $this->logEmailUploadFailure('attachment_save', [
                'client_id' => $clientId,
                'email_log_id' => $mailReportId,
                'attachment' => $attachmentData['filename'] ?? 'unknown',
            ], $e);
            // Don't re-throw - allow email upload to continue even if attachment fails
            // Attachment record will still be created (if we got that far) but without file
            return 'Attachment "' . $displayName . '" could not be saved: ' . $e->getMessage();
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
     * Prefer HTML/text that has readable content. Outlook often stores an empty
     * compose shell as HTML containing only &nbsp;, which would blank the reading pane.
     * Raw ICS/calendar dumps are never stored as the message body.
     */
    protected function preferRenderableEmailBody(?string $html, ?string $text): ?string
    {
        $html = $html ?? '';
        $text = $text ?? '';

        $calendarSource = '';
        if ($this->isCalendarPayload($html)) {
            $calendarSource = $html;
            $html = '';
        }
        if ($this->isCalendarPayload($text)) {
            $calendarSource = $calendarSource !== '' ? $calendarSource : $text;
            $text = '';
        }
        if ($calendarSource !== '' && ! $this->emailBodyHasVisibleText($text) && ! $this->emailBodyHasVisibleText($html)) {
            $text = \App\Models\EmailLog::summarizeCalendarPayload($calendarSource);
        }

        if ($this->emailBodyHasVisibleText($html)) {
            return $html;
        }
        if ($this->emailBodyHasVisibleText($text) || trim(strip_tags($text)) !== '') {
            return $text;
        }

        // Keep empty rather than storing an &nbsp;-only shell so the UI can fall back to PDF.
        return null;
    }

    protected function isCalendarPayload(?string $content): bool
    {
        return \App\Models\EmailLog::isCalendarPayload($content);
    }

    protected function emailBodyHasVisibleText(?string $content): bool
    {
        if ($content === null || trim($content) === '') {
            return false;
        }

        if ($this->isCalendarPayload($content)) {
            return false;
        }

        $text = preg_replace('#<(script|style|head|noscript)\b[^>]*>.*?</\1>#is', ' ', $content) ?? $content;
        if (preg_match('/<img\b/i', $text)) {
            return true;
        }

        $text = preg_replace('#<[^>]+>#', ' ', $text) ?? $text;
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text) !== '';
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
            $companyDomains = config('app.brand.firm_email_domains', [
                '@bansallawyers.com.au',
                '@bansaleducation.com.au',
            ]);
            if (! is_array($companyDomains)) {
                $companyDomains = [];
            }
            
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
            $normalizedEntry = \App\Services\EmailSync\IncomingEmailSyncService::normalizeRecipientEntry($entry);
            if ($normalizedEntry !== '') {
                $normalized[] = $normalizedEntry;
            }
        }

        return $normalized !== [] ? implode(',', array_values(array_unique($normalized))) : '';
    }

    /**
     * Parse date strings from the Python parser into a Carbon instance for DB storage.
     * Never pass locale display strings (e.g. d/m/Y) to PostgreSQL — server DateStyle (MDY vs DMY) differs
     * between machines; bind proper timestamps only.
     */
    /**
     * Parse email sent/received timestamps for DB storage as Melbourne wall time.
     *
     * Offset-aware values (ISO-8601 / RFC2822) are converted to the app timezone.
     * Naive values are treated as Australia/Melbourne local time — never UTC —
     * so Outlook/MSG and formatted dates keep the correct Melbourne clock face.
     */
    public function parseEmailDateTimeForStorage(string $dateString): Carbon
    {
        $dateString = trim($dateString);
        $appTimezone = (string) config('app.timezone', 'Australia/Melbourne');

        if ($dateString === '') {
            return now($appTimezone);
        }

        try {
            // ISO-8601 with explicit offset or Z → convert absolute instant to app TZ.
            if (preg_match('/^\d{4}-\d{2}-\d{2}T/', $dateString) || preg_match('/(?:[+-]\d{2}:?\d{2}|Z)$/i', $dateString)) {
                return Carbon::parse($dateString)->timezone($appTimezone);
            }
        } catch (\Throwable $e) {
            // fall through to explicit formats
        }

        // Naive wall-clock formats (MSG/exports) are CRM local time, not UTC.
        $formats = ['d/m/Y h:i a', 'd/m/Y g:i a', 'd/m/Y H:i:s', 'd/m/Y H:i', 'Y-m-d H:i:s', 'Y-m-d H:i'];
        foreach ($formats as $fmt) {
            try {
                $dt = Carbon::createFromFormat($fmt, $dateString, $appTimezone);
                if ($dt instanceof Carbon) {
                    return $dt;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        try {
            // RFC2822 etc.: honour embedded offset when present; otherwise app TZ.
            return Carbon::parse($dateString, $appTimezone)->timezone($appTimezone);
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
        $disposition = strtolower((string) ($attachmentData['content_disposition'] ?? ''));

        if (str_contains($disposition, 'attachment')) {
            return true;
        }

        // Only skip parts embedded in the email body (cid: references). Outlook .eml
        // exports often mark real file attachments as Content-Disposition: inline.
        return empty($attachmentData['is_inline']);
    }

    /**
     * Log browser-side email upload failures to the dedicated error log file.
     */
    public function logClientUploadError(Request $request)
    {
        $errors = $request->input('errors');
        if (is_string($errors)) {
            $decoded = json_decode($errors, true);
            $errors = is_array($decoded) ? $decoded : null;
        }

        $this->logEmailUploadFailure((string) $request->input('stage', 'client'), array_filter([
            'source' => 'browser',
            'staff_id' => Auth::id(),
            'client_id' => $request->input('client_id'),
            'mail_type' => $request->input('mail_type'),
            'filename' => $request->input('filename'),
            'error' => $request->input('error'),
            'technical_error' => $request->input('technical_error'),
            'errors' => $errors,
            'file_size' => $request->input('file_size'),
            'file_type' => $request->input('file_type'),
        ], static fn ($value) => $value !== null && $value !== ''));

        return response()->json(['status' => true]);
    }

    /**
     * Write email upload failures to storage/logs/email-upload-errors-*.log (errors only).
     */
    protected function logEmailUploadFailure(string $stage, array $context = [], ?\Throwable $throwable = null): void
    {
        $payload = array_merge([
            'staff_id' => Auth::id(),
        ], $context);

        EmailUploadErrorLogger::log($stage, $payload, $throwable);
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

        $matterId = $request->upload_sent_mail_client_matter_id ?? $request->upload_inbox_mail_client_matter_id ?? $request->client_matter_id ?? $request->matter_id;
        if (!empty($matterId) && !empty($request->client_id)) {
            $belongs = \App\Models\ClientMatter::where('id', (int)$matterId)->where('client_id', (int)$request->client_id)->exists();
            if (!$belongs) {
                return response()->json([
                    'status' => false,
                    'message' => 'Selected matter does not belong to this client.',
                    'error_code' => 'invalid_matter',
                ], 422);
            }
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

    /**
     * Parse an email file for IMAP sync (no HTTP request required).
     *
     * @return array<string, mixed>|null
     */
    public function parseEmailFileForSync($file): ?array
    {
        return $this->parseEmailWithPython($file);
    }

    /**
     * Import a synced IMAP message using the same pipeline as manual upload.
     *
     * @param array<string, mixed> $syncMeta
     * @return array<string, mixed>
     */
    public function importFromSync(
        $file,
        ?int $clientId,
        ?int $clientMatterId,
        string $recordType,
        string $mailType,
        int $staffUserId,
        string $clientUniqueId,
        array $syncMeta
    ): array {
        $payload = [
            'type' => $recordType,
            'force_upload' => false,
        ];

        if ($mailType === 'sent') {
            $payload['upload_sent_mail_client_matter_id'] = $clientMatterId;
        } else {
            $payload['upload_inbox_mail_client_matter_id'] = $clientMatterId;
        }

        $request = Request::create('/', 'POST', $payload);
        $syncMeta['staff_user_id'] = $staffUserId;

        return $this->processEmailFile(
            $file,
            $clientId ?? 0,
            $clientUniqueId,
            $mailType,
            $request,
            $syncMeta
        );
    }
}

