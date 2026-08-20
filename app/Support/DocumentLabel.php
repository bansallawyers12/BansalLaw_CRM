<?php

namespace App\Support;

final class DocumentLabel
{
    /**
     * Normalize a folder/checklist label from user input or legacy HTML-encoded values.
     */
    public static function normalize(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Escape a label for safe HTML text/attribute output.
     */
    public static function forDisplay(?string $value): string
    {
        return htmlspecialchars(self::normalize($value), ENT_QUOTES, 'UTF-8');
    }
}
