<?php

namespace App\Support;

/**
 * Safe HTML for displaying client note descriptions in the Notes tab.
 * Prevents pasted markup (e.g. stray </div>) from breaking the page layout
 * while preserving common rich text from editors.
 */
class NoteDescriptionHtml
{
    private static ?\HTMLPurifier $purifier = null;

    public static function isOfficeXmlPaste(string $description): bool
    {
        return strpos($description, '<xml>') !== false
            || strpos($description, '<o:OfficeDocumentSettings>') !== false;
    }

    public static function forDisplay(?string $description): string
    {
        if ($description === null || trim($description) === '') {
            return '';
        }

        if (self::isOfficeXmlPaste($description)) {
            return '<p>'.htmlspecialchars($description, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</p>';
        }

        return self::purifier()->purify($description);
    }

    private static function purifier(): \HTMLPurifier
    {
        if (self::$purifier === null) {
            $config = \HTMLPurifier_Config::createDefault();
            // Match typical TinyMCE/Summernote output; balanced tree fixes malformed paste
            $config->set('HTML.Allowed', implode(',', [
                'p[style|class]', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'del', 'ins',
                'ul', 'ol', 'li', 'a[href|title|target|rel|name]',
                'span[style|class]', 'sub', 'sup', 'blockquote[cite]',
                'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
                'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'colgroup', 'col',
                'img[src|alt|width|height|title|class]',
                'div[class|style]', 'hr', 'pre', 'code',
            ]));
            $config->set('URI.AllowedSchemes', [
                'http' => true,
                'https' => true,
                'mailto' => true,
                'tel' => true,
            ]);
            $config->set('Attr.AllowedFrameTargets', ['_blank', '_self']);
            $config->set('HTML.TargetBlank', true);
            $config->set('AutoFormat.RemoveEmpty', false);
            $config->set('AutoFormat.AutoParagraph', false);
            self::$purifier = new \HTMLPurifier($config);
        }

        return self::$purifier;
    }
}
