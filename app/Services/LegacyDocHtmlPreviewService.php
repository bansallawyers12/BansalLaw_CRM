<?php

namespace App\Services;

use PhpOffice\PhpWord\Shared\OLERead;
use Throwable;

/**
 * Build a Word-like HTML preview for legacy binary .doc files.
 *
 * Uses the Word piece table (CLX / PlcPcd) and preserves cell marks / tabs so
 * party tables and two-column filing details render closer to the original layout.
 */
class LegacyDocHtmlPreviewService
{
    public function convertToHtml(string $fileContent, string $filename = 'document.doc'): ?string
    {
        $text = $this->extractText($fileContent);
        if ($text === null || trim($text) === '') {
            return null;
        }

        $body = $this->renderStructuredHtml($text);
        if (trim(strip_tags($body)) === '') {
            return null;
        }

        $title = htmlspecialchars(basename($filename), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $styles = '<style>'
            .'html,body{margin:0;padding:0;background:#e8e8e8;}'
            .'body{font-family:"Times New Roman",Times,serif;font-size:12pt;line-height:1.35;color:#000;}'
            .'.doc-page{max-width:816px;margin:16px auto;padding:72px 72px 80px;background:#fff;'
            .'box-shadow:0 1px 4px rgba(0,0,0,.18);min-height:100vh;box-sizing:border-box;}'
            .'p{margin:0 0 10px;}'
            .'p.doc-blank{margin:0 0 8px;min-height:0.6em;}'
            .'p.doc-center{text-align:center;}'
            .'p.doc-title{text-align:center;font-weight:700;margin:14px 0 12px;}'
            .'p.doc-heading{font-weight:700;margin:14px 0 8px;}'
            .'p.doc-section{text-align:center;font-weight:700;margin:18px 0 14px;}'
            .'p.doc-num,p.doc-letter{margin:0 0 12px;}'
            .'p.doc-num{padding-left:36px;text-indent:-36px;}'
            .'p.doc-letter{padding-left:72px;text-indent:-28px;}'
            .'span.doc-mark{display:inline-block;min-width:28px;}'
            .'hr.doc-rule{border:0;border-top:1px solid #000;margin:10px 0 14px;}'
            .'table.doc-table{width:100%;border-collapse:collapse;margin:4px 0 12px;}'
            .'table.doc-table td{vertical-align:top;padding:2px 0;font-size:12pt;}'
            .'table.doc-party td:first-child{width:62%;padding-right:12px;}'
            .'table.doc-party td:last-child{width:38%;text-align:right;white-space:nowrap;}'
            .'table.doc-meta td:first-child{width:58%;padding-right:16px;}'
            .'table.doc-meta td:last-child{width:42%;}'
            .'a{color:#0563c1;}'
            .'@media print{html,body{background:#fff}.doc-page{margin:0;box-shadow:none;max-width:none}}'
            .'</style>';

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>'.$title.'</title>'
            .$styles.'</head><body><div class="doc-page">'.$body.'</div></body></html>';
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
     * @deprecated kept for callers that only need flat paragraphs
     * @return list<string>
     */
    public function textToParagraphs(string $text): array
    {
        $blocks = $this->splitIntoBlocks($text);
        $out = [];
        foreach ($blocks as $block) {
            if ($block['type'] === 'p' || $block['type'] === 'title' || $block['type'] === 'heading') {
                $out[] = $block['text'];
            }
        }

        return $out;
    }

    private function renderStructuredHtml(string $text): string
    {
        $html = '';
        foreach ($this->applyAffidavitNumbering($this->splitIntoBlocks($text)) as $block) {
            $html .= match ($block['type']) {
                'party_table' => $this->renderPartyTable($block['rows']),
                'meta_table' => $this->renderMetaTable($block['rows']),
                'hr' => '<hr class="doc-rule">'."\n",
                'title' => '<p class="doc-title">'.$this->escapeWithBreaks($block['text']).'</p>'."\n",
                'section' => '<p class="doc-section">'.$this->escapeWithBreaks($block['text']).'</p>'."\n",
                'heading' => '<p class="doc-heading">'.$this->escapeWithBreaks($block['text']).'</p>'."\n",
                'center' => '<p class="doc-center">'.$this->escapeWithBreaks($block['text']).'</p>'."\n",
                'num' => '<p class="doc-num"><span class="doc-mark">'.(int) $block['n'].'.</span> '
                    .$this->escapeWithBreaks($block['text']).'</p>'."\n",
                'letter' => '<p class="doc-letter"><span class="doc-mark">'.$block['n'].'.</span> '
                    .$this->escapeWithBreaks($block['text']).'</p>'."\n",
                'blank' => '<p class="doc-blank">&nbsp;</p>'."\n",
                default => '<p>'.$this->escapeWithBreaks($block['text']).'</p>'."\n",
            };
        }

        return $html;
    }

    /**
     * Word stores affidavit paragraph numbers as list formatting, not text.
     * Rebuild 1/2/3 + a/b/c numbering with hanging indents after the oath line.
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array<string, mixed>>
     */
    private function applyAffidavitNumbering(array $blocks): array
    {
        $out = [];
        $inBody = false;
        $number = 0;
        $letterIndex = 0;
        $inLetterList = false;

        foreach ($blocks as $block) {
            $type = (string) ($block['type'] ?? 'p');
            $text = trim((string) ($block['text'] ?? ''));

            if ($type === 'heading' && $this->looksLikeCenteredSectionHeading($text)) {
                $block['type'] = 'section';
                $inLetterList = false;
                $out[] = $block;
                continue;
            }

            if ($type === 'heading' && $this->looksLikeEndOfAffidavitBody($text)) {
                $inBody = false;
                $inLetterList = false;
                $out[] = $block;
                continue;
            }

            if (in_array($type, ['party_table', 'meta_table', 'hr', 'title', 'heading', 'section', 'center'], true)) {
                $inLetterList = false;
                $out[] = $block;
                continue;
            }

            if ($type === 'blank') {
                $out[] = $block;
                continue;
            }

            if ($type !== 'p') {
                $out[] = $block;
                continue;
            }

            if (! $inBody && preg_match('/make oath and say\s*:?\s*$/iu', $text) === 1) {
                $inBody = true;
                $out[] = $block;
                continue;
            }

            if (! $inBody) {
                $out[] = $block;
                continue;
            }

            if ($this->looksLikeSignatureBlock($text)) {
                $inBody = false;
                $inLetterList = false;
                $out[] = $block;
                continue;
            }

            // Strip numbers already present in the source text.
            $text = preg_replace('/^\(?[0-9]+\)?[\.\)]\s+/u', '', $text) ?? $text;
            $text = preg_replace('/^\(?[a-z]\)?[\.\)]\s+/iu', '', $text) ?? $text;
            $block['text'] = $text;

            if ($inLetterList) {
                $letterIndex++;
                $mark = $letterIndex <= 26 ? chr(ord('a') + $letterIndex - 1) : (string) $letterIndex;
                $out[] = [
                    'type' => 'letter',
                    'n' => $mark,
                    'text' => $text,
                ];
                continue;
            }

            $number++;
            $out[] = [
                'type' => 'num',
                'n' => $number,
                'text' => $text,
            ];

            if (preg_match('/\bas follows\s*:?\s*$/iu', $text) === 1) {
                $inLetterList = true;
                $letterIndex = 0;
            }
        }

        return $out;
    }

    private function looksLikeCenteredSectionHeading(string $text): bool
    {
        if (mb_strlen($text, 'UTF-8') > 80) {
            return false;
        }

        return preg_match('/^(DISPUTED|BACKGROUND|CHRONOLOGY|RELIEF|ORDERS SOUGHT|PARTICULARS|GROUNDS|CONCLUSION)\b/iu', $text) === 1
            || preg_match('/\bIN TOTALITY\b/u', $text) === 1;
    }

    private function looksLikeEndOfAffidavitBody(string $text): bool
    {
        return preg_match('/^(FORM\b|CERTIFICATE\b|SCHEDULE\b|ANNEXURE\b|EXHIBIT\b)/iu', $text) === 1;
    }

    private function looksLikeSignatureBlock(string $text): bool
    {
        return preg_match('/^(Sworn|Affirmed|Before me)\b/iu', $text) === 1
            || preg_match('/^\[(signature|place|date)\b/iu', $text) === 1
            || preg_match('/^The contents of this affidavit\b/iu', $text) === 1;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function splitIntoBlocks(string $text): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = explode("\n", $normalized);
        $blocks = [];

        foreach ($lines as $line) {
            // Soft line breaks inside a Word paragraph.
            $line = str_replace("\x0B", "\n", $line);

            if (str_contains($line, "\x07")) {
                $rows = $this->cellsToPartyRows($line);
                if ($rows !== []) {
                    $blocks[] = ['type' => 'party_table', 'rows' => $rows];
                    continue;
                }
                $line = str_replace("\x07", ' ', $line);
            }

            $trimmed = trim(str_replace("\xA0", ' ', $line));
            $trimmed = preg_replace('/[ \t]+/u', ' ', $trimmed) ?? $trimmed;

            if ($trimmed === '') {
                $blocks[] = ['type' => 'blank', 'text' => ''];
                continue;
            }

            if (preg_match('/^_{8,}$/u', $trimmed) === 1) {
                $blocks[] = ['type' => 'hr'];
                continue;
            }

            $metaRow = $this->parseMetaColumns($line);
            if ($metaRow !== null) {
                // Merge consecutive meta rows into one table block.
                $last = $blocks === [] ? null : $blocks[array_key_last($blocks)];
                if (is_array($last) && ($last['type'] ?? null) === 'meta_table') {
                    $blocks[array_key_last($blocks)]['rows'][] = $metaRow;
                } else {
                    $blocks[] = ['type' => 'meta_table', 'rows' => [$metaRow]];
                }
                continue;
            }

            // Email line that sits under the filing-details column in Word.
            if (preg_match('/^Email:\s*(.+)$/iu', $trimmed, $emailMatch) === 1) {
                $lastIdx = $blocks === [] ? null : array_key_last($blocks);
                if ($lastIdx !== null && ($blocks[$lastIdx]['type'] ?? null) === 'meta_table') {
                    $blocks[$lastIdx]['rows'][] = ['', 'Email: '.trim($emailMatch[1])];
                    continue;
                }
            }

            if ($this->looksLikeCenteredTitle($trimmed)) {
                $blocks[] = ['type' => 'title', 'text' => $trimmed];
                continue;
            }

            if ($this->looksLikeSectionHeading($trimmed)) {
                $blocks[] = ['type' => 'heading', 'text' => $trimmed];
                continue;
            }

            $blocks[] = ['type' => 'p', 'text' => trim($line)];
        }

        return $this->collapseExtraBlanks($blocks);
    }

    /**
     * @return list<array{0:string,1:string}>
     */
    private function cellsToPartyRows(string $line): array
    {
        $cells = array_map(
            static fn (string $cell): string => trim(str_replace(["\x0B", "\xA0"], ["\n", ' '], $cell)),
            explode("\x07", $line)
        );

        $nonEmpty = array_values(array_filter($cells, static fn (string $c): bool => $c !== ''));
        if (count($nonEmpty) < 2) {
            return [];
        }

        // Typical court party block: Name | Role | and | Name | Role
        $rolePattern = '/\b(Plaintiffs?|Defendants?|Applicants?|Respondents?|The Company|First and Second|Plaintiff|Defendant)\b/i';
        $hasRole = false;
        foreach ($nonEmpty as $cell) {
            if (preg_match($rolePattern, $cell) === 1) {
                $hasRole = true;
                break;
            }
        }
        if (! $hasRole && count($nonEmpty) < 3) {
            return [];
        }

        $rows = [];
        $i = 0;
        $count = count($nonEmpty);
        while ($i < $count) {
            $left = $nonEmpty[$i];
            $right = '';
            if ($i + 1 < $count && preg_match($rolePattern, $nonEmpty[$i + 1]) === 1) {
                $right = $nonEmpty[$i + 1];
                $i += 2;
            } else {
                $i++;
            }
            $rows[] = [$left, $right];
        }

        return $rows;
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private function parseMetaColumns(string $line): ?array
    {
        if (! str_contains($line, "\t")) {
            return null;
        }

        $parts = preg_split('/\t+/', $line) ?: [];
        $parts = array_values(array_filter(array_map(
            static fn (string $p): string => trim(str_replace(["\x0B", "\xA0"], [' ', ' '], $p)),
            $parts
        ), static fn (string $p): bool => $p !== ''));

        if (count($parts) < 2) {
            return null;
        }

        $left = $parts[0];
        $right = $parts[count($parts) - 1];
        if ($left === $right && count($parts) === 2) {
            return null;
        }

        // Filing-detail style rows.
        $metaHint = '/^(Date of Document|Filed on behalf|Prepared by|Solicitors? Code|Telephone|Ref:|Email:)/i';
        if (preg_match($metaHint, $left) !== 1 && preg_match($metaHint, $right) !== 1) {
            return null;
        }

        return [$left, $right];
    }

    private function looksLikeCenteredTitle(string $text): bool
    {
        if (mb_strlen($text, 'UTF-8') > 80) {
            return false;
        }

        return preg_match('/^(AFFIDAVIT|STATEMENT|OUTLINE|SUBMISSIONS|ORDERS?|NOTICE|SUMMONS)\b/i', $text) === 1
            || preg_match('/^AFFIDAVIT OF\b/i', $text) === 1;
    }

    private function looksLikeSectionHeading(string $text): bool
    {
        if (mb_strlen($text, 'UTF-8') > 60) {
            return false;
        }

        // Court caption lines stay normal left-aligned paragraphs.
        if (preg_match('/\b(COURT|LIST|BETWEEN|ECI|VIC|PTY LTD)\b/u', $text) === 1) {
            return false;
        }

        return preg_match('/^[A-Z0-9][A-Z0-9 \/\-]{2,}$/u', $text) === 1
            && preg_match('/[A-Z]{3,}/u', $text) === 1
            && ! preg_match('/\.$/u', $text);
    }

    /**
     * @param list<array{0:string,1:string}> $rows
     */
    private function renderPartyTable(array $rows): string
    {
        $html = '<table class="doc-table doc-party" role="presentation">';
        foreach ($rows as [$left, $right]) {
            $html .= '<tr><td>'.$this->escapeWithBreaks($left).'</td><td>'
                .$this->escapeWithBreaks($right).'</td></tr>';
        }

        return $html.'</table>'."\n";
    }

    /**
     * @param list<array{0:string,1:string}> $rows
     */
    private function renderMetaTable(array $rows): string
    {
        $html = '<table class="doc-table doc-meta" role="presentation">';
        foreach ($rows as [$left, $right]) {
            $html .= '<tr><td>'.$this->escapeWithBreaks($left).'</td><td>'
                .$this->escapeWithBreaks($right).'</td></tr>';
        }

        return $html.'</table>'."\n";
    }

    private function escapeWithBreaks(string $text): string
    {
        $text = str_replace(["\x0B", "\r\n", "\r"], "\n", $text);
        $parts = explode("\n", $text);
        $escaped = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (preg_match('/^Email:\s*(\S+@\S+)$/iu', $part, $m) === 1) {
                $addr = htmlspecialchars($m[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $escaped[] = 'Email: <a href="mailto:'.$addr.'">'.$addr.'</a>';
                continue;
            }
            $escaped[] = htmlspecialchars($part, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        return implode('<br>', $escaped);
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @return list<array<string, mixed>>
     */
    private function collapseExtraBlanks(array $blocks): array
    {
        $out = [];
        $blankRun = 0;
        foreach ($blocks as $block) {
            if (($block['type'] ?? '') === 'blank') {
                $blankRun++;
                if ($blankRun > 1) {
                    continue;
                }
            } else {
                $blankRun = 0;
            }
            $out[] = $block;
        }

        return $out;
    }

    private function cleanWordText(string $text): string
    {
        $text = $this->stripWordFields($text);

        // Keep \t (columns), \x07 (cells), \x0B (soft breaks), \r (paragraphs).
        $text = str_replace(
            ["\x0C", "\x01", "\x02", "\x03", "\x04", "\x05", "\x08"],
            ["\n", '', '', '', '', '', ''],
            $text
        );

        // Drop other controls except TAB, LF, CR, VT, BEL(cell).
        $text = preg_replace('/[\x00-\x06\x0E-\x1F]/', '', $text) ?? $text;

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
