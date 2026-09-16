<?php

namespace App\Services;

use PhpOffice\PhpWord\Shared\OLERead;
use Throwable;

/**
 * Build a readable HTML preview for legacy binary .doc files.
 *
 * PhpWord's MsDoc reader mangles character runs; this uses the Word piece table
 * (CLX / PlcPcd) from the OLE streams instead.
 */
class LegacyDocHtmlPreviewService
{
    public function convertToHtml(string $fileContent, string $filename = 'document.doc'): ?string
    {
        $text = $this->extractText($fileContent);
        if ($text === null || trim($text) === '') {
            return null;
        }

        $paragraphs = $this->textToParagraphs($text);
        if ($paragraphs === []) {
            return null;
        }

        $body = '';
        foreach ($paragraphs as $paragraph) {
            $body .= '<p>'.htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')."</p>\n";
        }

        $title = htmlspecialchars(basename($filename), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $styles = '<style>'
            .'body{font-family:Segoe UI,Calibri,Arial,sans-serif;font-size:14px;line-height:1.55;color:#222;margin:0;padding:24px 28px;background:#fff;}'
            .'p{margin:0 0 0.75em;white-space:pre-wrap;word-wrap:break-word;}'
            .'</style>';

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>'.$title.'</title>'
            .$styles.'</head><body>'.$body.'</body></html>';
    }

    public function extractText(string $fileContent): ?string
    {
        if ($fileContent === '' || ! str_starts_with($fileContent, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1")) {
            return null;
        }

        $tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'crm_legacy_doc_'.uniqid('', true);
        if (! @mkdir($tempDir, 0755, true) && ! is_dir($tempDir)) {
            return null;
        }

        $inputPath = $tempDir.DIRECTORY_SEPARATOR.'document.doc';
        file_put_contents($inputPath, $fileContent);

        try {
            $ole = new OLERead();
            $ole->read($inputPath);

            if ($ole->wrkdocument === null) {
                return null;
            }

            $word = $ole->getStream($ole->wrkdocument);
            if (! is_string($word) || strlen($word) < 0x1AA) {
                return null;
            }

            if ($this->uint16($word, 0) !== 0xA5EC) {
                return null;
            }

            $tableIndex = $this->resolveTableStreamIndex($ole, $word);
            if ($tableIndex === null) {
                return null;
            }

            $table = $ole->getStream($tableIndex);
            if (! is_string($table) || $table === '') {
                return null;
            }

            $ccpText = $this->uint32($word, 0x4C);
            $fcClx = $this->uint32($word, 0x1A2);
            $lcbClx = $this->uint32($word, 0x1A6);
            if ($lcbClx === 0 || $fcClx + $lcbClx > strlen($table)) {
                return null;
            }

            $clx = substr($table, $fcClx, $lcbClx);
            $pieces = $this->parsePieceTable($clx);
            if ($pieces === []) {
                return null;
            }

            $parts = [];
            $length = 0;
            foreach ($pieces as [$cpStart, $cpEnd, $fc, $compressed]) {
                $n = $cpEnd - $cpStart;
                if ($n <= 0) {
                    continue;
                }

                if ($compressed) {
                    $offset = intdiv($fc, 2);
                    $raw = substr($word, $offset, $n);
                    $part = $raw === false ? '' : (mb_convert_encoding($raw, 'UTF-8', 'Windows-1252') ?: $raw);
                } else {
                    $raw = substr($word, $fc, $n * 2);
                    $part = $raw === false ? '' : (mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE') ?: '');
                }

                $parts[] = $part;
                $length += mb_strlen($part, 'UTF-8');
                if ($ccpText > 0 && $length >= $ccpText) {
                    break;
                }
            }

            $text = implode('', $parts);
            if ($ccpText > 0) {
                $text = mb_substr($text, 0, $ccpText, 'UTF-8');
            }

            return $this->cleanWordText($text);
        } catch (Throwable) {
            return null;
        } finally {
            foreach (glob($tempDir.DIRECTORY_SEPARATOR.'*') ?: [] as $tempFile) {
                @unlink($tempFile);
            }
            @rmdir($tempDir);
        }
    }

    /**
     * @return list<string>
     */
    public function textToParagraphs(string $text): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = preg_split("/\n+/", $normalized) ?: [];
        $paragraphs = [];

        foreach ($lines as $line) {
            $line = trim(preg_replace('/[ \t\x0B\xA0]+/u', ' ', $line) ?? $line);
            if ($line !== '') {
                $paragraphs[] = $line;
            }
        }

        return $paragraphs;
    }

    private function cleanWordText(string $text): string
    {
        $text = $this->stripWordFields($text);

        // Cell marks / specials → paragraph breaks; soft breaks → newline.
        $text = str_replace(
            ["\x07", "\x0B", "\x0C", "\x01", "\x02", "\x03", "\x04", "\x05", "\x08"],
            ["\n", "\n", "\n", '', '', '', '', '', ''],
            $text
        );

        // Drop residual field / ASCII control chars except TAB/LF/CR.
        $text = preg_replace('/[\x00-\x08\x0E-\x1F]/', '', $text) ?? $text;

        return $text;
    }

    private function stripWordFields(string $text): string
    {
        $out = '';
        $length = mb_strlen($text, 'UTF-8');
        $i = 0;

        while ($i < $length) {
            $ch = mb_substr($text, $i, 1, 'UTF-8');
            if ($ch === "\x13") {
                $sep = null;
                $end = null;
                for ($j = $i + 1; $j < $length; $j++) {
                    $c = mb_substr($text, $j, 1, 'UTF-8');
                    if ($c === "\x14" && $sep === null) {
                        $sep = $j;
                    }
                    if ($c === "\x15") {
                        $end = $j;
                        break;
                    }
                }

                if ($end === null) {
                    $out .= $ch;
                    $i++;
                    continue;
                }

                if ($sep !== null && $sep < $end) {
                    $out .= mb_substr($text, $sep + 1, $end - $sep - 1, 'UTF-8');
                }
                $i = $end + 1;
                continue;
            }

            if ($ch === "\x14" || $ch === "\x15") {
                $i++;
                continue;
            }

            $out .= $ch;
            $i++;
        }

        return $out;
    }

    /**
     * @return list<array{0:int,1:int,2:int,3:bool}>
     */
    private function parsePieceTable(string $clx): array
    {
        $pieces = [];
        $i = 0;
        $len = strlen($clx);

        while ($i < $len) {
            $clxt = ord($clx[$i]);
            $i++;

            if ($clxt === 0x01) {
                if ($i + 2 > $len) {
                    break;
                }
                $cb = $this->uint16($clx, $i);
                $i += 2 + $cb;
                continue;
            }

            if ($clxt !== 0x02) {
                break;
            }

            if ($i + 4 > $len) {
                break;
            }

            $lcb = $this->uint32($clx, $i);
            $i += 4;
            if ($lcb < 4 || $i + $lcb > $len) {
                break;
            }

            $plc = substr($clx, $i, $lcb);
            $i += $lcb;
            $n = intdiv($lcb - 4, 12);
            if ($n <= 0) {
                break;
            }

            $cps = [];
            for ($j = 0; $j <= $n; $j++) {
                $cps[] = $this->uint32($plc, $j * 4);
            }

            $pcdBase = ($n + 1) * 4;
            for ($j = 0; $j < $n; $j++) {
                $fcRaw = $this->uint32($plc, $pcdBase + $j * 8 + 2);
                $compressed = ($fcRaw & 0x40000000) !== 0;
                $fc = $fcRaw & 0x3FFFFFFF;
                $pieces[] = [$cps[$j], $cps[$j + 1], $fc, $compressed];
            }
        }

        return $pieces;
    }

    private function resolveTableStreamIndex(OLERead $ole, string $word): ?int
    {
        $flags = $this->uint16($word, 0x0A);
        $want1Table = ($flags & 0x0200) !== 0;

        if ($want1Table && $ole->wrk1Table !== null) {
            return (int) $ole->wrk1Table;
        }

        $wanted = $want1Table ? '1TABLE' : '0TABLE';
        foreach ($ole->props as $index => $prop) {
            $name = strtoupper((string) ($prop['name'] ?? ''));
            if ($name === $wanted) {
                return (int) $index;
            }
        }

        if ($ole->wrk1Table !== null) {
            return (int) $ole->wrk1Table;
        }

        foreach ($ole->props as $index => $prop) {
            $name = strtoupper((string) ($prop['name'] ?? ''));
            if ($name === '0TABLE' || $name === '1TABLE') {
                return (int) $index;
            }
        }

        return null;
    }

    private function uint16(string $data, int $offset): int
    {
        $unpacked = unpack('v', substr($data, $offset, 2));

        return (int) ($unpacked[1] ?? 0);
    }

    private function uint32(string $data, int $offset): int
    {
        $unpacked = unpack('V', substr($data, $offset, 4));

        return (int) ($unpacked[1] ?? 0);
    }
}
