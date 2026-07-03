<?php

namespace App\Http\Controllers\CRM;

use App\Models\ClientMatter;
use App\Services\EmailMatchingService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SmartEmailImportController extends EmailUploadController
{
    private const MAX_FILES = 10;

    private const BATCH_TTL_HOURS = 24;

    public function __construct(
        private EmailMatchingService $matchingService
    ) {
        parent::__construct();
    }

    public function index()
    {
        return view('crm.emails.smart-import');
    }

    /**
     * Parse uploaded .msg files, suggest client/matter matches, stage files for confirmation.
     */
    public function analyze(Request $request)
    {
        $validationResponse = $this->validateSmartImportFiles($request);
        if ($validationResponse) {
            return $validationResponse;
        }

        $this->cleanupExpiredBatches();

        $batchToken = (string) Str::uuid();
        $batchDir = $this->batchDirectory($batchToken);
        File::ensureDirectoryExists($batchDir);

        $items = [];
        $failed = [];

        foreach ($request->file('email_files', []) as $file) {
            $itemId = (string) Str::uuid();
            $originalName = $file->getClientOriginalName();
            $sanitizedName = $this->sanitizedUploadFilename($file);
            $storedExtension = strtolower((string) pathinfo($sanitizedName, PATHINFO_EXTENSION));

            try {
                $storedPath = $batchDir . DIRECTORY_SEPARATOR . $itemId . '.' . $storedExtension;
                $file->move($batchDir, $itemId . '.' . $storedExtension);

                $uploadedFile = new UploadedFile(
                    $storedPath,
                    $sanitizedName,
                    'application/octet-stream',
                    null,
                    true
                );

                $parsedData = $this->parseEmailWithPython($uploadedFile);
                if (! $parsedData || isset($parsedData['error']) || (isset($parsedData['success']) && ! $parsedData['success'])) {
                    throw new \RuntimeException($parsedData['error'] ?? 'Failed to parse email');
                }

                $matchResult = $this->matchingService->suggestMatches($parsedData);
                $preview = $this->buildPreview($parsedData, $originalName, $matchResult);

                $items[] = [
                    'id' => $itemId,
                    'filename' => $sanitizedName,
                    'original_filename' => $originalName,
                    'stored_extension' => $storedExtension,
                    'mail_type' => $matchResult['mail_type'],
                    'confidence' => $matchResult['confidence'],
                    'is_high_confidence' => $matchResult['is_high_confidence'],
                    'matched_by' => $matchResult['matched_by'],
                    'suggestions' => $matchResult['suggestions'],
                    'suggested_client_id' => $matchResult['best']['client_id'] ?? null,
                    'suggested_client_matter_id' => $matchResult['best']['client_matter_id'] ?? null,
                    'suggested_record_type' => $matchResult['best']['record_type'] ?? 'client',
                    'preview' => $preview,
                ];
            } catch (\Throwable $e) {
                @unlink($batchDir . DIRECTORY_SEPARATOR . $itemId . '.' . $storedExtension);
                $failed[] = [
                    'filename' => $originalName,
                    'error' => $e->getMessage(),
                ];
                Log::error('Smart email import analyze failed', [
                    'file' => $originalName,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($items === [] && $failed !== []) {
            File::deleteDirectory($batchDir);

            return response()->json([
                'status' => false,
                'message' => 'No emails could be analyzed.',
                'failed' => $failed,
            ], 422);
        }

        $meta = [
            'batch_token' => $batchToken,
            'user_id' => Auth::id(),
            'created_at' => now()->toIso8601String(),
            'items' => $items,
        ];
        file_put_contents($batchDir . DIRECTORY_SEPARATOR . 'meta.json', json_encode($meta, JSON_THROW_ON_ERROR));

        return response()->json([
            'status' => true,
            'message' => count($items) . ' email(s) ready for review.',
            'batch_token' => $batchToken,
            'items' => $items,
            'failed' => $failed,
            'high_confidence_count' => count(array_filter($items, static fn (array $item) => $item['is_high_confidence'])),
            'unmatched_count' => count(array_filter($items, static fn (array $item) => empty($item['suggested_client_id']))),
        ]);
    }

    /**
     * Confirm staged imports and save to client/matter records.
     */
    public function confirm(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'batch_token' => 'required|uuid',
            'assignments' => 'required|array|min:1',
            'assignments.*.item_id' => 'required|uuid',
            'assignments.*.client_id' => 'required|integer|min:1',
            'assignments.*.client_matter_id' => 'required|integer|min:1',
            'assignments.*.mail_type' => 'required|in:inbox,sent',
            'assignments.*.record_type' => 'nullable|in:client,lead',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $batchToken = (string) $request->input('batch_token');
        $batchDir = $this->batchDirectory($batchToken);
        $metaPath = $batchDir . DIRECTORY_SEPARATOR . 'meta.json';

        if (! is_file($metaPath)) {
            return response()->json([
                'status' => false,
                'message' => 'Import session expired or not found. Please upload again.',
            ], 404);
        }

        $meta = json_decode((string) file_get_contents($metaPath), true, 512, JSON_THROW_ON_ERROR);
        if ((int) ($meta['user_id'] ?? 0) !== (int) Auth::id()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized import session.',
            ], 403);
        }

        $itemsById = collect($meta['items'] ?? [])->keyBy('id');
        $saved = 0;
        $failed = [];
        $savedItemIds = [];

        foreach ($request->input('assignments', []) as $assignment) {
            $itemId = (string) $assignment['item_id'];
            $itemMeta = $itemsById->get($itemId);
            $storedExtension = strtolower((string) ($itemMeta['stored_extension'] ?? 'msg'));
            $storedPath = $batchDir . DIRECTORY_SEPARATOR . $itemId . '.' . $storedExtension;

            if (! $itemMeta || ! is_file($storedPath)) {
                $failed[] = [
                    'item_id' => $itemId,
                    'error' => 'Staged file not found.',
                ];
                continue;
            }

            $clientId = (int) $assignment['client_id'];
            $clientMatterId = (int) $assignment['client_matter_id'];
            $mailType = (string) $assignment['mail_type'];
            $recordType = (string) ($assignment['record_type'] ?? $itemMeta['suggested_record_type'] ?? 'client');

            if (! $this->matterBelongsToClient($clientMatterId, $clientId)) {
                $failed[] = [
                    'item_id' => $itemId,
                    'filename' => $itemMeta['filename'] ?? $itemId,
                    'error' => 'Selected matter does not belong to the selected client.',
                ];
                continue;
            }

            try {
                $uploadedFile = new UploadedFile(
                    $storedPath,
                    (string) ($itemMeta['filename'] ?? $itemId . '.msg'),
                    'application/octet-stream',
                    null,
                    true
                );

                $result = $this->importEmailFromContext(
                    $uploadedFile,
                    $clientId,
                    $mailType,
                    $clientMatterId,
                    $recordType
                );

                if (! ($result['success'] ?? false)) {
                    $failed[] = [
                        'item_id' => $itemId,
                        'filename' => $itemMeta['filename'] ?? $itemId,
                        'error' => $result['error'] ?? 'Import failed',
                    ];
                    continue;
                }

                $saved++;
                $savedItemIds[] = $itemId;
                @unlink($storedPath);
            } catch (HttpResponseException $e) {
                $failed[] = [
                    'item_id' => $itemId,
                    'filename' => $itemMeta['filename'] ?? $itemId,
                    'error' => 'You do not have access to this client.',
                ];
            } catch (\Throwable $e) {
                $failed[] = [
                    'item_id' => $itemId,
                    'filename' => $itemMeta['filename'] ?? $itemId,
                    'error' => $e->getMessage() ?: 'Import failed',
                ];
                Log::error('Smart email import confirm failed', [
                    'item_id' => $itemId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($savedItemIds !== []) {
            $meta['items'] = array_values(array_filter(
                $meta['items'] ?? [],
                static fn (array $item): bool => ! in_array($item['id'] ?? '', $savedItemIds, true)
            ));
            file_put_contents($metaPath, json_encode($meta, JSON_THROW_ON_ERROR));
        }

        $remaining = array_values(array_filter(
            glob($batchDir . DIRECTORY_SEPARATOR . '*') ?: [],
            static fn (string $path): bool => basename($path) !== 'meta.json'
        ));
        if ($remaining === []) {
            File::deleteDirectory($batchDir);
        }

        $failedCount = count($failed);
        $message = $saved > 0
            ? "Imported {$saved} email(s) successfully."
            : 'No emails were imported.';
        if ($saved > 0 && $failedCount > 0) {
            $message = "Imported {$saved} email(s); {$failedCount} failed.";
        }

        return response()->json([
            'status' => $saved > 0,
            'message' => $message,
            'saved' => $saved,
            'saved_item_ids' => $savedItemIds,
            'failed' => $failed,
        ]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse|null
     */
    private function validateSmartImportFiles(Request $request)
    {
        $allowedLabel = $this->emailUploadExtensionsLabel();

        $validator = Validator::make($request->all(), [
            'email_files' => 'required|array|min:1|max:' . self::MAX_FILES,
            'email_files.*' => 'file|max:' . (int) config('crm.email_upload_max_kb', 30720),
        ], [
            'email_files.required' => 'Please choose at least one Outlook email file (' . $allowedLabel . ').',
            'email_files.max' => 'Maximum ' . self::MAX_FILES . ' email files allowed per upload.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $invalidFiles = [];
        foreach ($request->file('email_files', []) as $file) {
            if (! $this->isAllowedEmailUploadExtension((string) $file->getClientOriginalExtension())) {
                $invalidFiles[] = $file->getClientOriginalName();
            }
        }

        if ($invalidFiles !== []) {
            return response()->json([
                'status' => false,
                'message' => 'Only Outlook email files are allowed (' . $allowedLabel . ').',
                'invalid_files' => $invalidFiles,
            ], 422);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $parsedData
     * @param array<string, mixed> $matchResult
     * @return array<string, mixed>
     */
    private function buildPreview(array $parsedData, string $filename, array $matchResult): array
    {
        $bodySnippet = (string) ($parsedData['text_preview'] ?? '');
        if ($bodySnippet === '' && ! empty($parsedData['text_content'])) {
            $bodySnippet = mb_substr(trim((string) $parsedData['text_content']), 0, 300);
        }

        $attachments = $parsedData['attachments'] ?? [];
        $attachmentNames = [];
        if (is_array($attachments)) {
            foreach ($attachments as $attachment) {
                if (! empty($attachment['filename'])) {
                    $attachmentNames[] = (string) $attachment['filename'];
                }
            }
        }

        return [
            'filename' => $filename,
            'subject' => (string) ($parsedData['subject'] ?? ''),
            'from' => (string) ($parsedData['sender_email'] ?? $parsedData['sender_name'] ?? ''),
            'to' => $this->formatParsedRecipientList($parsedData, 'to_recipients', 'recipients'),
            'cc' => $this->formatParsedRecipientList($parsedData, 'cc_recipients'),
            'sent_date' => (string) ($parsedData['sent_date'] ?? ''),
            'body_snippet' => $bodySnippet,
            'attachment_count' => count($attachmentNames),
            'attachment_names' => array_slice($attachmentNames, 0, 5),
            'mail_type' => $matchResult['mail_type'],
        ];
    }

    private function batchDirectory(string $batchToken): string
    {
        return storage_path('app/smart-email-import/' . Auth::id() . '/' . $batchToken);
    }

    private function matterBelongsToClient(int $clientMatterId, int $clientId): bool
    {
        return ClientMatter::query()
            ->where('id', $clientMatterId)
            ->where('client_id', $clientId)
            ->exists();
    }

    private function cleanupExpiredBatches(): void
    {
        $userDir = storage_path('app/smart-email-import/' . Auth::id());
        if (! is_dir($userDir)) {
            return;
        }

        $cutoff = now()->subHours(self::BATCH_TTL_HOURS)->getTimestamp();

        foreach (File::directories($userDir) as $batchDir) {
            $metaPath = $batchDir . DIRECTORY_SEPARATOR . 'meta.json';
            if (! is_file($metaPath)) {
                File::deleteDirectory($batchDir);
                continue;
            }

            $meta = json_decode((string) file_get_contents($metaPath), true);
            $createdAt = isset($meta['created_at']) ? strtotime((string) $meta['created_at']) : filemtime($metaPath);
            if ($createdAt !== false && $createdAt < $cutoff) {
                File::deleteDirectory($batchDir);
            }
        }
    }
}
