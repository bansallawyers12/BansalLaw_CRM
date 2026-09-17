<?php

namespace App\Services;

use Exception;
use ZipArchive;

/**
 * Extracts slide texts and notes from OOXML .pptx files to generate
 * clean, readable slide cards for presentation preview fallback.
 */
class PptxPresentationPreviewService
{
    /**
     * Render a clean HTML deck view of the presentation slides.
     */
    public function convertToHtml(string $fileContent, string $filename = 'presentation.pptx', ?string $downloadUrl = null): ?string
    {
        $slides = $this->extractSlides($fileContent);
        if (empty($slides)) {
            return null;
        }

        $safeName = htmlspecialchars(basename($filename), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeDownloadUrl = $downloadUrl ? htmlspecialchars($downloadUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : null;
        $totalSlides = count($slides);

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8">';
        $html .= '<title>' . $safeName . '</title>';
        $html .= '<meta name="viewport" content="width=device-width, initial-scale=1">';
        $html .= '<style>
            * { box-sizing: border-box; }
            body { margin: 0; padding: 24px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background: #f3f4f6; color: #1f2937; }
            .deck-header { max-width: 900px; margin: 0 auto 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
            .deck-title-wrap { display: flex; align-items: center; gap: 10px; }
            .deck-badge { background: #ea580c; color: #fff; font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 4px; letter-spacing: 0.5px; }
            .deck-title { font-size: 16px; font-weight: 600; color: #111827; }
            .deck-count { font-size: 13px; color: #6b7280; }
            .deck-download-btn { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; background: #ea580c; color: #fff; text-decoration: none; border-radius: 6px; font-size: 13px; font-weight: 500; transition: background 0.2s; }
            .deck-download-btn:hover { background: #c2410c; }
            .deck-container { max-width: 900px; margin: 0 auto; display: flex; flex-direction: column; gap: 24px; }
            .slide-card { background: #ffffff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06); overflow: hidden; border: 1px solid #e5e7eb; }
            .slide-header { background: #fafafa; border-bottom: 1px solid #e5e7eb; padding: 10px 18px; display: flex; justify-content: space-between; align-items: center; }
            .slide-num { font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; }
            .slide-body { padding: 28px 36px; min-height: 220px; display: flex; flex-direction: column; justify-content: center; }
            .slide-title { font-size: 20px; font-weight: 700; color: #111827; margin: 0 0 16px; line-height: 1.3; }
            .slide-paragraphs { display: flex; flex-direction: column; gap: 10px; }
            .slide-p { margin: 0; font-size: 15px; line-height: 1.6; color: #374151; }
            .slide-bullet { display: flex; align-items: baseline; gap: 8px; }
            .slide-bullet::before { content: "•"; color: #ea580c; font-size: 18px; line-height: 1; flex-shrink: 0; }
            .slide-empty { color: #9ca3af; font-style: italic; font-size: 14px; }
            @media (max-width: 640px) {
                body { padding: 12px; }
                .slide-body { padding: 18px 20px; }
                .slide-title { font-size: 17px; }
                .slide-p { font-size: 14px; }
            }
        </style></head><body>';

        $html .= '<div class="deck-header">';
        $html .= '<div class="deck-title-wrap">';
        $html .= '<span class="deck-badge">PRESENTATION</span>';
        $html .= '<span class="deck-title">' . $safeName . '</span>';
        $html .= '<span class="deck-count">(' . $totalSlides . ' ' . ($totalSlides === 1 ? 'Slide' : 'Slides') . ')</span>';
        $html .= '</div>';
        if ($safeDownloadUrl) {
            $html .= '<a href="' . $safeDownloadUrl . '" class="deck-download-btn" target="_blank" rel="noopener">Download File</a>';
        }
        $html .= '</div>';

        $html .= '<div class="deck-container">';
        foreach ($slides as $index => $slide) {
            $slideNum = $index + 1;
            $html .= '<div class="slide-card">';
            $html .= '<div class="slide-header"><span class="slide-num">Slide ' . $slideNum . ' of ' . $totalSlides . '</span></div>';
            $html .= '<div class="slide-body">';

            $title = $slide['title'] ?? null;
            $items = $slide['items'] ?? [];

            if ($title !== null && $title !== '') {
                $html .= '<div class="slide-title">' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</div>';
            }

            if (! empty($items)) {
                $html .= '<div class="slide-paragraphs">';
                foreach ($items as $item) {
                    $escaped = htmlspecialchars($item, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    $html .= '<div class="slide-p slide-bullet">' . $escaped . '</div>';
                }
                $html .= '</div>';
            } elseif ($title === null || $title === '') {
                $html .= '<div class="slide-empty">[Visual or graphic slide]</div>';
            }

            $html .= '</div>'; // .slide-body
            $html .= '</div>'; // .slide-card
        }
        $html .= '</div>'; // .deck-container

        $html .= '</body></html>';

        return $html;
    }

    /**
     * Parse the slides in presentation order and extract slide texts.
     *
     * @return array<int, array{title: ?string, items: array<int, string>}>
     */
    public function extractSlides(string $fileContent): array
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'pptx_');
        if (! $tempFile) {
            return [];
        }

        file_put_contents($tempFile, $fileContent);
        $zip = new ZipArchive();
        if ($zip->open($tempFile) !== true) {
            @unlink($tempFile);

            return [];
        }

        $slides = [];
        try {
            // Find all slide XML files in ppt/slides/
            $slideEntries = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = $stat['name'] ?? '';
                if (preg_match('#^ppt/slides/slide(\d+)\.xml$#i', $name, $matches)) {
                    $slideEntries[(int) $matches[1]] = $name;
                }
            }

            // Sort by slide index (slide1.xml, slide2.xml, ...)
            ksort($slideEntries, SORT_NUMERIC);

            foreach ($slideEntries as $entryName) {
                $xmlContent = $zip->getFromName($entryName);
                if ($xmlContent === false || $xmlContent === '') {
                    continue;
                }

                $parsed = $this->parseSlideXml($xmlContent);
                $slides[] = $parsed;
            }
        } catch (Exception $e) {
            // Return whatever was parsed
        } finally {
            $zip->close();
            @unlink($tempFile);
        }

        return $slides;
    }

    /**
     * Extract title and body paragraphs from slide XML.
     *
     * @return array{title: ?string, items: array<int, string>}
     */
    private function parseSlideXml(string $xmlContent): array
    {
        $title = null;
        $items = [];

        // Suppress libxml errors for malformed XML
        $previousHandling = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($xmlContent);
            if ($xml === false) {
                libxml_clear_errors();
                libxml_use_internal_errors($previousHandling);

                return ['title' => null, 'items' => []];
            }

            // Register OpenXML PresentationML namespaces
            $xml->registerXPathNamespace('p', 'http://schemas.openxmlformats.org/presentationml/2006/main');
            $xml->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');

            // Find all shapes (<p:sp>)
            $shapes = $xml->xpath('//p:sp') ?: [];
            foreach ($shapes as $shape) {
                $shape->registerXPathNamespace('p', 'http://schemas.openxmlformats.org/presentationml/2006/main');
                $shape->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');

                // Determine if this shape is a title placeholder
                $isTitleShape = false;
                $nvPr = $shape->xpath('.//p:nvSpPr/p:nvPr/p:ph');
                if (! empty($nvPr)) {
                    $typeAttr = (string) ($nvPr[0]['type'] ?? '');
                    if (in_array(strtolower($typeAttr), ['title', 'ctrtitle'], true)) {
                        $isTitleShape = true;
                    }
                }

                // Extract paragraphs in this shape (can be p:txBody or a:txBody)
                $paragraphs = $shape->xpath('.//p:txBody//a:p | .//a:txBody//a:p') ?: [];
                foreach ($paragraphs as $para) {
                    $para->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
                    $textRuns = $para->xpath('.//a:r/a:t | .//a:t');
                    $paraText = '';
                    foreach ($textRuns as $t) {
                        $paraText .= (string) $t;
                    }
                    $paraText = trim(preg_replace('/\s+/', ' ', $paraText));
                    if ($paraText === '') {
                        continue;
                    }

                    if ($isTitleShape && $title === null) {
                        $title = $paraText;
                    } else {
                        $items[] = $paraText;
                    }
                }
            }

            // Also check for slide tables (<a:tbl>) or any remaining text not caught in p:sp
            if (empty($items) && $title === null) {
                $allParas = $xml->xpath('//a:p') ?: [];
                foreach ($allParas as $para) {
                    $para->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
                    $textRuns = $para->xpath('.//a:r/a:t | .//a:t');
                    $pText = '';
                    foreach ($textRuns as $t) {
                        $pText .= (string) $t;
                    }
                    $pText = trim(preg_replace('/\s+/', ' ', $pText));
                    if ($pText !== '') {
                        $items[] = $pText;
                    }
                }
            }

            // If no title placeholder shape was explicitly marked, use the first item as title if appropriate
            if ($title === null && ! empty($items)) {
                $candidate = array_shift($items);
                if (mb_strlen($candidate) <= 80) {
                    $title = $candidate;
                } else {
                    array_unshift($items, $candidate);
                }
            }
        } catch (Exception $e) {
            // Keep parsed so far
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousHandling);
        }

        return [
            'title' => $title,
            'items' => $items,
        ];
    }
}
