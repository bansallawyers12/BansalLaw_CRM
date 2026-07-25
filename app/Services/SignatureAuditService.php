<?php

namespace App\Services;

use App\Models\Document;
use App\Models\SignatureActivity;
use App\Models\Signer;
use App\Models\Staff;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SignatureAuditService
{
    public const ACTOR_STAFF = 'staff';
    public const ACTOR_SIGNER = 'signer';
    public const ACTOR_SYSTEM = 'system';

    /**
     * Append-only audit event for a signature document.
     */
    public function log(
        Document $document,
        string $actionType,
        string $note,
        array $metadata = [],
        ?Signer $signer = null,
        ?string $actorType = null,
        ?Request $request = null
    ): SignatureActivity {
        $request = $request ?? request();
        $actorType = $actorType ?? $this->resolveActorType($signer);

        $activity = SignatureActivity::create([
            'document_id' => $document->id,
            'signer_id' => $signer?->id,
            'created_by' => $actorType === self::ACTOR_STAFF ? $this->activityCreatorId() : null,
            'actor_type' => $actorType,
            'action_type' => $actionType,
            'note' => $note,
            'metadata' => array_merge([
                'request_id' => (string) Str::uuid(),
            ], $metadata),
            'ip_address' => $request?->ip(),
            'user_agent' => Str::limit((string) $request?->userAgent(), 500, ''),
        ]);

        return $activity;
    }

    /**
     * Record completion evidence: hashes + certificate of completion.
     *
     * @param  array<int, string>  $signatureImagePaths  Local paths to signature PNGs
     * @return array{signed_hash: ?string, original_hash: ?string, certificate_path: ?string}
     */
    public function recordCompletionEvidence(
        Document $document,
        Signer $signer,
        ?string $originalPdfPath,
        ?string $signedPdfPath,
        array $signatureImagePaths = [],
        ?Request $request = null
    ): array {
        $request = $request ?? request();

        $originalHash = $this->hashFile($originalPdfPath);
        $signedHash = $this->hashFile($signedPdfPath);

        $signatureHashes = [];
        foreach ($signatureImagePaths as $index => $path) {
            $hash = $this->hashFile($path);
            if ($hash) {
                $signatureHashes[] = [
                    'index' => $index,
                    'sha256' => $hash,
                ];
            }
        }

        $evidence = [
            'document_id' => $document->id,
            'document_title' => $document->display_title ?? $document->file_name,
            'signer_id' => $signer->id,
            'signer_name' => $signer->name,
            'signer_email' => $signer->email,
            'sent_at' => optional($signer->created_at)?->toIso8601String(),
            'opened_at' => optional($signer->opened_at)?->toIso8601String(),
            'signed_at' => now()->toIso8601String(),
            'ip_address' => $request?->ip(),
            'user_agent' => Str::limit((string) $request?->userAgent(), 500, ''),
            'original_sha256' => $originalHash,
            'signed_sha256' => $signedHash,
            'signature_image_hashes' => $signatureHashes,
            'field_positions' => $document->signatureFields()->get([
                'id', 'page_number', 'x_percent', 'y_percent', 'width_percent', 'height_percent',
            ])->toArray(),
            'sent_by_staff_id' => $document->created_by,
        ];

        $certificatePath = null;
        try {
            $certificatePath = $this->generateCertificateOfCompletion($document, $signer, $evidence);
        } catch (\Throwable $e) {
            Log::warning('Failed to generate signature certificate of completion', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);
        }

        $document->forceFill([
            'original_hash' => $originalHash,
            'signed_hash' => $signedHash,
            'hash_generated_at' => now(),
            'certificate_path' => $certificatePath,
        ])->save();

        $this->log(
            $document,
            'signed',
            "Document signed by {$signer->name} ({$signer->email})",
            [
                'signer_id' => $signer->id,
                'signer_name' => $signer->name,
                'signer_email' => $signer->email,
                'evidence' => $evidence,
                'certificate_path' => $certificatePath,
            ],
            $signer,
            self::ACTOR_SIGNER,
            $request
        );

        return [
            'signed_hash' => $signedHash,
            'original_hash' => $originalHash,
            'certificate_path' => $certificatePath,
        ];
    }

    /**
     * Build and store a Certificate of Completion PDF.
     */
    public function generateCertificateOfCompletion(Document $document, Signer $signer, array $evidence): string
    {
        $timeline = SignatureActivity::forDocument($document->id)
            ->orderBy('created_at')
            ->get();

        $pdf = PDF::loadView('crm.signatures.certificate', [
            'document' => $document,
            'signer' => $signer,
            'evidence' => $evidence,
            'timeline' => $timeline,
            'generatedAt' => now(),
        ])->setPaper('a4');

        $relativePath = 'certificates/' . $document->id . '_completion_certificate.pdf';
        $bytes = $pdf->output();

        try {
            Storage::disk('s3')->put($relativePath, $bytes);
            return $relativePath;
        } catch (\Throwable $e) {
            Log::warning('Certificate S3 upload failed; storing locally', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);
            Storage::disk('public')->put($relativePath, $bytes);
            return $relativePath;
        }
    }

    public function hashFile(?string $path): ?string
    {
        if (!$path || !is_file($path)) {
            return null;
        }

        return hash_file('sha256', $path) ?: null;
    }

    public function hashContents(?string $contents): ?string
    {
        if ($contents === null || $contents === '') {
            return null;
        }

        return hash('sha256', $contents);
    }

    protected function resolveActorType(?Signer $signer): string
    {
        if ($signer) {
            return self::ACTOR_SIGNER;
        }

        if (Auth::guard('admin')->check()) {
            return self::ACTOR_STAFF;
        }

        return self::ACTOR_SYSTEM;
    }

    protected function activityCreatorId(): ?int
    {
        $id = Auth::guard('admin')->id();
        if ($id === null) {
            return null;
        }

        return Staff::whereKey($id)->exists() ? (int) $id : null;
    }
}
