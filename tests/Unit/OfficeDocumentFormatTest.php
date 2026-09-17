<?php

namespace Tests\Unit;

use App\Support\OfficeDocumentFormat;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OfficeDocumentFormatTest extends TestCase
{
    private const OLE = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";

    #[Test]
    public function sniff_word_extension_prefers_ole_magic_when_extension_missing(): void
    {
        $this->assertSame('doc', OfficeDocumentFormat::sniffWordExtension('affidavit', self::OLE.'rest'));
        $this->assertSame('doc', OfficeDocumentFormat::sniffWordExtension('file.msword', self::OLE.'rest'));
        $this->assertSame('doc', OfficeDocumentFormat::sniffWordExtension('file.docx', self::OLE.'rest'));
    }

    #[Test]
    public function sniff_word_extension_maps_zip_bytes_without_extension_to_docx(): void
    {
        $this->assertSame('docx', OfficeDocumentFormat::sniffWordExtension('file', 'PK'."\x03\x04".'rest'));
        $this->assertSame('docx', OfficeDocumentFormat::sniffWordExtension('file.doc', 'PK'."\x03\x04".'rest'));
    }

    #[Test]
    public function phpword_reader_never_selects_word2007_for_ole_bytes(): void
    {
        $this->assertSame('MsDoc', OfficeDocumentFormat::phpWordReaderName('docx', self::OLE.'rest'));
        $this->assertSame('MsDoc', OfficeDocumentFormat::phpWordReaderName('', self::OLE.'rest'));
        $this->assertSame('MsDoc', OfficeDocumentFormat::phpWordReaderName('doc', self::OLE.'rest'));
    }

    #[Test]
    public function phpword_reader_uses_word2007_only_for_zip_bytes(): void
    {
        $this->assertSame('Word2007', OfficeDocumentFormat::phpWordReaderName('docx', 'PK'."\x03\x04"));
        $this->assertSame('ODText', OfficeDocumentFormat::phpWordReaderName('odt', 'PK'."\x03\x04"));
        // Non-ZIP default must not be Word2007 (DOC-BP-01 ZipArchive error 19).
        $this->assertSame('MsDoc', OfficeDocumentFormat::phpWordReaderName('docx', 'not-a-zip'));
    }

    #[Test]
    public function legacy_binary_doc_detection(): void
    {
        $this->assertTrue(OfficeDocumentFormat::isLegacyBinaryDoc('doc', self::OLE.'x'));
        $this->assertTrue(OfficeDocumentFormat::isLegacyBinaryDoc('docx', self::OLE.'x'));
        $this->assertFalse(OfficeDocumentFormat::isLegacyBinaryDoc('doc', 'PK'."\x03\x04"));
        $this->assertTrue(OfficeDocumentFormat::looksLikeZipArchiveError('The archive failed to load with the following error code: 19'));
    }
}
