<?php

namespace App\Support;

/**
 * Detect Word/Office container formats from extension + magic bytes.
 *
 * PhpWord's IOFactory::load() defaults to Word2007 (ZIP). Binary OLE .doc files
 * are not ZIPs and must use MsDoc / LegacyDocHtmlPreviewService instead.
 */
final class OfficeDocumentFormat
{
    public const OLE_MAGIC = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";

    public static function isOleCompound(string $fileContent): bool
    {
        return str_starts_with($fileContent, self::OLE_MAGIC);
    }

    public static function isOoxmlZip(string $fileContent): bool
    {
        return str_starts_with($fileContent, 'PK');
    }

    public static function isRtf(string $fileContent): bool
    {
        return str_starts_with(ltrim($fileContent), '{\\rtf');
    }

    /**
     * Resolve a word-processing extension for HTML preview.
     * Magic bytes win over a missing or mislabeled filename extension.
     */
    public static function sniffWordExtension(string $filename, string $fileContent): string
    {
        $extension = strtolower(ltrim((string) pathinfo($filename, PATHINFO_EXTENSION), '.'));
        $extension = self::normalizeWordExtensionAlias($extension);

        if (self::isOleCompound($fileContent)) {
            // Mislabeled OLE Word binaries (e.g. ".docx" / no extension).
            if ($extension === '' || in_array($extension, ['docx', 'docm', 'rtf', 'odt'], true)) {
                return 'doc';
            }

            return $extension;
        }

        if (self::isRtf($fileContent)) {
            return 'rtf';
        }

        if (self::isOoxmlZip($fileContent)) {
            if ($extension === '' || $extension === 'doc') {
                return 'docx';
            }

            return $extension;
        }

        return $extension;
    }

    /**
     * Map extension + magic bytes to a PhpWord reader name.
     * Never select Word2007 for non-ZIP bytes (avoids ZipArchive error 19).
     */
    public static function phpWordReaderName(string $extension, string $fileContent): string
    {
        $extension = self::normalizeWordExtensionAlias(strtolower(ltrim($extension, '.')));

        if (self::isOoxmlZip($fileContent)) {
            return $extension === 'odt' ? 'ODText' : 'Word2007';
        }

        if (self::isOleCompound($fileContent)) {
            return 'MsDoc';
        }

        if (self::isRtf($fileContent)) {
            return 'RTF';
        }

        return match ($extension) {
            'doc' => 'MsDoc',
            'rtf' => 'RTF',
            'odt' => 'ODText',
            // Claimed OOXML without ZIP magic would hit ZipArchive error 19 — use MsDoc.
            default => 'MsDoc',
        };
    }

    public static function isLegacyBinaryDoc(string $extension, string $fileContent): bool
    {
        $extension = self::normalizeWordExtensionAlias(strtolower(ltrim($extension, '.')));

        if (self::isOoxmlZip($fileContent)) {
            return false;
        }

        return $extension === 'doc' || self::isOleCompound($fileContent);
    }

    public static function looksLikeZipArchiveError(string $message): bool
    {
        $message = strtolower($message);

        return str_contains($message, 'archive failed')
            || str_contains($message, 'error code: 19')
            || str_contains($message, 'not a zip');
    }

    private static function normalizeWordExtensionAlias(string $extension): string
    {
        return match ($extension) {
            'msword', 'application/msword', 'word', 'dot' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            default => $extension,
        };
    }
}
