<?php

namespace Tests\Unit;

use App\Models\Document;
use App\Services\ComposeMatterDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ComposeMatterDocumentServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function list_for_matter_returns_only_uploaded_matter_documents_for_client_matter(): void
    {
        if (! Schema::hasTable('documents')) {
            $this->markTestSkipped('documents table not available');
        }

        $withFile = new Document();
        $withFile->client_id = 10;
        $withFile->client_matter_id = 55;
        $withFile->doc_type = 'matter';
        $withFile->type = 'client';
        $withFile->checklist = 'Passport';
        $withFile->file_name = 'passport';
        $withFile->filetype = 'pdf';
        $withFile->myfile_key = 'passport.pdf';
        $withFile->save();

        $checklistOnly = new Document();
        $checklistOnly->client_id = 10;
        $checklistOnly->client_matter_id = 55;
        $checklistOnly->doc_type = 'matter';
        $checklistOnly->type = 'client';
        $checklistOnly->checklist = 'Empty row';
        $checklistOnly->save();

        $nameOnly = new Document();
        $nameOnly->client_id = 10;
        $nameOnly->client_matter_id = 55;
        $nameOnly->doc_type = 'matter';
        $nameOnly->type = 'client';
        $nameOnly->checklist = 'Name only';
        $nameOnly->file_name = 'not-uploaded.pdf';
        $nameOnly->save();

        $otherMatter = new Document();
        $otherMatter->client_id = 10;
        $otherMatter->client_matter_id = 99;
        $otherMatter->doc_type = 'matter';
        $otherMatter->type = 'client';
        $otherMatter->file_name = 'other';
        $otherMatter->filetype = 'pdf';
        $otherMatter->myfile_key = 'other.pdf';
        $otherMatter->save();

        $service = app(ComposeMatterDocumentService::class);
        $rows = $service->listForMatter(10, 55);

        $this->assertCount(1, $rows);
        $this->assertSame($withFile->id, $rows[0]['id']);
        $this->assertSame('Passport', $rows[0]['checklist']);
        $this->assertStringContainsString('passport.pdf', $rows[0]['file_name']);

        $allForOwner = $service->listForMatter(10, 0);
        $this->assertCount(2, $allForOwner);
    }
}
