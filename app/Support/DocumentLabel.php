<?php

namespace App\Support;

use App\Models\Document;

final class DocumentLabel
{
    /**
     * Normalize a folder/checklist label from user input or legacy HTML-encoded values.
     * Keeps apostrophes, parentheses, and other display characters.
     */
    public static function normalize(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Normalize curly quotes so "Partner's" / "Partner's" match consistently.
        $value = str_replace(
            ["\u{2018}", "\u{2019}", "\u{201A}", "\u{201B}", "\u{201C}", "\u{201D}", "\u{2032}"],
            ["'", "'", "'", "'", '"', '"', "'"],
            $value
        );

        return $value;
    }

    /**
     * Escape a label for safe HTML text/attribute output.
     */
    public static function forDisplay(?string $value): string
    {
        return htmlspecialchars(self::normalize($value), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitize a single path segment for S3 / disk object keys.
     * Checklist/folder labels may contain special characters; storage keys must not.
     */
    public static function sanitizeStorageSegment(?string $value): string
    {
        $value = self::normalize($value);
        $value = str_replace(["/", "\\", "\0"], '_', $value);
        $value = preg_replace('/[^A-Za-z0-9._-]+/', '_', $value) ?? '';
        $value = preg_replace('/_+/', '_', $value) ?? '';
        $value = trim($value, '._-');

        return $value !== '' ? $value : 'document';
    }

    /**
     * Stored basename: {client reference}_{document name}[.ext]
     * The document name is the checklist label (or the original file name for note attachments).
     */
    public static function buildClientFileName(
        string $clientReference,
        string $normalName,
        ?string $extension = null
    ): string {
        $reference = self::sanitizeStorageSegment($clientReference);
        if ($reference === 'document') {
            $reference = 'client';
        }

        $base = $reference . '_' . self::sanitizeStorageSegment($normalName);

        return self::withExtension($base, $extension);
    }

    /**
     * Names for a new upload. If another document for this client already uses the
     * same storage key, the document id is appended when we have one. A new upload
     * that does not have an id yet gets _2, _3, and so on, so it does not replace
     * the earlier file.
     *
     * @return array{file_name: string, storage_key: string}
     */
    public static function storedUploadName(
        string $clientReference,
        string $normalName,
        ?string $extension,
        int $documentId = 0,
        int $ownerClientId = 0,
        int $fallbackDisambiguator = 0,
        ?string $docType = null
    ): array {
        $fileName = self::buildClientFileName($clientReference, $normalName);
        $storageKey = self::withExtension($fileName, $extension);
        [$fileName, $storageKey] = self::avoidTakenKey(
            $fileName,
            $storageKey,
            $extension,
            $documentId > 0 ? $documentId : $fallbackDisambiguator,
            $ownerClientId,
            $docType
        );

        return [
            'file_name' => $fileName,
            'storage_key' => $storageKey,
        ];
    }

    /**
     * A file with no client reference keeps the original name only.
     *
     * @return array{file_name: string, storage_key: string}
     */
    public static function plainStoredName(
        string $normalName,
        ?string $extension,
        int $documentId = 0,
        int $ownerClientId = 0,
        ?string $docType = null
    ): array {
        $fileName = self::sanitizeStorageSegment($normalName);
        if ($fileName === 'document') {
            $fileName = 'file';
        }
        $storageKey = self::withExtension($fileName, $extension);
        [$fileName, $storageKey] = self::avoidTakenKey(
            $fileName,
            $storageKey,
            $extension,
            $documentId,
            $ownerClientId,
            $docType
        );

        return [
            'file_name' => $fileName,
            'storage_key' => $storageKey,
        ];
    }

    /**
     * Client files are {client reference}_{original name}. No reference means the original name only.
     *
     * @return array{file_name: string, storage_key: string}
     */
    public static function clientFileNames(
        string $clientReference,
        string $originalFileName,
        int $documentId = 0,
        int $ownerClientId = 0,
        ?string $docType = null
    ): array {
        $stem = pathinfo($originalFileName, PATHINFO_FILENAME);
        $extension = pathinfo($originalFileName, PATHINFO_EXTENSION);
        $normal = $stem !== '' ? $stem : $originalFileName;
        if (trim($clientReference) === '') {
            return self::plainStoredName($normal, $extension, $documentId, $ownerClientId, $docType);
        }

        return self::storedUploadName($clientReference, $normal, $extension, $documentId, $ownerClientId, 0, $docType);
    }

    /**
     * Name a person downloads. Prefer the saved document name over the storage key.
     */
    public static function downloadName(?string $fileName, ?string $fileType = null, ?string $storageKey = null): string
    {
        $name = trim(self::normalize($fileName));
        $ext = strtolower(trim((string) $fileType));
        if (str_contains($ext, '/')) {
            $ext = '';
        } else {
            $ext = preg_replace('/[^a-z0-9]+/', '', $ext) ?: '';
        }

        if ($name !== '') {
            $current = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if ($ext !== '' && $current !== $ext) {
                return $name . '.' . $ext;
            }

            return $name;
        }

        $key = basename(str_replace('\\', '/', trim((string) $storageKey)));

        return $key !== '' ? $key : 'document';
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function avoidTakenKey(
        string $fileName,
        string $storageKey,
        ?string $extension,
        int $documentId,
        int $ownerClientId,
        ?string $docType
    ): array {
        if ($ownerClientId <= 0) {
            return [$fileName, $storageKey];
        }

        $base = $fileName;
        $n = 2;
        while (self::storageKeyTaken($storageKey, $ownerClientId, $documentId, $docType)) {
            $suffix = $documentId > 0
                ? ($n === 2 ? (string) $documentId : $documentId.'_'.$n)
                : (string) $n;
            $fileName = $base . '_' . $suffix;
            $storageKey = self::withExtension($fileName, $extension);
            $n++;
            if ($n > 30) {
                break;
            }
        }

        return [$fileName, $storageKey];
    }

    public static function storageKeyTaken(
        string $storageKey,
        int $ownerClientId,
        int $exceptDocumentId = 0,
        ?string $docType = null
    ): bool {
        return Document::query()
            ->where('client_id', $ownerClientId)
            ->when($exceptDocumentId > 0, fn ($query) => $query->where('id', '!=', $exceptDocumentId))
            ->when($docType !== null && $docType !== '', fn ($query) => $query->where('doc_type', $docType))
            ->where('myfile_key', $storageKey)
            ->exists();
    }

    private static function withExtension(string $base, ?string $extension): string
    {
        if ($extension === null || $extension === '') {
            return $base;
        }

        $ext = preg_replace('/[^A-Za-z0-9]+/', '', strtolower($extension)) ?: 'bin';

        return $base . '.' . $ext;
    }
}
