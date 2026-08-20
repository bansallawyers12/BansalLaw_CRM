<?php

namespace App\Support;

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
     * Build a stored object basename: client_checklist_unique[.ext]
     */
    public static function buildStoredFileName(
        string $clientFirstName,
        string $checklistName,
        string $uniquePart,
        ?string $extension = null
    ): string {
        $base = self::sanitizeStorageSegment($clientFirstName)
            . '_' . self::sanitizeStorageSegment($checklistName)
            . '_' . self::sanitizeStorageSegment($uniquePart);

        if ($extension === null || $extension === '') {
            return $base;
        }

        $ext = preg_replace('/[^A-Za-z0-9]+/', '', strtolower($extension)) ?: 'bin';

        return $base . '.' . $ext;
    }
}
