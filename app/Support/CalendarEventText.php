<?php

namespace App\Support;

/**
 * Shared sanitizers for email→calendar merge and calendar display titles.
 */
class CalendarEventText
{
    public static function sanitizeLocation(?string $location): ?string
    {
        if ($location === null) {
            return null;
        }

        $location = trim(html_entity_decode(strip_tags($location)));
        if ($location === '') {
            return null;
        }

        if (self::looksLikeEmailOrUrlNoise($location) || self::looksLikeBodySnippet($location)) {
            return null;
        }

        // Truncate at sentence fragments that are clearly body text.
        if (preg_match('/^(.{3,80}?)(?:\s+(?:and you may|for your|please|attached|invoice)\b.*)?$/iu', $location, $m)) {
            $location = trim($m[1]);
        }

        $location = mb_substr($location, 0, 120);
        $location = trim($location, " \t\n\r\0\x0B,;.");

        if ($location === '' || self::looksLikeEmailOrUrlNoise($location) || self::looksLikeBodySnippet($location) || mb_strlen($location) < 3) {
            return null;
        }

        // Reject if mostly punctuation / digits / email-like.
        $letters = preg_replace('/[^a-zA-Z]/', '', $location) ?? '';
        if (mb_strlen($letters) < 3) {
            return null;
        }

        return $location;
    }

    public static function sanitizeHearingType(?string $type): string
    {
        $type = trim((string) $type);
        if ($type === '') {
            return 'Hearing';
        }

        if (self::looksLikeEmailOrUrlNoise($type) || self::looksLikeBodySnippet($type)) {
            return 'Hearing';
        }

        $normalized = strtolower($type);
        $allowed = [
            'hearing' => 'Hearing',
            'court' => 'Court hearing',
            'court hearing' => 'Court hearing',
            'directions hearing' => 'Directions hearing',
            'case management' => 'Case management',
            'mention' => 'Mention',
            'tribunal' => 'Tribunal',
            'listing' => 'Listing',
            'mediation' => 'Mediation',
            'other' => 'Hearing',
        ];

        if (isset($allowed[$normalized])) {
            return $allowed[$normalized];
        }

        // Keep short clean labels only.
        if (mb_strlen($type) <= 40 && ! preg_match('/[@:]/', $type) && ! self::looksLikeBodySnippet($type)) {
            return mb_substr($type, 0, 40);
        }

        return 'Hearing';
    }

    public static function looksLikeEmailOrUrlNoise(string $text): bool
    {
        return (bool) preg_match(
            '/mailto:|https?:\/\/|www\.|[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i',
            $text
        );
    }

    public static function looksLikeBodySnippet(string $text): bool
    {
        $normalized = strtolower($text);

        if (preg_match('/\b(attached|attachment|invoice|for your reference|please find|kind regards|best regards|you may proceed|at this stage|this stage)\b/i', $normalized)) {
            return true;
        }

        // Long free-text lines are almost never a venue or hearing type.
        if (mb_strlen($text) > 80 && str_word_count($text) > 8) {
            return true;
        }

        return false;
    }

    public static function looksLikeJunkEventTitle(string $title): bool
    {
        $title = trim($title);
        if ($title === '' || self::looksLikeEmailOrUrlNoise($title) || self::looksLikeBodySnippet($title)) {
            return true;
        }

        return (bool) preg_match(
            '/\b(our ref|your ref|court file(?:\s+number)?|invoice|requires more documents)\b/i',
            $title
        );
    }

    public static function displayStaffTitle(string $title, string $eventType): string
    {
        $fallback = ucfirst(str_replace('_', ' ', $eventType)) ?: 'Event';
        $title = trim($title);
        $title = preg_replace('/^(?:re|fw|fwd)\s*:\s*/i', '', $title) ?? $title;
        $title = trim($title);

        if ($title === '' || self::looksLikeJunkEventTitle($title)) {
            return $fallback;
        }

        return mb_substr($title, 0, 255);
    }

    public static function isNoiseScheduleLine(string $line): bool
    {
        $line = trim($line);
        if ($line === '') {
            return true;
        }

        if (preg_match('/^>+/', $line)) {
            return true;
        }

        if (preg_match('/^on\s+.+\swrote:\s*$/i', $line)) {
            return true;
        }

        if (preg_match('/^(from|to|cc|bcc|subject|sent|date):\s*/i', $line)) {
            return true;
        }

        if (self::looksLikeEmailOrUrlNoise($line)) {
            return true;
        }

        if (self::looksLikeBodySnippet($line) && ! preg_match('/\b(directions hearing|court hearing|listed (?:for|at)|hearing (?:at|on|date))\b/i', $line)) {
            return true;
        }

        return false;
    }

    /**
     * Prefer court / venue phrases; ignore bare "at email" / "@ someone".
     */
    public static function extractLocationCandidate(string $line): ?string
    {
        if (preg_match('/\b(?:venue|location|court)\s*:\s*(.{3,120})/i', $line, $match)) {
            return self::sanitizeLocation($match[1]);
        }

        if (preg_match(
            '/\b(?:at|@)\s+((?:Federal\s+Circuit(?:\s+and\s+Family\s+Court)?|Federal\s+Court|Family\s+Court|Magistrates(?:\'|\s)?\s*Court|County\s+Court|Supreme\s+Court|NCAT|AAT|VICAT|Tribunal|Court\s+House|[A-Z][A-Za-z]*(?:\s+[A-Z][A-Za-z]*){0,4}\s+Court)[^.,;\n]{0,80})/i',
            $line,
            $match
        )) {
            return self::sanitizeLocation($match[1]);
        }

        return null;
    }
}
