<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ClientLegalForm;
use App\Models\Document;
use App\Models\Signer;
use App\Models\Staff;
use App\Services\CrmDurableStorage;
use App\Services\LegalFormFromDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MoveDocumentToLegalFormTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function suggestion_uses_the_file_name(): void
    {
        $service = app(LegalFormFromDocumentService::class);

        $authority = new Document(['file_name' => 'A2A client', 'filetype' => 'pdf']);
        $short = new Document(['file_name' => 'Short Costs Disclosure', 'filetype' => 'pdf']);
        $ambiguous = new Document(['file_name' => 'Costs Disclosure', 'filetype' => 'pdf']);

        $this->assertSame('authority_to_act', $service->suggestFormType($authority));
        $this->assertSame('short_costs_disclosure', $service->suggestFormType($short));
        $this->assertNull($service->suggestFormType($ambiguous));
    }

    #[Test]
    public function a_document_moves_onto_legal_forms_and_leaves_the_document_list(): void
    {
        $staff = Staff::factory()->superAdmin()->create();
        $client = Admin::factory()->create(['type' => 'client']);
        $relative = 'client-docs/cost-disclosure.pdf';
        app(CrmDurableStorage::class)->putBytes($relative, '%PDF-1.4 cost disclosure');

        $document = Document::factory()->create([
            'client_id' => $client->id,
            'created_by' => $staff->id,
            'file_name' => 'Costs Disclosure',
            'filetype' => 'pdf',
            'myfile' => CrmDurableStorage::localMyfile($relative),
            'myfile_key' => 'Costs_Disclosure.pdf',
            'doc_type' => 'matter',
            'type' => 'client',
            'status' => 'draft',
            'not_used_doc' => null,
        ]);

        $response = $this->actingAs($staff, 'admin')->postJson(route('clients.documents.moveToLegalForm'), [
            'document_id' => $document->id,
            'form_type' => 'cost_agreement',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $document->refresh();
        $this->assertSame(Document::FILED_AS_LEGAL_FORM, (int) $document->not_used_doc);

        $form = ClientLegalForm::query()->where('source_document_id', $document->id)->first();
        $this->assertNotNull($form);
        $this->assertTrue($form->is_uploaded);
        $this->assertSame('cost_agreement', $form->form_type);
        $this->assertSame((int) $client->id, (int) $form->client_id);
        $this->assertSame('0.00', (string) $form->estimated_total);
        $this->assertTrue(app(CrmDurableStorage::class)->exists($form->pdf_path));

        $this->actingAs($staff, 'admin')->postJson(route('clients.documents.moveToLegalForm'), [
            'document_id' => $document->id,
            'form_type' => 'cost_agreement',
        ])->assertStatus(422);

        $this->actingAs($staff, 'admin')
            ->deleteJson(route('legal-forms.destroy', $form))
            ->assertOk();

        $document->refresh();
        $this->assertNull($document->not_used_doc);
    }

    #[Test]
    public function a_document_waiting_for_signature_stays_in_documents(): void
    {
        $staff = Staff::factory()->superAdmin()->create();
        $client = Admin::factory()->create(['type' => 'client']);
        $document = Document::factory()->create([
            'client_id' => $client->id,
            'created_by' => $staff->id,
            'file_name' => 'Authority to Act',
            'filetype' => 'pdf',
            'status' => 'sent',
            'doc_type' => 'agreement',
        ]);
        Signer::factory()->create([
            'document_id' => $document->id,
            'status' => 'pending',
        ]);

        $this->actingAs($staff, 'admin')->postJson(route('clients.documents.moveToLegalForm'), [
            'document_id' => $document->id,
            'form_type' => 'authority_to_act',
        ])->assertStatus(422);

        $this->assertNull(ClientLegalForm::query()->where('source_document_id', $document->id)->first());
        $document->refresh();
        $this->assertNotSame(Document::FILED_AS_LEGAL_FORM, (int) $document->not_used_doc);
    }
}
