<?php

namespace App\Services;

use App\Models\Note;
use App\Models\NoteAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NoteAttachmentService
{
    public const MAX_FILES = 10;
    public const MAX_BYTES = 20971520; // 20 MB

    public const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp',
        'pdf',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'csv', 'txt', 'rtf', 'odt',
        'zip',
    ];

    public static function disk()
    {
        return Storage::disk('local');
    }

    /**
     * @param  UploadedFile[]|UploadedFile|null  $files
     */
    public static function validateUploads($files): ?string
    {
        $files = self::normalizeFiles($files);
        if ($files === []) {
            return null;
        }

        if (count($files) > self::MAX_FILES) {
            return 'You can attach a maximum of ' . self::MAX_FILES . ' files per note.';
        }

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                return 'One of the selected files could not be uploaded. Please try again.';
            }

            $original = $file->getClientOriginalName();
            if (! PersonalDocumentVideoUploadService::isSafeOriginalFilename($original)) {
                return 'File name cannot contain slashes. Please rename the file and try again.';
            }

            $ext = strtolower((string) $file->getClientOriginalExtension());
            if ($ext === '' || ! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                return "File type .{$ext} is not allowed. Use images, PDF, Word, Excel, PowerPoint, or similar documents.";
            }

            if ((int) $file->getSize() > self::MAX_BYTES) {
                return "'{$original}' is larger than 20 MB.";
            }
        }

        return null;
    }

    /**
     * @param  UploadedFile[]|UploadedFile|null  $files
     */
    public static function storeForNote(Note $note, $files): void
    {
        $files = self::normalizeFiles($files);
        if ($files === []) {
            return;
        }

        $disk = self::disk();
        $dir = 'note_attachments/' . (int) $note->client_id . '/' . (int) $note->id;

        foreach ($files as $file) {
            $ext = strtolower((string) $file->getClientOriginalExtension());
            $storedName = Str::uuid()->toString() . ($ext !== '' ? '.' . $ext : '');
            $path = $disk->putFileAs($dir, $file, $storedName);

            if (! $path) {
                Log::warning('Note attachment store failed', [
                    'note_id' => $note->id,
                    'original' => $file->getClientOriginalName(),
                ]);
                continue;
            }

            $attachment = NoteAttachment::create([
                'note_id' => $note->id,
                'client_id' => $note->client_id,
                'uploaded_by' => Auth::guard('admin')->id() ?: Auth::id(),
                'original_name' => $file->getClientOriginalName(),
                'stored_path' => $path,
                'mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
                'extension' => $ext,
                'file_size' => (int) $file->getSize(),
            ]);

            try {
                app(NoteMatterDocumentSyncService::class)->syncAttachment($note, $attachment, $file);
            } catch (\Throwable $e) {
                Log::warning('Note attachment could not sync to matter documents', [
                    'note_id' => $note->id,
                    'attachment_id' => $attachment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public static function deleteAttachment(NoteAttachment $attachment): void
    {
        self::deleteStoredFile($attachment->stored_path);
        $attachment->delete();
    }

    public static function deleteAllForNote(Note $note): void
    {
        $attachments = NoteAttachment::where('note_id', $note->id)->get();
        foreach ($attachments as $attachment) {
            self::deleteStoredFile($attachment->stored_path);
            $attachment->delete();
        }
    }

    public static function deleteStoredFile(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        try {
            $disk = self::disk();
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        } catch (\Throwable $e) {
            Log::warning('Could not delete note attachment file: ' . $e->getMessage(), [
                'path' => $path,
            ]);
        }
    }

    public static function absolutePath(NoteAttachment $attachment): ?string
    {
        $path = (string) $attachment->stored_path;
        if ($path === '') {
            return null;
        }

        $disk = self::disk();
        if (! $disk->exists($path)) {
            return null;
        }

        return $disk->path($path);
    }

    /**
     * @return UploadedFile[]
     */
    private static function normalizeFiles($files): array
    {
        if ($files === null || $files === []) {
            return [];
        }

        if ($files instanceof UploadedFile) {
            return [$files];
        }

        if (! is_array($files)) {
            return [];
        }

        return array_values(array_filter($files, fn ($f) => $f instanceof UploadedFile));
    }
}
