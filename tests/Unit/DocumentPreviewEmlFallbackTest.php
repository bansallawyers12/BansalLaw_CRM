<?php

namespace Tests\Unit;

use App\Http\Controllers\CRM\Clients\ClientDocumentsController;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\TestCase;

class DocumentPreviewEmlFallbackTest extends TestCase
{
    #[Test]
    public function is_eml_or_mime_content_identifies_mime_headers(): void
    {
        $ctrl = app(ClientDocumentsController::class);
        $method = new ReflectionMethod($ctrl, 'isEmlOrMimeContent');
        $method->setAccessible(true);

        $eml1 = "MIME-Version: 1.0\r\nDate: Wed, 26 Nov 2025 14:42:00 +1100\r\nFrom: test@example.com\r\n\r\nHello";
        $eml2 = "From: \"Sender\" <sender@example.com>\r\nSubject: Test\r\n\r\nBody";
        $pdf = "%PDF-1.7\n1 0 obj\n<< /Type /Catalog >>\nendobj";

        $this->assertTrue($method->invoke($ctrl, $eml1));
        $this->assertTrue($method->invoke($ctrl, $eml2));
        $this->assertFalse($method->invoke($ctrl, $pdf));
    }

    #[Test]
    public function convert_html_to_pdf_generates_valid_pdf(): void
    {
        $ctrl = app(ClientDocumentsController::class);
        $method = new ReflectionMethod($ctrl, 'convertHtmlToPdf');
        $method->setAccessible(true);

        $html = '<html><body><h1>Email Preview Test</h1><p>This is a test paragraph.</p></body></html>';
        $pdf = $method->invoke($ctrl, $html, null);

        $this->assertNotNull($pdf);
        $this->assertStringStartsWith('%PDF', $pdf);
    }
}
