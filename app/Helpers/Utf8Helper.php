<?php

namespace App\Helpers;

class Utf8Helper
{
    /**
     * Safely sanitize a string for UTF-8 encoding
     * This function ensures the string is valid UTF-8 without breaking existing functionality
     *
     * @param string|null $input
     * @return string
     */
    public static function safeSanitize($input)
    {
        // Return empty string for null/empty input
        if (empty($input)) {
            return '';
        }

        // Convert to string if not already
        $input = (string) $input;

        // Strategy 1: Check if string is already valid UTF-8
        if (mb_check_encoding($input, 'UTF-8')) {
            return $input;
        }

        // Strategy 2: Try to detect and convert encoding
        $detected = mb_detect_encoding($input, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'], true);
        if ($detected && $detected !== 'UTF-8') {
            $converted = mb_convert_encoding($input, 'UTF-8', $detected);
            if (mb_check_encoding($converted, 'UTF-8')) {
                return $converted;
            }
        }

        // Strategy 3: Remove invalid UTF-8 sequences
        $cleaned = mb_convert_encoding($input, 'UTF-8', 'UTF-8');
        if (mb_check_encoding($cleaned, 'UTF-8')) {
            return $cleaned;
        }

        // Strategy 4: Use PHP's filter to remove invalid sequences
        if (function_exists('filter_var')) {
            $filtered = filter_var($input, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
            if ($filtered !== false && mb_check_encoding($filtered, 'UTF-8')) {
                return $filtered;
            }
        }

        // Strategy 5: Last resort - remove non-printable characters
        $sanitized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x9F]/', '', $input);
        $sanitized = mb_convert_encoding($sanitized, 'UTF-8', 'UTF-8');
        
        return $sanitized ?: '';
    }

    /**
     * Safely prepare HTML content with UTF-8 encoding
     *
     * @param string|null $input
     * @param int $flags
     * @return string
     */
    public static function safeHtmlSpecialChars($input, $flags = ENT_QUOTES)
    {
        $sanitized = self::safeSanitize($input);
        return htmlspecialchars($sanitized, $flags, 'UTF-8');
    }

    /**
     * Safely truncate string while preserving UTF-8 encoding
     *
     * @param string|null $input
     * @param int $length
     * @param string $suffix
     * @return string
     */
    public static function safeTruncate($input, $length = 190, $suffix = '...')
    {
        $sanitized = self::safeSanitize($input);
        
        if (mb_strlen($sanitized, 'UTF-8') <= $length) {
            return $sanitized;
        }
        
        return mb_substr($sanitized, 0, $length, 'UTF-8') . $suffix;
    }

    /**
     * Validate and clean data array for DataTables
     *
     * @param array $data
     * @return array
     */
    public static function cleanDataTableArray($data)
    {
        if (!is_array($data)) {
            return [];
        }

        $cleaned = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $cleaned[$key] = self::safeSanitize($value);
            } elseif (is_array($value)) {
                $cleaned[$key] = self::cleanDataTableArray($value);
            } else {
                $cleaned[$key] = $value;
            }
        }
        
        return $cleaned;
    }
}
