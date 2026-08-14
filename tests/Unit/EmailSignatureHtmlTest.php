<?php

namespace Tests\Unit;

use App\Support\EmailSignatureHtml;
use PHPUnit\Framework\TestCase;

class EmailSignatureHtmlTest extends TestCase
{
    public function test_it_decodes_escaped_table_source_into_html(): void
    {
        $source = '&lt;table border="0"&gt;&lt;tr&gt;&lt;td&gt;KHUSHI SANGROYA&lt;/td&gt;&lt;/tr&gt;&lt;/table&gt;';

        $this->assertSame(
            '<table border="0"><tr><td>KHUSHI SANGROYA</td></tr></table>',
            EmailSignatureHtml::normalize($source)
        );
    }

    public function test_it_leaves_real_html_unchanged(): void
    {
        $html = '<table border="0"><tr><td>KHUSHI SANGROYA</td></tr></table>';

        $this->assertSame($html, EmailSignatureHtml::normalize($html));
    }

    public function test_it_returns_empty_values_unchanged(): void
    {
        $this->assertNull(EmailSignatureHtml::normalize(null));
        $this->assertSame('', EmailSignatureHtml::normalize(''));
    }
}
