<?php

namespace Tests\Unit;

use App\Support\NoteDescriptionEmbeddedImages;
use Tests\TestCase;

class NoteDescriptionEmbeddedImagesTest extends TestCase
{
    public function test_counts_data_uri_images_in_html(): void
    {
        $png = base64_encode(hex2bin('89504e470d0a1a0a'));
        $html = '<p>Hello</p><img src="data:image/png;base64,' . $png . '" alt="x">';

        $this->assertSame(1, NoteDescriptionEmbeddedImages::countDataUriImages($html));
    }

    public function test_counts_large_data_uri_without_regex_backtrack_failure(): void
    {
        $png = base64_encode(str_repeat('x', 200000));
        $html = '<p>this</p><img src="data:image/png;base64,' . $png . '">';

        $this->assertSame(1, NoteDescriptionEmbeddedImages::countDataUriImages($html));
    }

    public function test_validate_rejects_when_over_file_limit(): void
    {
        $png = base64_encode(hex2bin('89504e470d0a1a0a'));
        $html = '';
        for ($i = 0; $i < 11; $i++) {
            $html .= '<img src="data:image/png;base64,' . $png . '">';
        }

        $error = NoteDescriptionEmbeddedImages::validateEmbeddedImages($html, 0, 0);

        $this->assertNotNull($error);
        $this->assertStringContainsString('maximum', strtolower((string) $error));
    }
}
