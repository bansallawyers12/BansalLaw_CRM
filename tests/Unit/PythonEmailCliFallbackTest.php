<?php

namespace Tests\Unit;

use App\Services\PythonEmailCliFallback;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PythonEmailCliFallbackTest extends TestCase
{
    #[Test]
    public function cli_fallback_availability_check_returns_boolean(): void
    {
        $fallback = new PythonEmailCliFallback();
        $this->assertIsBool($fallback->isAvailable());
    }

    #[Test]
    public function cli_fallback_parses_sample_eml_if_available(): void
    {
        $fallback = new PythonEmailCliFallback();
        if (! $fallback->isAvailable()) {
            $this->markTestSkipped('Python or cli_email.py not available in this test environment.');
        }

        $testEml = base_path('python_services/test.eml');
        if (! file_exists($testEml)) {
            $this->markTestSkipped('test.eml not found');
        }

        $file = new UploadedFile(
            $testEml,
            'test.eml',
            'message/rfc822',
            null,
            true
        );

        $result = $fallback->parse($file, 'parse', true);

        $this->assertIsArray($result);
        $this->assertTrue($result['success'] ?? false);
        $this->assertSame('Test EML with attachment', $result['subject'] ?? null);
    }
}
