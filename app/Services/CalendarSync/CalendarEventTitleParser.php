<?php

namespace App\Services\CalendarSync;

/**
 * Parse CRM-style titles produced for Zoho/Outlook: [FileNo / MatterRef] Title
 */
class CalendarEventTitleParser
{
    /**
     * @return array{file_ref: string|null, matter_ref: string|null, title: string}
     */
    public static function parse(?string $rawTitle): array
    {
        $title = trim((string) $rawTitle);
        if ($title === '') {
            return ['file_ref' => null, 'matter_ref' => null, 'title' => ''];
        }

        if (preg_match('/^\[([^\]]+)\]\s*(.*)$/u', $title, $m)) {
            $inner = trim($m[1]);
            $rest = trim($m[2]);
            $parts = array_map('trim', explode('/', $inner, 2));
            $file = $parts[0] !== '' ? $parts[0] : null;
            $matter = isset($parts[1]) && $parts[1] !== '' ? $parts[1] : null;

            return [
                'file_ref' => $file,
                'matter_ref' => $matter,
                'title' => $rest !== '' ? $rest : $title,
            ];
        }

        return ['file_ref' => null, 'matter_ref' => null, 'title' => $title];
    }
}
