<?php

namespace Tests\Unit;

use App\Support\OfficeDocumentFormat;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class OfficeDocumentFormatDocxTest extends TestCase
{
    public function test_validates_minimal_phpword_docx(): void
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'crm-docx-test-' . uniqid('', true) . '.docx';
        $phpWord = new PhpWord;
        $phpWord->addSection()->addText('Test');
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);

        $this->assertTrue(OfficeDocumentFormat::isValidDocxFile($path));
        @unlink($path);
    }

    public function test_rejects_plain_text_as_docx(): void
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'crm-bad-docx-' . uniqid('', true) . '.docx';
        file_put_contents($path, 'not a zip');
        $this->assertFalse(OfficeDocumentFormat::isValidDocxFile($path));
        @unlink($path);
    }
}
