<?php

namespace App\Support;

class EmailSignatureHtml
{
    /**
     * Decode signature HTML that was stored as visible source (e.g. &lt;table&gt;...).
     * Leaves already-rendered HTML unchanged.
     */
    public static function normalize(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        $value = $html;
        for ($i = 0; $i < 3; $i++) {
            if (! preg_match('/&lt;\s*(?:table|div|p|html|body|span|img|font|!DOCTYPE|br|strong|b|em|i|a)\b/i', $value)) {
                break;
            }

            $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $value) {
                break;
            }
            $value = $decoded;
        }

        return $value;
    }
}
