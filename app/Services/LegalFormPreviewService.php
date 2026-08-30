<?php

namespace App\Services;

use App\Models\ClientLegalForm;
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
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (! in_array($extension, ['doc', 'docx', 'rtf', 'odt'], true)) {
            return null;
        }

        $safeFilename = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($filename)) ?: ('document.'.$extension);
        $tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'crm_legal_form_html_'.uniqid('', true);

        if (! @mkdir($tempDir, 0755, true) && ! is_dir($tempDir)) {
            return null;
        }

        $inputPath = $tempDir.DIRECTORY_SEPARATOR.$safeFilename;
        file_put_contents($inputPath, $fileContent);

        try {
            $phpWord = IOFactory::load($inputPath);
            $writer = IOFactory::createWriter($phpWord, 'HTML');
            ob_start();
            $writer->save('php://output');
            $body = ob_get_clean();

            if ($body === false || trim($body) === '') {
                return null;
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
            Log::warning('Legal form PhpWord HTML preview failed', [
                'file' => $filename,
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
