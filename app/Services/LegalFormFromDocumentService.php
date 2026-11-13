<?php

namespace App\Services;

use App\Models\ClientLegalForm;
use App\Models\ClientMatter;
use App\Models\Document;
use App\Models\PersonalDocumentType;
use App\Models\VisaDocumentType;
use App\Support\DocumentLabel;
use Illuminate\Support\Facades\DB;

class LegalFormFromDocumentService
{
    /** @var list<string> */
    public const ALLOWED_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'txt', 'rtf', 'odt', 'xls', 'xlsx', 'ppt', 'pptx', 'csv',
    ];

    /** Statuses that mean a signature request is still open. */
    private const AWAITING_SIGNATURE = ['sent', 'pending', 'placed', 'viewed'];

    public function __construct(
        private LegalFormFileStorage $fileStorage
    ) {}

    public function suggestFormType(Document $document): ?string
    {
        $haystack = $this->labelHaystack($document);

        if (str_contains($haystack, 'authority to act') || preg_match('/\ba2a\b/', $haystack) === 1) {
            return 'authority_to_act';
        }

        if (str_contains($haystack, 'short') && str_contains($haystack, 'cost')) {
            return 'short_costs_disclosure';
        }

        if (
            (str_contains($haystack, 'long') && str_contains($haystack, 'cost'))
            || str_contains($haystack, 'cost agreement')
            || str_contains($haystack, 'costs agreement')
        ) {
            return 'cost_agreement';
        }

        return null;
    }

    public function refusalReason(Document $document): ?string
    {
        if ((int) $document->client_id <= 0) {
            return 'Link this document to a client before moving it to Legal Forms.';
        }

        if ((int) $document->not_used_doc === Document::FILED_AS_LEGAL_FORM) {
            return 'This document is already on Legal Forms.';
        }

        if ($document->exists && ClientLegalForm::query()->where('source_document_id', $document->id)->exists()) {
            return 'This document is already on Legal Forms.';
        }

        $status = strtolower(trim((string) $document->status));
        if (in_array($status, self::AWAITING_SIGNATURE, true)) {
            return 'This document is still waiting for a signature. Move it to Legal Forms after it is signed.';
        }

        if ($document->exists && $document->signers()->whereNotIn('status', ['signed', 'completed', 'declined'])->exists()) {
            return 'This document is still waiting for a signature. Move it to Legal Forms after it is signed.';
        }

        $extension = $this->extensionOf($document);
        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return 'This file type cannot be stored as a legal form. Use a PDF or Word file.';
        }

        return null;
    }

    /**
     * Copy the file onto a legal form and hide the document from document lists.
     */
    public function storeFromDocument(
        Document $document,
        string $formType,
        int $createdBy,
        string $bytes
    ): ClientLegalForm {
        if (! isset(ClientLegalForm::FORM_TYPES[$formType])) {
            throw new \InvalidArgumentException('Choose a legal form type.');
        }

        $reason = $this->refusalReason($document);
        if ($reason !== null) {
            throw new \RuntimeException($reason);
        }

        if ($bytes === '') {
            throw new \RuntimeException('The file could not be read from storage.');
        }

        $extension = $this->extensionOf($document);
        $originalName = $this->originalName($document, $extension);
        $relativePath = $this->storagePath((int) $document->client_id, (int) $document->id, $originalName, $extension);

        $matterId = null;
        $matterReference = null;
        if ($document->client_matter_id) {
            $matter = ClientMatter::query()
                ->where('id', $document->client_matter_id)
                ->where('client_id', $document->client_id)
                ->first(['id', 'client_unique_matter_no']);
            if ($matter) {
                $matterId = (int) $matter->id;
                $reference = trim((string) $matter->client_unique_matter_no);
                $matterReference = $reference !== '' ? $reference : null;
            }
        }

        $formDate = $document->created_at ? $document->created_at->toDateString() : now()->toDateString();
        $signedDate = null;
        if (strtolower((string) $document->status) === 'signed' && $document->exists) {
            $signedAt = $document->signers()->whereNotNull('signed_at')->orderByDesc('signed_at')->value('signed_at');
            if ($signedAt) {
                $signedDate = \Illuminate\Support\Carbon::parse($signedAt)->toDateString();
            }
        }

        return DB::transaction(function () use (
            $document,
            $formType,
            $createdBy,
            $bytes,
            $relativePath,
            $originalName,
            $matterId,
            $matterReference,
            $formDate,
            $signedDate
        ) {
            $this->fileStorage->putBytes($relativePath, $bytes);
            if (! $this->fileStorage->exists($relativePath)) {
                throw new \RuntimeException('The file could not be saved to legal form storage.');
            }

            try {
                $form = ClientLegalForm::create([
                    'client_id' => $document->client_id,
                    'client_matter_id' => $matterId,
                    'created_by' => $createdBy,
                    'source_document_id' => $document->id,
                    'form_type' => $formType,
                    'is_uploaded' => true,
                    'matter_reference' => $matterReference,
                    'form_date' => $formDate,
                    'signed_date' => $signedDate,
                    'pdf_path' => $relativePath,
                    'attachment_path' => $relativePath,
                    'attachment_original_name' => $originalName,
                ]);
                $document->not_used_doc = Document::FILED_AS_LEGAL_FORM;
                $document->save();

                return $form;
            } catch (\Throwable $e) {
                $this->fileStorage->delete($relativePath);
                throw $e;
            }
        });
    }

    public function extensionOf(Document $document): string
    {
        foreach ([$document->file_name, $document->myfile_key] as $name) {
            $extension = strtolower((string) pathinfo((string) $name, PATHINFO_EXTENSION));
            if ($extension !== '' && preg_match('/^[a-z0-9]{1,8}$/', $extension)) {
                return $extension;
            }
        }

        $filetype = strtolower(trim((string) $document->filetype));
        if ($filetype !== '' && ! str_contains($filetype, '/') && preg_match('/^[a-z0-9]{1,8}$/', $filetype)) {
            return $filetype;
        }

        return match ($filetype) {
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'text/plain' => 'txt',
            'application/rtf', 'text/rtf' => 'rtf',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'text/csv' => 'csv',
            default => '',
        };
    }

    private function originalName(Document $document, string $extension): string
    {
        $name = trim((string) ($document->file_name ?: $document->checklist ?: 'document'));
        $existing = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        if ($existing === '' && $extension !== '') {
            $name .= '.'.$extension;
        }

        return $name;
    }

    private function storagePath(int $clientId, int $documentId, string $originalName, string $extension): string
    {
        $stem = pathinfo($originalName, PATHINFO_FILENAME);
        $safeName = DocumentLabel::plainStoredName(
            $documentId.'_'.($stem !== '' ? $stem : 'document'),
            $extension
        )['storage_key'];

        return 'legal_forms/'.$clientId.'/uploads/'.$safeName;
    }

    private function labelHaystack(Document $document): string
    {
        $parts = [
            (string) $document->file_name,
            (string) $document->checklist,
            (string) $document->myfile_key,
        ];

        $folderId = $document->folder_name;
        if ($folderId !== null && $folderId !== '' && is_numeric($folderId)) {
            if ($document->doc_type === 'personal') {
                $parts[] = (string) PersonalDocumentType::query()->where('id', $folderId)->value('title');
            } elseif (in_array($document->doc_type, ['matter', 'visa'], true)) {
                $parts[] = (string) VisaDocumentType::query()->where('id', $folderId)->value('title');
            }
        }

        $haystack = strtolower(implode(' ', array_filter($parts, fn ($part) => trim((string) $part) !== '')));
        $haystack = str_replace(['_', '-'], ' ', $haystack);

        return preg_replace('/\s+/', ' ', $haystack) ?? $haystack;
    }
}
