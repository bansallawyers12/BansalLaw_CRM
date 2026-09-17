<?php

namespace App\Services;

use App\Models\ClientLegalForm;
use App\Support\OfficeDocumentFormat;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\IOFactory;

class LegalFormPreviewService
{
    public function __construct(
        private LegalFormDocxService $docxService,
    ) {
    }

    /**
     * Reuse an existing generated DOCX when it is still newer than the form row.
     */
    public function ensureGeneratedDocx(ClientLegalForm $legalForm): string
    {
        $updatedAt = $legalForm->updated_at?->getTimestamp() ?? 0;
        if ($legalForm->pdf_path) {
            $fullPath = public_path($legalForm->pdf_path);
            if (is_file($fullPath) && (@filemtime($fullPath) ?: 0) >= $updatedAt) {
                return $legalForm->pdf_path;
            }
        }

        $docxPath = $this->docxService->generate($legalForm);

        // Avoid bumping updated_at — otherwise the file always looks stale vs the row.
        $legalForm->timestamps = false;
        $legalForm->pdf_path = $docxPath;
        $legalForm->save();
        $legalForm->timestamps = true;

        $this->forgetHtmlCache((int) $legalForm->id);

        return $docxPath;
    }

    public function htmlPreviewFromPath(string $fullPath, string $filename, ?ClientLegalForm $legalForm = null): ?string
    {
        if (! is_file($fullPath)) {
            return null;
        }

        $cachePath = $legalForm ? $this->htmlCachePath($legalForm, $fullPath) : null;
        if ($cachePath && is_file($cachePath)) {
            $cached = file_get_contents($cachePath);
            if (is_string($cached) && trim($cached) !== '') {
                return $cached;
            }
        }

        $fileContent = file_get_contents($fullPath);
        if (! is_string($fileContent) || $fileContent === '') {
            return null;
        }

        $html = $this->convertDocxBytesToHtml($fileContent, $filename);
        if ($html !== null && $cachePath) {
            $dir = dirname($cachePath);
            if (! is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            @file_put_contents($cachePath, $html);
        }

        return $html;
    }

    public function forgetHtmlCache(int $formId): void
    {
        $dir = $this->cacheDirectory();
        if (! is_dir($dir)) {
            return;
        }

        foreach (glob($dir.DIRECTORY_SEPARATOR.$formId.'_*.html') ?: [] as $file) {
            @unlink($file);
        }
    }

    public function convertDocxBytesToHtml(string $fileContent, string $filename): ?string
    {
        // Prefer LibreOffice→PDF for Word-accurate legal form preview when available.
        $pdf = app(OfficeToPdfConverterService::class)->convertToPdf($fileContent, $filename);
        if (is_string($pdf) && $pdf !== '') {
            $b64 = base64_encode($pdf);
            $title = htmlspecialchars(basename($filename), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>'.$title.'</title>'
                .'<style>html,body{margin:0;height:100%;background:#525659;}embed{border:0;width:100%;height:100%;}</style>'
                .'</head><body>'
                .'<embed type="application/pdf" src="data:application/pdf;base64,'.$b64.'">'
                .'</body></html>';
        }

        $extension = OfficeDocumentFormat::sniffWordExtension($filename, $fileContent);
        if (! in_array($extension, ['doc', 'docx', 'rtf', 'odt'], true)) {
            return null;
        }

        if (OfficeDocumentFormat::isLegacyBinaryDoc($extension, $fileContent)) {
            $legacyHtml = (new LegacyDocHtmlPreviewService())->convertToHtml($fileContent, $filename);
            if ($legacyHtml !== null) {
                return $legacyHtml;
            }

            Log::warning('Legal form legacy .doc HTML preview returned empty; falling back to PhpWord MsDoc', [
                'file' => $filename,
                'extension' => $extension,
            ]);
        }

        $safeFilename = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($filename)) ?: ('document.'.$extension);
        if (! str_contains($safeFilename, '.')) {
            $safeFilename .= '.'.$extension;
        }
        $tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'crm_legal_form_html_'.uniqid('', true);

        if (! @mkdir($tempDir, 0755, true) && ! is_dir($tempDir)) {
            return null;
        }

        $inputPath = $tempDir.DIRECTORY_SEPARATOR.$safeFilename;
        file_put_contents($inputPath, $fileContent);

        try {
            // IOFactory::load() defaults to Word2007 (ZIP/DOCX). Legacy .doc needs MsDoc.
            $readerName = OfficeDocumentFormat::phpWordReaderName($extension, $fileContent);
            $phpWord = IOFactory::load($inputPath, $readerName);
            $writer = IOFactory::createWriter($phpWord, 'HTML');
            ob_start();
            $writer->save('php://output');
            $body = ob_get_clean();

            if ($body === false || trim($body) === '') {
                return null;
            }

            if ($readerName === 'MsDoc') {
                $body = $this->normalizeMsDocHtmlEncoding($body);
            }

            $styles = '<style>body{font-family:Segoe UI,Calibri,Arial,sans-serif;font-size:14px;line-height:1.55;color:#1f2937;margin:0;padding:28px 32px;background:#fff;}'
                .'table{border-collapse:collapse;width:100%;margin:12px 0;} td,th{border:1px solid #d1d5db;padding:6px 10px;vertical-align:top;}'
                .'p{margin:0.5em 0;} h1,h2,h3{color:#1a3a5c;margin:0.75em 0 0.35em;}</style>';

            if (stripos($body, '<html') !== false) {
                if (stripos($body, '</head>') !== false) {
                    return (string) preg_replace('/<\/head>/i', $styles.'</head>', $body, 1);
                }

                return $styles.$body;
            }

            return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Form preview</title>'
                .$styles.'</head><body>'
                .$body
                .'</body></html>';
        } catch (\Throwable $e) {
            if (OfficeDocumentFormat::looksLikeZipArchiveError($e->getMessage())) {
                $legacyHtml = (new LegacyDocHtmlPreviewService())->convertToHtml($fileContent, $filename);
                if ($legacyHtml !== null) {
                    Log::warning('Recovered legal form preview via LegacyDoc after ZipArchive failure', [
                        'file' => $filename,
                        'error' => $e->getMessage(),
                    ]);

                    return $legacyHtml;
                }
            }

            Log::warning('Legal form PhpWord HTML preview failed', [
                'file' => $filename,
                'reader' => OfficeDocumentFormat::phpWordReaderName($extension, $fileContent),
                'error' => $e->getMessage(),
            ]);

            return null;
        } finally {
            foreach (glob($tempDir.DIRECTORY_SEPARATOR.'*') ?: [] as $tempFile) {
                @unlink($tempFile);
            }
            @rmdir($tempDir);
        }
    }

    /**
     * Repair MsDoc HTML where each character packs two ASCII bytes (UTF-16LE units as codepoints).
     */
    private function normalizeMsDocHtmlEncoding(string $html): string
    {
        return (string) preg_replace_callback(
            '/>([^<]+)</u',
            function (array $matches): string {
                $text = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (! $this->msDocTextLooksPacked($text)) {
                    return '>' . $matches[1] . '<';
                }

                $fixed = $this->unpackMsDocPackedText($text);

                return '>' . htmlspecialchars($fixed, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '<';
            },
            $html
        );
    }

    private function msDocTextLooksPacked(string $text): bool
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $packed = 0;
        $significant = 0;

        foreach ($chars as $ch) {
            $cp = mb_ord($ch, 'UTF-8');
            if ($cp === false || $cp <= 0x20) {
                continue;
            }
            $significant++;
            $lo = $cp & 0xFF;
            $hi = ($cp >> 8) & 0xFF;
            if ($hi >= 0x20 && $hi <= 0x7E && $lo >= 0x09 && $lo <= 0x7E) {
                $packed++;
            }
        }

        return $significant > 0 && ($packed / $significant) >= 0.5;
    }

    private function unpackMsDocPackedText(string $text): string
    {
        $out = '';
        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
            $cp = mb_ord($ch, 'UTF-8');
            if ($cp === false) {
                continue;
            }
            if ($cp <= 0xFF) {
                $out .= chr($cp);
                continue;
            }
            $out .= chr($cp & 0xFF);
            $hi = ($cp >> 8) & 0xFF;
            if ($hi !== 0) {
                $out .= chr($hi);
            }
        }

        $out = str_replace(["\x07", "\x0B", "\x0C"], ' ', $out);
        $out = preg_replace("/\r\n?|\n/", "\n", $out) ?? $out;

        return trim($out);
    }

    private function htmlCachePath(ClientLegalForm $legalForm, string $fullPath): string
    {
        $stamp = $legalForm->updated_at?->getTimestamp() ?? 0;
        $mtime = @filemtime($fullPath) ?: 0;

        return $this->cacheDirectory().DIRECTORY_SEPARATOR.$legalForm->id.'_'.$stamp.'_'.$mtime.'.html';
    }

    private function cacheDirectory(): string
    {
        return storage_path('app/legal_form_previews');
    }
}
