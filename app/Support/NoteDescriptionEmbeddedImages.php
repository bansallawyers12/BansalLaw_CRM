<?php

namespace App\Support;

use App\Models\Note;
use App\Services\NoteAttachmentService;

class NoteDescriptionEmbeddedImages
{
    /**
     * @return list<array{start: int, end: int, subtype: string, base64: string}>
     */
  public static function findDataUriImages(string $html): array
    {
        if ($html === '') {
            return [];
        }

        $found = [];
        $searchFrom = 0;
        $len = strlen($html);

        while ($searchFrom < $len) {
            $imgStart = stripos($html, '<img', $searchFrom);
            if ($imgStart === false) {
                break;
            }

            $tagClose = strpos($html, '>', $imgStart);
            if ($tagClose === false) {
                break;
            }

            $tagHead = substr($html, $imgStart, min(256, $tagClose - $imgStart + 1));
            if (! preg_match('/\bsrc\s*=\s*(["\'])data:image\/([^;"\']+);base64,/i', $tagHead, $headMatch, PREG_OFFSET_CAPTURE)) {
                $searchFrom = $tagClose + 1;

                continue;
            }

            $quote = $headMatch[1][0];
            $subtype = strtolower((string) $headMatch[2][0]);
            $base64Marker = ';base64,';
            $payloadStart = stripos($html, $base64Marker, $imgStart);
            if ($payloadStart === false || $payloadStart > $tagClose) {
                $searchFrom = $tagClose + 1;

                continue;
            }
            $payloadStart += strlen($base64Marker);

            $payloadEnd = strpos($html, $quote, $payloadStart);
            if ($payloadEnd === false || $payloadEnd > $tagClose) {
                $searchFrom = $tagClose + 1;

                continue;
            }

            $base64 = substr($html, $payloadStart, $payloadEnd - $payloadStart);
            $found[] = [
                'start' => $imgStart,
                'end' => $tagClose + 1,
                'subtype' => $subtype,
                'base64' => $base64,
            ];

            $searchFrom = $tagClose + 1;
        }

        return $found;
    }

    public static function countDataUriImages(?string $html): int
    {
        if ($html === null || trim($html) === '') {
            return 0;
        }

        return count(self::findDataUriImages($html));
    }

    /**
     * @return string|null Error message, or null when valid.
     */
    public static function validateEmbeddedImages(?string $html, int $existingAttachments, int $uploadedFiles): ?string
    {
        if ($html === null || $html === '') {
            return null;
        }

        $images = self::findDataUriImages($html);
        if ($images === []) {
            return null;
        }

        $total = $existingAttachments + $uploadedFiles + count($images);
        if ($total > NoteAttachmentService::MAX_FILES) {
            return 'You can attach a maximum of ' . NoteAttachmentService::MAX_FILES . ' files per note (including pasted images).';
        }

        foreach ($images as $index => $image) {
            $label = $index + 1;
            $subtype = (string) ($image['subtype'] ?? '');
            if ($subtype === '' || ! self::isAllowedImageSubtype($subtype)) {
                return 'Pasted image #' . $label . ' is not a supported image type.';
            }

            $payload = preg_replace('/\s+/', '', (string) ($image['base64'] ?? ''));
            if ($payload === '') {
                return 'Pasted image #' . $label . ' could not be read. Try using the attachment area instead.';
            }

            $approxBytes = (int) floor(strlen($payload) * 3 / 4);
            if ($approxBytes > NoteAttachmentService::MAX_BYTES) {
                return 'Pasted image #' . $label . ' is larger than 20 MB. Attach it using the file picker instead.';
            }

            $bytes = base64_decode($payload, true);
            if ($bytes === false) {
                return 'Pasted image #' . $label . ' could not be read. Try using the attachment area instead.';
            }

            if (strlen($bytes) > NoteAttachmentService::MAX_BYTES) {
                return 'Pasted image #' . $label . ' is larger than 20 MB. Attach it using the file picker instead.';
            }
        }

        return null;
    }

    /**
     * Move data-URI images from note HTML into stored attachments; returns cleaned HTML.
     *
     * @return array{description: string, matter_document_refresh: ?array}
     */
    public static function promoteToAttachments(Note $note, string $description): array
    {
        $images = self::findDataUriImages($description);
        if ($images === []) {
            return [
                'description' => $description,
                'matter_document_refresh' => null,
            ];
        }

        $matterRefresh = null;
        $index = 0;

        // Remove from end to start so byte offsets stay valid.
        for ($i = count($images) - 1; $i >= 0; $i--) {
            $image = $images[$i];
            $subtype = strtolower((string) ($image['subtype'] ?? ''));
            $payload = preg_replace('/\s+/', '', (string) ($image['base64'] ?? ''));

            $replacement = '';
            if ($payload !== '' && self::isAllowedImageSubtype($subtype)) {
                $bytes = base64_decode($payload, true);
                $size = is_string($bytes) ? strlen($bytes) : 0;
                if ($bytes !== false && $size > 0 && $size <= NoteAttachmentService::MAX_BYTES) {
                    $index++;
                    $ext = self::extensionForSubtype($subtype);
                    $originalName = 'Pasted image ' . $index . '.' . $ext;
                    $mimeType = 'image/' . ($subtype === 'jpg' ? 'jpeg' : $subtype);

                    $sync = NoteAttachmentService::storeBytesForNote($note, $bytes, $originalName, $mimeType, $ext);
                    if ($sync) {
                        $matterRefresh = $sync;
                    }
                }
            }

            $description = substr($description, 0, (int) $image['start'])
                . $replacement
                . substr($description, (int) $image['end']);
        }

        $description = self::tidyAfterImageRemoval($description);

        return [
            'description' => $description,
            'matter_document_refresh' => $matterRefresh,
        ];
    }

    /**
     * Repair notes that still contain huge inline data-URI images (legacy saves).
     */
    public static function repairNoteIfNeeded(Note $note): bool
    {
        $description = (string) ($note->description ?? '');
        if ($description === '' || self::countDataUriImages($description) === 0) {
            return false;
        }

        $promoted = self::promoteToAttachments($note, $description);
        if ($promoted['description'] === $description) {
            return false;
        }

        $note->description = $promoted['description'];
        $note->save();

        return true;
    }

    private static function isAllowedImageSubtype(string $subtype): bool
    {
        $subtype = strtolower($subtype);

        return in_array($subtype, ['jpeg', 'jpg', 'png', 'gif', 'webp', 'bmp'], true);
    }

    private static function extensionForSubtype(string $subtype): string
    {
        $subtype = strtolower($subtype);

        return $subtype === 'jpeg' ? 'jpg' : $subtype;
    }

    private static function tidyAfterImageRemoval(string $html): string
    {
        $html = preg_replace('/<p>\s*(<br\s*\/?>\s*)*\s*<\/p>/i', '', $html) ?? $html;
        $html = preg_replace('/\s{2,}/', ' ', $html) ?? $html;

        return trim($html);
    }
}
