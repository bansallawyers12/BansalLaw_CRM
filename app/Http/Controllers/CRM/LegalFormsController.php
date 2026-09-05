<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\EnsuresCrmRecordAccess;
use App\Models\ClientLegalForm;
use App\Models\ClientMatter;
use App\Services\LegalFormDocxService;
use App\Services\LegalFormFileStorage;
use App\Services\LegalFormPreviewService;
use App\Services\LegalFormScopeAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LegalFormsController extends Controller
{
    use EnsuresCrmRecordAccess;

    private LegalFormDocxService $docxService;

    private LegalFormPreviewService $previewService;

    private LegalFormScopeAiService $scopeAiService;

    private LegalFormFileStorage $fileStorage;

    /** Document extensions allowed for legal form uploads (no images or executables). */
    private const UPLOAD_ALLOWED_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'txt', 'rtf', 'odt', 'xls', 'xlsx', 'ppt', 'pptx', 'csv',
    ];

    /** Extensions that must always be rejected (scripts, executables, images). */
    private const UPLOAD_BLOCKED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'ico', 'tif', 'tiff', 'heic', 'heif',
        'exe', 'msi', 'bat', 'cmd', 'com', 'scr', 'ps1', 'vbs', 'js', 'jsx', 'ts', 'tsx',
        'php', 'py', 'pyc', 'rb', 'pl', 'sh', 'bash', 'zsh', 'jar', 'app', 'dmg',
        'html', 'htm', 'xhtml', 'xml', 'json', 'yaml', 'yml', 'sql', 'dll', 'so', 'bin',
    ];

    public function __construct(
        LegalFormDocxService $docxService,
        LegalFormPreviewService $previewService,
        LegalFormScopeAiService $scopeAiService,
        LegalFormFileStorage $fileStorage,
    ) {
        $this->middleware('auth:admin');
        $this->docxService = $docxService;
        $this->previewService = $previewService;
        $this->scopeAiService = $scopeAiService;
        $this->fileStorage = $fileStorage;
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'client_id' => 'required|exists:admins,id',
            'client_matter_id' => 'nullable|exists:client_matters,id',
            'form_type' => 'required|in:short_costs_disclosure,cost_agreement,authority_to_act',
            'form_date' => 'nullable|date',
            'matter_reference' => 'nullable|string|max:100',
            'firm_name' => 'nullable|string|max:255',
            'firm_contact' => 'nullable|string|max:255',
            'firm_address' => 'nullable|string',
            'firm_phone' => 'nullable|string|max:50',
            'firm_mobile' => 'nullable|string|max:50',
            'firm_email' => 'nullable|string|max:255',
            'firm_state' => 'nullable|string|max:50',
            'firm_postcode' => 'nullable|string|max:10',
            'person_responsible' => 'nullable|string|max:255',
            'person_responsible_email' => 'nullable|string|max:255',
            'scope_of_work' => 'nullable|string',
            'estimated_legal_fees' => 'nullable|numeric|min:0',
            'estimated_disbursements' => 'nullable|numeric|min:0',
            'estimated_barrister_fees' => 'nullable|numeric|min:0',
            'fee_type' => 'nullable|string|in:fixed,hourly',
            'fixed_fee_amount' => 'nullable|numeric|min:0',
            'cost_estimate_breakdown' => 'nullable|string',
            'variables_affecting_costs' => 'nullable|string',
            'retainer_amount' => 'nullable|numeric|min:0',
            'payment_reference' => 'nullable|string|max:100',
            'authority_scope' => 'nullable|string',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,bmp,pdf,doc,docx,txt,xls,xlsx|max:10240',
        ]);

        $data = $request->except(['attachment']);

        if (empty($data['client_matter_id']) && ! empty($data['matter_reference'])) {
            $resolvedMatterId = ClientMatter::query()
                ->where('client_id', (int) $data['client_id'])
                ->where('client_unique_matter_no', trim((string) $data['matter_reference']))
                ->value('id');
            if ($resolvedMatterId) {
                $data['client_matter_id'] = $resolvedMatterId;
            }
        }
        $data['created_by'] = Auth::id();

        // Ensure numeric fields are never null
        $numericFields = [
            'estimated_legal_fees', 'estimated_disbursements', 'estimated_barrister_fees',
            'gst_amount', 'estimated_total', 'fixed_fee_amount', 'retainer_amount',
        ];
        foreach ($numericFields as $field) {
            $data[$field] = floatval($data[$field] ?? 0);
        }

        if (in_array($data['form_type'], ['short_costs_disclosure', 'cost_agreement'])) {
            $fees = $data['estimated_legal_fees'];
            $disbursements = $data['estimated_disbursements'];
            $barrister = $data['estimated_barrister_fees'];
            $data['gst_amount'] = round($fees * 0.10, 2);
            $data['estimated_total'] = $fees + $disbursements + $barrister + $data['gst_amount'];
        }

        try {
            $form = DB::transaction(function () use ($request, $data) {
                $form = ClientLegalForm::create($data);
                $attachmentMeta = $this->storeAttachment($request, (int) $form->client_id);
                if ($attachmentMeta !== []) {
                    $form->update($attachmentMeta);
                }
                $docxPath = $this->docxService->generate($form);
                $form->update(['pdf_path' => $docxPath]);
                $this->previewService->forgetHtmlCache((int) $form->id);

                return $form;
            });
        } catch (\Throwable $e) {
            Log::error('Legal form create failed', ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Could not generate the Word document. Check storage permissions and templates.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => ClientLegalForm::FORM_TYPES[$form->form_type] . ' created successfully.',
            'form' => $form->load(['client', 'matter', 'creator']),
        ]);
    }

    public function uploadForm(Request $request): JsonResponse
    {
        $request->validate([
            'client_id' => 'required|exists:admins,id',
            'client_matter_id' => 'nullable|exists:client_matters,id',
            'form_type' => 'required|in:short_costs_disclosure,cost_agreement,authority_to_act',
            'form_date' => 'nullable|date',
            'matter_reference' => 'nullable|string|max:100',
            'file' => 'required|file|max:20480',
        ]);

        $this->validateUploadedDocument($request->file('file'));

        $data = $request->only(['client_id', 'client_matter_id', 'form_type', 'form_date', 'matter_reference']);

        if (empty($data['client_matter_id']) && ! empty($data['matter_reference'])) {
            $resolvedMatterId = ClientMatter::query()
                ->where('client_id', (int) $data['client_id'])
                ->where('client_unique_matter_no', trim((string) $data['matter_reference']))
                ->value('id');
            if ($resolvedMatterId) {
                $data['client_matter_id'] = $resolvedMatterId;
            }
        }

        $data['created_by'] = Auth::id();
        $data['is_uploaded'] = true;

        try {
            $form = DB::transaction(function () use ($request, $data) {
                $form = ClientLegalForm::create($data);
                $fileMeta = $this->storeUploadedFormFile($request->file('file'), (int) $form->client_id, (int) $form->id);
                $form->update($fileMeta);

                return $form;
            });
        } catch (\Throwable $e) {
            Log::error('Legal form upload failed', ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Could not upload the form file.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => (ClientLegalForm::FORM_TYPES[$form->form_type] ?? 'Form') . ' uploaded successfully.',
            'form' => $form->load(['client', 'matter', 'creator']),
        ]);
    }

    public function show(ClientLegalForm $legalForm): JsonResponse
    {
        $this->ensureCrmRecordAccess((int) $legalForm->client_id);
        return response()->json([
            'success' => true,
            'form' => $legalForm->load(['client', 'matter', 'creator']),
        ]);
    }

    public function update(Request $request, ClientLegalForm $legalForm): JsonResponse
    {
        $this->ensureCrmRecordAccess((int) $legalForm->client_id);
        $request->validate([
            'scope_of_work' => 'nullable|string',
            'estimated_legal_fees' => 'nullable|numeric|min:0',
            'estimated_disbursements' => 'nullable|numeric|min:0',
            'estimated_barrister_fees' => 'nullable|numeric|min:0',
            'fee_type' => 'nullable|string|in:fixed,hourly',
            'fixed_fee_amount' => 'nullable|numeric|min:0',
            'person_responsible' => 'nullable|string|max:255',
            'person_responsible_email' => 'nullable|string|max:255',
            'authority_scope' => 'nullable|string',
        ]);

        $data = $request->except(['client_id', 'client_matter_id', 'pdf_path', 'is_uploaded', 'form_type', 'created_by', 'trust_account_name', 'trust_bsb', 'trust_account_number']);

        $numericFields = [
            'estimated_legal_fees', 'estimated_disbursements', 'estimated_barrister_fees',
            'gst_amount', 'estimated_total', 'fixed_fee_amount', 'retainer_amount',
        ];
        foreach ($numericFields as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = floatval($data[$field] ?? 0);
            }
        }

        if (in_array($legalForm->form_type, ['short_costs_disclosure', 'cost_agreement'])) {
            $fees = floatval($data['estimated_legal_fees'] ?? $legalForm->estimated_legal_fees);
            $disbursements = floatval($data['estimated_disbursements'] ?? $legalForm->estimated_disbursements);
            $barrister = floatval($data['estimated_barrister_fees'] ?? $legalForm->estimated_barrister_fees);
            $data['gst_amount'] = round($fees * 0.10, 2);
            $data['estimated_total'] = $fees + $disbursements + $barrister + $data['gst_amount'];
        }

        // Uploaded forms are source PDFs/DOCXs — never regenerate or overwrite their stored paths.
        if ($legalForm->is_uploaded) {
            $legalForm->update($data);
            $this->previewService->forgetHtmlCache((int) $legalForm->id);

            return response()->json([
                'success' => true,
                'message' => 'Form updated successfully.',
                'form' => $legalForm->fresh()->load(['client', 'matter', 'creator']),
            ]);
        }

        try {
            DB::transaction(function () use ($legalForm, $data) {
                $legalForm->update($data);
                $docxPath = $this->docxService->generate($legalForm);
                $legalForm->update(['pdf_path' => $docxPath]);
                $this->previewService->forgetHtmlCache((int) $legalForm->id);
            });
        } catch (\Throwable $e) {
            Log::error('Legal form update failed', ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Could not regenerate the Word document.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Form updated successfully.',
            'form' => $legalForm->fresh()->load(['client', 'matter', 'creator']),
        ]);
    }

    public function destroy(ClientLegalForm $legalForm): JsonResponse
    {
        $this->ensureCrmRecordAccess((int) $legalForm->client_id);
        $this->deleteStoredFile($legalForm->pdf_path);
        $this->deleteStoredFile($legalForm->attachment_path);
        $this->previewService->forgetHtmlCache((int) $legalForm->id);
        $legalForm->delete();

        return response()->json([
            'success' => true,
            'message' => 'Form deleted successfully.',
        ]);
    }

    public function downloadDocx(ClientLegalForm $legalForm)
    {
        $this->ensureCrmRecordAccess((int) $legalForm->client_id);
        if ($legalForm->is_uploaded) {
            return $this->downloadUploadedFormFile($legalForm);
        }

        $docxPath = $this->previewService->ensureGeneratedDocx($legalForm);

        $fullPath = public_path($docxPath);
        if (!file_exists($fullPath)) {
            abort(404, 'Document not found.');
        }

        $client = $legalForm->client;
        $clientName = trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? ''));
        $typeLabel = str_replace(' ', '_', ClientLegalForm::FORM_TYPES[$legalForm->form_type] ?? 'Form');
        $filename = $clientName . '_' . $typeLabel . '.docx';

        return response()->download($fullPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    public function downloadAttachment(ClientLegalForm $legalForm)
    {
        $this->ensureCrmRecordAccess((int) $legalForm->client_id);
        $path = $this->fileStorage->normalize($legalForm->attachment_path);
        if ($path === '' || ! $this->fileStorage->exists($path)) {
            abort(404, 'Attachment not found.');
        }

        $filename = $legalForm->attachment_original_name ?: basename($path);

        return $this->fileStorage->downloadResponse($path, $filename);
    }

    public function uploadAttachment(Request $request, ClientLegalForm $legalForm): JsonResponse
    {
        $this->ensureCrmRecordAccess((int) $legalForm->client_id);
        $request->validate([
            'attachment' => 'required|file|mimes:jpg,jpeg,png,gif,webp,bmp,pdf,doc,docx,txt,xls,xlsx|max:10240',
        ]);

        try {
            $this->deleteStoredFile($legalForm->attachment_path);
            $attachmentMeta = $this->storeAttachment($request, (int) $legalForm->client_id);
            if ($attachmentMeta === []) {
                return response()->json([
                    'success' => false,
                    'message' => 'No attachment file was received.',
                ], 422);
            }

            $legalForm->update($attachmentMeta);
        } catch (\Throwable $e) {
            Log::error('Legal form attachment upload failed', [
                'legal_form_id' => $legalForm->id,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Could not upload the attachment.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Attachment uploaded successfully.',
            'form' => $legalForm->fresh()->load(['client', 'matter', 'creator']),
        ]);
    }

    /**
     * @return array{pdf_path: string, attachment_path: string, attachment_original_name: string}
     */
    private function storeUploadedFormFile(\Illuminate\Http\UploadedFile $file, int $clientId, int $formId): array
    {
        $dir = 'legal_forms/' . $clientId . '/uploads';
        $originalName = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());
        $safeName = time() . '_' . $formId . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($originalName, PATHINFO_FILENAME)) . '.' . $extension;
        $relativePath = $dir . '/' . $safeName;

        $this->fileStorage->putUploadedFile($file, $relativePath);
        if (! $this->fileStorage->exists($relativePath)) {
            throw new \RuntimeException('Uploaded form file could not be saved to storage.');
        }

        return [
            'pdf_path' => $relativePath,
            'attachment_path' => $relativePath,
            'attachment_original_name' => $originalName,
        ];
    }

    private function validateUploadedDocument(?\Illuminate\Http\UploadedFile $file): void
    {
        if (! $file || ! $file->isValid()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' => ['Please select a valid file to upload.'],
            ]);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $originalName = strtolower($file->getClientOriginalName());

        if ($extension === '' || in_array($extension, self::UPLOAD_BLOCKED_EXTENSIONS, true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' => ['This file type is not allowed. Images, scripts, and executable files cannot be uploaded.'],
            ]);
        }

        if (! in_array($extension, self::UPLOAD_ALLOWED_EXTENSIONS, true)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' => ['Only document files are allowed (PDF, Word, Excel, PowerPoint, text, etc.).'],
            ]);
        }

        foreach (self::UPLOAD_BLOCKED_EXTENSIONS as $blocked) {
            if (str_ends_with($originalName, '.' . $blocked)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'file' => ['This file type is not allowed. Images, scripts, and executable files cannot be uploaded.'],
                ]);
            }
        }

        $mimeType = strtolower((string) $file->getMimeType());
        if (str_starts_with($mimeType, 'image/') || str_starts_with($mimeType, 'application/x-msdownload')) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'file' => ['This file type is not allowed. Images and executable files cannot be uploaded.'],
            ]);
        }
    }

    private function resolveUploadedRelativePath(ClientLegalForm $legalForm): ?string
    {
        $candidates = array_values(array_unique(array_filter([
            $this->fileStorage->normalize($legalForm->pdf_path),
            $this->fileStorage->normalize($legalForm->attachment_path),
        ])));

        foreach ($candidates as $path) {
            $this->fileStorage->promoteLegacyToCloud($path);
            if ($this->fileStorage->exists($path)) {
                return $path;
            }
        }

        return $candidates[0] ?? null;
    }

    private function downloadUploadedFormFile(ClientLegalForm $legalForm)
    {
        $relativePath = $this->resolveUploadedRelativePath($legalForm);
        if ($relativePath === null || ! $this->fileStorage->exists($relativePath)) {
            abort(404, 'Uploaded form file not found.');
        }

        $filename = $legalForm->attachment_original_name ?: basename($relativePath);

        return $this->fileStorage->downloadResponse($relativePath, $filename);
    }

    private function previewUploadedFormFile(Request $request, ClientLegalForm $legalForm)
    {
        $relativePath = $this->resolveUploadedRelativePath($legalForm);
        $filename = $legalForm->attachment_original_name
            ?: ($relativePath ? basename($relativePath) : 'uploaded-form');
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($extension === '' && $relativePath) {
            $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        }
        $safeFilename = str_replace('"', '\\"', $filename);

        if ($relativePath === null || ! $this->fileStorage->exists($relativePath)) {
            if ($request->boolean('embed')) {
                return response(
                    $this->legalFormPreviewErrorHtml('The uploaded file could not be found in storage. Please re-upload the form.'),
                    404
                )->header('Content-Type', 'text/html; charset=UTF-8');
            }

            abort(404, 'Uploaded form file not found.');
        }

        if ($request->boolean('embed')) {
            if ($extension === 'pdf') {
                $pdfData = $this->fileStorage->get($relativePath);
                if (! is_string($pdfData) || $pdfData === '') {
                    return response($this->legalFormPreviewErrorHtml('The uploaded PDF could not be loaded.'), 404)
                        ->header('Content-Type', 'text/html; charset=UTF-8');
                }

                return response($pdfData, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline; filename="' . str_replace('"', '\\"', pathinfo($filename, PATHINFO_FILENAME) . '.pdf') . '"',
                ]);
            }

            if (in_array($extension, ['doc', 'docx', 'rtf', 'odt'], true)) {
                $resolved = $this->fileStorage->resolveReadablePath($relativePath, $extension);
                if ($resolved !== null) {
                    try {
                        $htmlPreview = $this->previewService->htmlPreviewFromPath($resolved['path'], $filename, $legalForm);
                        if ($htmlPreview !== null) {
                            return response($htmlPreview, 200, [
                                'Content-Type' => 'text/html; charset=UTF-8',
                            ]);
                        }
                    } finally {
                        if (! empty($resolved['temporary']) && is_file($resolved['path'])) {
                            @unlink($resolved['path']);
                        }
                    }
                }
            }

            return response($this->legalFormPreviewErrorHtml('Preview is not available for this file type. Use Download to open the file.'), 503)
                ->header('Content-Type', 'text/html; charset=UTF-8');
        }

        $mimeTypes = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'txt' => 'text/plain',
            'rtf' => 'application/rtf',
            'odt' => 'application/vnd.oasis.opendocument.text',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'csv' => 'text/csv',
        ];

        $headers = [
            'Content-Type' => $mimeTypes[$extension] ?? 'application/octet-stream',
        ];

        if ($request->boolean('download')) {
            return $this->fileStorage->downloadResponse($relativePath, $safeFilename, $headers, true);
        }

        return $this->fileStorage->inlineResponse($relativePath, $safeFilename, $headers);
    }

    /**
     * @return array{attachment_path?: string, attachment_original_name?: string}
     */
    private function storeAttachment(Request $request, int $clientId): array
    {
        if (! $request->hasFile('attachment')) {
            return [];
        }

        $file = $request->file('attachment');
        $dir = 'legal_forms/' . $clientId . '/attachments';
        $originalName = $file->getClientOriginalName();
        $safeName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
        $relativePath = $dir . '/' . $safeName;

        $this->fileStorage->putUploadedFile($file, $relativePath);
        if (! $this->fileStorage->exists($relativePath)) {
            throw new \RuntimeException('Attachment file could not be saved to storage.');
        }

        return [
            'attachment_path' => $relativePath,
            'attachment_original_name' => $originalName,
        ];
    }

    private function deleteStoredFile(?string $relativePath): void
    {
        $this->fileStorage->delete($relativePath);
    }

    public function previewDocx(Request $request, ClientLegalForm $legalForm)
    {
        $this->ensureCrmRecordAccess((int) $legalForm->client_id);

        if ($legalForm->is_uploaded) {
            return $this->previewUploadedFormFile($request, $legalForm);
        }

        $docxPath = $this->previewService->ensureGeneratedDocx($legalForm);

        $fullPath = public_path($docxPath);
        if (! file_exists($fullPath)) {
            abort(404, 'Document not found.');
        }

        $client = $legalForm->client;
        $clientName = trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? ''));
        $typeLabel = str_replace(' ', '_', ClientLegalForm::FORM_TYPES[$legalForm->form_type] ?? 'Form');
        $filename = $clientName . '_' . $typeLabel . '.docx';
        $safeFilename = str_replace('"', '\\"', $filename);

        if ($request->boolean('embed')) {
            $htmlPreview = $this->previewService->htmlPreviewFromPath($fullPath, $filename, $legalForm);
            if ($htmlPreview !== null) {
                return response($htmlPreview, 200, [
                    'Content-Type' => 'text/html; charset=UTF-8',
                ]);
            }

            Log::warning('Legal form HTML preview failed', [
                'legal_form_id' => $legalForm->id,
            ]);

            return response($this->legalFormPreviewErrorHtml(), 503)
                ->header('Content-Type', 'text/html; charset=UTF-8');
        }

        $disposition = $request->boolean('download') ? 'attachment' : 'inline';

        return response()->file($fullPath, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => $disposition . '; filename="' . $safeFilename . '"',
        ]);
    }

    private function legalFormPreviewErrorHtml(string $message = ''): string
    {
        $body = $message !== ''
            ? htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
            : 'Unable to preview this form. Use Download to open the Word document.';

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Preview unavailable</title>'
            . '<style>body{font-family:Segoe UI,Arial,sans-serif;padding:24px;color:#444;background:#fff;}</style></head>'
            . '<body><p>' . $body . '</p></body></html>';
    }

    public function getClientForms(Request $request): JsonResponse
    {
        $request->validate([
            'client_id' => 'required|exists:admins,id',
            'matter_id' => 'nullable|integer',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $clientId = (int) $request->query('client_id');
        $this->ensureCrmRecordAccess($clientId);

        $matterId = $request->query('matter_id');
        $perPage = (int) ($request->query('per_page') ?: config('crm.legal_forms.list_per_page', 20));
        $perPage = max(5, min(100, $perPage));

        $query = ClientLegalForm::query()
            ->where('client_id', $clientId)
            ->select([
                'id',
                'client_id',
                'client_matter_id',
                'created_by',
                'form_type',
                'is_uploaded',
                'matter_reference',
                'estimated_legal_fees',
                'estimated_disbursements',
                'estimated_barrister_fees',
                'gst_amount',
                'estimated_total',
                'retainer_amount',
                'attachment_path',
                'attachment_original_name',
                'form_date',
                'created_at',
            ])
            ->with([
                'matter:id,client_unique_matter_no',
                'creator:id,first_name,last_name',
            ]);

        if ($matterId) {
            $query->where(function ($q) use ($matterId) {
                $q->where('client_matter_id', $matterId)
                  ->orWhereNull('client_matter_id');
            });
        }

        $paginator = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'forms' => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'has_more' => $paginator->hasMorePages(),
        ]);
    }

    public function generateScopeAI(Request $request): JsonResponse
    {
        $request->validate([
            'client_id' => 'required|exists:admins,id',
            'client_matter_id' => 'nullable|exists:client_matters,id',
            'matter_reference' => 'nullable|string|max:100',
            'form_type' => 'required|in:short_costs_disclosure,cost_agreement,authority_to_act',
            'field' => 'required|in:scope_of_work,authority_scope,variables_affecting_costs',
        ]);

        $this->ensureCrmRecordAccess((int) $request->client_id);

        $staffId = (int) Auth::id();
        $jobId = $this->scopeAiService->start([
            'client_id' => (int) $request->client_id,
            'client_matter_id' => $request->filled('client_matter_id') ? (int) $request->client_matter_id : null,
            'matter_reference' => $request->filled('matter_reference') ? (string) $request->matter_reference : null,
            'form_type' => (string) $request->form_type,
            'field' => (string) $request->field,
        ], $staffId);

        return response()->json([
            'success' => true,
            'job_id' => $jobId,
            'status' => 'queued',
            'message' => 'AI generation queued.',
        ]);
    }

    public function generateScopeAiStatus(string $jobId): JsonResponse
    {
        $staffId = (int) Auth::id();
        $status = $this->scopeAiService->getStatus($jobId, $staffId);

        if ($status === null) {
            return response()->json([
                'success' => false,
                'status' => 'not_found',
                'message' => 'AI generation job not found or expired.',
            ], 404);
        }

        $state = (string) ($status['status'] ?? 'queued');

        return response()->json([
            'success' => in_array($state, ['queued', 'processing', 'completed'], true),
            'job_id' => $jobId,
            'status' => $state,
            'message' => $status['message'] ?? null,
            'text' => $status['text'] ?? null,
        ]);
    }
}
