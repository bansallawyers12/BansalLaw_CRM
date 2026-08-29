<?php

namespace App\Services\CommunicationCheck;

/**
 * Shared phone normalisation for SMS / call matching (AU-friendly).
 */
class PhoneNormalizer
{
    /**
     * Digits only, for loose comparison.
     */
    public static function digits(?string $phone): string
    {
        if ($phone === null || trim($phone) === '') {
            return '';
        }

        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    /**
     * Last N national digits (default 9 for AU mobile/landline without country code).
     */
    public static function lastDigits(?string $phone, int $n = 9): string
    {
        $digits = self::digits($phone);
        if ($digits === '') {
            return '';
        }

        return strlen($digits) >= $n ? substr($digits, -$n) : $digits;
    }

    /**
     * True when two phone strings share the same significant national digits.
     */
    public static function matches(?string $a, ?string $b): bool
    {
        $la = self::lastDigits($a);
        $lb = self::lastDigits($b);
        if ($la === '' || $lb === '') {
            return false;
        }

        return $la === $lb;
    }

    /**
     * Extract a phone-like token from free text (from/to/phone fields).
     */
    public static function extractFromText(?string $text): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        if (preg_match('/(?:\+?\d[\d\s\-().]{6,}\d)/', $text, $m)) {
            $candidate = trim($m[0]);
            if (self::lastDigits($candidate) !== '') {
                return $candidate;
            }
        }

        $digits = self::digits($text);
        if (strlen($digits) >= 8) {
            return $text;
        }

        return null;
    }
}
