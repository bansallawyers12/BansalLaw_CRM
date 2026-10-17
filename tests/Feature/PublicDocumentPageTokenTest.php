<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Document;
use App\Models\Signer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicDocumentPageTokenTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function document_page_requires_signer_token(): void
    {
        $client = Admin::factory()->create(['type' => 'client', 'is_archived' => 0]);

        $document = Document::create([
            'client_id' => $client->id,
            'user_id' => 1,
            'myfile' => 'http://example.test/missing.pdf',
            'doc_type' => 'matter',
            'filetype' => 'pdf',
            'status' => 'sent',
        ]);

        $token = bin2hex(random_bytes(32));
        Signer::create([
            'document_id' => $document->id,
            'name' => 'Client',
            'email' => 'client@example.com',
            'token' => $token,
            'status' => 'pending',
        ]);

        $this->get(route('public.documents.page', ['id' => $document->id, 'page' => 1]))
            ->assertStatus(403);

        $cacheDir = storage_path('app/public/pdf_pages');
        if (! is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        file_put_contents($cacheDir . '/doc_' . $document->id . '_page_1.png', $png);

        $this->get(route('public.documents.page', [
            'id' => $document->id,
            'page' => 1,
            'token' => $token,
        ]))->assertOk();
    }
}
