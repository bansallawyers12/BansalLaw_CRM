<?php

namespace App\Support;

use App\Models\NoteAttachment;
use Illuminate\Support\Collection;

class NoteAttachmentHtml
{
    /**
     * @param  Collection<int, NoteAttachment>|iterable  $attachments
     */
    public static function forNoteCard($attachments): string
    {
        $items = collect($attachments)->filter();
        if ($items->isEmpty()) {
            return '';
        }

        $html = '<div class="note-attachments-list">';
        foreach ($items as $attachment) {
            if (! $attachment instanceof NoteAttachment) {
                continue;
            }
            $name = htmlspecialchars((string) $attachment->original_name, ENT_QUOTES, 'UTF-8');
            $download = htmlspecialchars(url('/note-attachments/' . $attachment->id . '/download'), ENT_QUOTES, 'UTF-8');
            $preview = htmlspecialchars(url('/note-attachments/' . $attachment->id . '/preview'), ENT_QUOTES, 'UTF-8');
            $size = self::humanSize((int) $attachment->file_size);

            if ($attachment->isImage()) {
                $html .= '<a class="note-attachment-thumb" href="' . $preview . '" target="_blank" rel="noopener noreferrer" title="' . $name . '">';
                $html .= '<img src="' . $preview . '" alt="' . $name . '">';
                $html .= '<span>' . $name . '</span></a>';
            } else {
                $icon = self::iconClass((string) $attachment->extension);
                $html .= '<a class="note-attachment-file" href="' . $download . '" target="_blank" rel="noopener noreferrer">';
                $html .= '<i class="' . $icon . '" aria-hidden="true"></i>';
                $html .= '<span class="note-attachment-name">' . $name . '</span>';
                if ($size !== '') {
                    $html .= '<span class="note-attachment-size">' . htmlspecialchars($size, ENT_QUOTES, 'UTF-8') . '</span>';
                }
                $html .= '</a>';
            }
        }
        $html .= '</div>';

        return $html;
    }

    public static function humanSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '';
        }
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / 1048576, 1) . ' MB';
    }

    public static function iconClass(string $extension): string
    {
        $ext = strtolower($extension);
        if ($ext === 'pdf') {
            return 'fa-solid fa-file-pdf';
        }
        if (in_array($ext, ['doc', 'docx', 'odt', 'rtf'], true)) {
            return 'fa-solid fa-file-word';
        }
        if (in_array($ext, ['xls', 'xlsx', 'csv'], true)) {
            return 'fa-solid fa-file-excel';
        }
        if (in_array($ext, ['ppt', 'pptx'], true)) {
            return 'fa-solid fa-file-powerpoint';
        }
        if ($ext === 'zip') {
            return 'fa-solid fa-file-zipper';
        }

        return 'fa-solid fa-paperclip';
    }
}
