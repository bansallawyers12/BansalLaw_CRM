<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\ClientMatter;
use App\Models\Document;
use App\Models\Note;
use App\Models\NoteAttachment;
use App\Models\VisaDocumentType;
use App\Support\DocumentLabel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class NoteMatterDocumentSyncService
{
    public const FOLDER_TITLE = 'Notes';

    public function syncAttachment(Note $note, NoteAttachment $attachment, UploadedFile $file): ?array
    {
        if ((string) $note->type !== 'client') {
            return null;
        }

        $matterId = (int) ($note->matter_id ?? 0);
        $clientId = (int) ($note->client_id ?? 0);

        if ($matterId <= 0 || $clientId <= 0) {
            return null;
        }

        if (! ClientMatter::where('id', $matterId)->where('client_id', $clientId)->exists()) {
            return null;
        }

        $folderResult = $this->ensureNotesFolder($clientId, $matterId);
        $folder = $folderResult['folder'] ?? null;
        if (! $folder) {
            return null;
        }

        $admin = Admin::select('client_id', 'first_name')->where('id', $clientId)->first();
        if (! $admin || empty($admin->client_id)) {
            Log::warning('Note matter document sync skipped: client storage id missing', [
                'client_id' => $clientId,
                'note_id' => $note->id,
            ]);

            return null;
        }

        $clientUniqueId = (string) $admin->client_id;
        $clientFirstName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string) ($admin->first_name ?? 'client')) ?: 'client';
        $originalName = (string) $attachment->original_name;
        $checklistName = DocumentLabel::normalize(pathinfo($originalName, PATHINFO_FILENAME) ?: $originalName);
        if ($checklistName === '') {
            $checklistName = 'Note attachment';
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if ($extension === '') {
            $extension = strtolower((string) $attachment->extension);
        }

        $uniqueId = 'note_' . (int) $note->id . '_' . (int) $attachment->id . '_' . time();
        $storedName = DocumentLabel::buildStoredFileName($clientFirstName, $checklistName, $uniqueId, $extension);
        $filePath = $clientUniqueId . '/matter/' . $storedName;

        $disk = Storage::disk('s3');
        if (! $disk->put($filePath, $file->get())) {
            Log::warning('Note matter document sync failed: S3 upload failed', [
                'note_id' => $note->id,
                'attachment_id' => $attachment->id,
                'path' => $filePath,
            ]);

            return null;
        }

        $userId = Auth::guard('admin')->id() ?: Auth::id();

        $document = new Document();
        $document->user_id = $userId;
        $document->client_id = $clientId;
        $document->type = 'client';
        $document->doc_type = 'matter';
        $document->client_matter_id = $matterId;
        $document->folder_name = (string) $folder->id;
        $document->checklist = $checklistName;
        $document->file_name = DocumentLabel::buildStoredFileName($clientFirstName, $checklistName, $uniqueId);
        $document->filetype = $extension;
        $document->myfile = $disk->url($filePath);
        $document->myfile_key = $storedName;
        $document->file_size = (int) $attachment->file_size;
        $document->created_by = $userId;
        $document->save();

        ClientMatter::where('id', $matterId)->update(['updated_at' => now()]);

        return [
            'folder_id' => (string) $folder->id,
            'client_matter_id' => $matterId,
            'folder_created' => (bool) ($folderResult['created'] ?? false),
        ];
    }

    /**
     * @return array{folder: ?VisaDocumentType, created: bool}
     */
    private function ensureNotesFolder(int $clientId, int $matterId): array
    {
        $existing = VisaDocumentType::where('status', 1)
            ->where('client_matter_id', $matterId)
            ->whereRaw('LOWER(title) = ?', [strtolower(self::FOLDER_TITLE)])
            ->first();

        if ($existing) {
            return ['folder' => $existing, 'created' => false];
        }

        $duplicate = VisaDocumentType::where('title', self::FOLDER_TITLE)
            ->where('status', 1)
            ->where('client_matter_id', $matterId)
            ->first();

        if ($duplicate) {
            return ['folder' => $duplicate, 'created' => false];
        }

        try {
            $folder = new VisaDocumentType();
            $folder->title = self::FOLDER_TITLE;
            $folder->status = 1;
            $folder->client_id = $clientId;
            $folder->client_matter_id = $matterId;
            $folder->save();

            return ['folder' => $folder, 'created' => true];
        } catch (\Throwable $e) {
            Log::warning('Could not create Notes matter document folder', [
                'client_id' => $clientId,
                'matter_id' => $matterId,
                'error' => $e->getMessage(),
            ]);

            $fallback = VisaDocumentType::where('status', 1)
                ->where('client_matter_id', $matterId)
                ->whereRaw('LOWER(title) = ?', [strtolower(self::FOLDER_TITLE)])
                ->first();

            return ['folder' => $fallback, 'created' => false];
        }
    }
}
