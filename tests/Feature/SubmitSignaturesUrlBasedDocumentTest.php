<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Document;
use App\Models\Signer;
use App\Services\PythonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubmitSignaturesUrlBasedDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        \Illuminate\Support\Facades\DB::table('user_roles')->insertOrIgnore([
            ['id' => 1, 'name' => 'Admin', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    #[Test]
    public function submit_signatures_supports_url_based_document_without_s3_key()
    {
        Storage::fake('public');

        // Create dummy PDF in storage/app/public/test_doc.pdf
        $pdfContent = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Count 1/Kids[3 0 R]>>endobj 3 0 obj<</Type/Page/MediaBox[0 0 612 792]/Parent 2 0 R>>endobj\nxref\n0 4\n0000000000 65535 f\n0000000009 00000 n\n0000000052 00000 n\n0000000118 00000 n\ntrailer<</Size 4/Root 1 0 R>>\nstartxref\n201\n%%EOF";
        Storage::disk('public')->put('documents/test_url_doc.pdf', $pdfContent);

        $client = Admin::factory()->create(['type' => 'client', 'is_archived' => 0]);

        // Document with HTTP URL-based myfile, NO doc_type and NO myfile_key (so S3 key cannot be constructed)
        $document = Document::create([
            'client_id' => $client->id,
            'user_id' => 1,
            'title' => 'URL Document',
            'myfile' => 'http://127.0.0.1:8000/storage/documents/test_url_doc.pdf',
            'doc_type' => null,
            'myfile_key' => null,
            'status' => 'pending',
        ]);

        $token = bin2hex(random_bytes(32));
        $signer = Signer::create([
            'document_id' => $document->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'token' => $token,
            'status' => 'pending',
        ]);

        // Mock Python service to return mock signed PDF
        $mockPython = $this->createMock(PythonService::class);
        $mockPython->method('isHealthy')->willReturn(true);
        $mockPython->method('addSignaturesToPdf')->willReturnCallback(function ($pdfPath, $outputPath, $sigs) use ($pdfContent) {
            file_put_contents($outputPath, $pdfContent);
            return true;
        });
        $this->app->instance(PythonService::class, $mockPython);

        // Create valid >100 byte PNG
        ob_start();
        $img = imagecreatetruecolor(50, 50);
        imagepng($img);
        $rawPng = ob_get_clean();
        imagedestroy($img);
        $dummySigPng = 'data:image/png;base64,' . base64_encode($rawPng);

        $response = $this->post(route('public.documents.submitSignatures', ['document' => $document->id]), [
            'signer_id' => $signer->id,
            'token' => $token,
            'signatures' => [
                '1' => json_encode(['1' => $dummySigPng]),
            ],
            'signature_positions' => [
                '1' => json_encode([
                    '1' => [
                        'x_percent' => 10,
                        'y_percent' => 10,
                        'w_percent' => 20,
                        'h_percent' => 10,
                    ],
                ]),
            ],
        ]);

        $response->assertStatus(302);

        $signer->refresh();
        $this->assertEquals('signed', $signer->status, 'Session data: ' . json_encode(session()->all()));

        $document->refresh();
        $this->assertEquals('signed', $document->status);
        $this->assertNotEmpty($document->signed_doc_link);
    }
}
