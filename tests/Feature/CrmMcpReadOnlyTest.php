<?php

namespace Tests\Feature;

use App\Mcp\Servers\BansalCrmServer;
use App\Mcp\Tools\DownloadDocumentTool;
use App\Mcp\Tools\GetRecordTool;
use App\Mcp\Tools\SearchClientsLeadsTool;
use App\Models\Admin;
use App\Models\Company;
use App\Models\Document;
use App\Models\Staff;
use App\Services\CrmMcp\CrmMcpDocumentDownloadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CrmMcpReadOnlyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function mcp_endpoint_requires_authentication(): void
    {
        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => new \stdClass,
                'clientInfo' => ['name' => 'test', 'version' => '1.0.0'],
            ],
        ]);

        $response->assertUnauthorized();
    }

    #[Test]
    public function search_tool_returns_matching_client_for_staff(): void
    {
        $staff = Staff::factory()->superAdmin()->create();
        $match = Admin::factory()->create([
            'first_name' => 'McpSearch',
            'last_name' => 'Client',
            'email' => 'mcp.search@example.com',
            'client_id' => 'MCPS2600001',
            'type' => 'client',
            'is_archived' => 0,
        ]);
        Admin::factory()->create([
            'first_name' => 'Other',
            'last_name' => 'Person',
            'email' => 'other@example.com',
            'client_id' => 'OTHR2600002',
            'type' => 'client',
            'is_archived' => 0,
        ]);

        $response = BansalCrmServer::actingAs($staff, 'admin')
            ->tool(SearchClientsLeadsTool::class, [
                'query' => 'McpSearch',
            ]);

        $response
            ->assertOk()
            ->assertStructuredContent(fn ($json) => $json
                ->where('count', 1)
                ->has('results', 1)
                ->where('results.0.id', $match->id)
                ->where('results.0.first_name', 'McpSearch')
            );
    }

    #[Test]
    public function search_by_non_numeric_name_does_not_match_zero_id(): void
    {
        $staff = Staff::factory()->superAdmin()->create();

        Admin::factory()->create([
            'first_name' => 'Zero',
            'last_name' => 'Id',
            'email' => 'zero@example.com',
            'client_id' => 'ZERO2600000',
            'type' => 'client',
            'is_archived' => 0,
        ]);

        $response = BansalCrmServer::actingAs($staff, 'admin')
            ->tool(SearchClientsLeadsTool::class, [
                'query' => 'DefinitelyNoMatchNameXYZ',
            ]);

        $response
            ->assertOk()
            ->assertStructuredContent(fn ($json) => $json->where('count', 0)->has('results', 0));
    }

    #[Test]
    public function search_finds_company_clients_by_company_name(): void
    {
        $staff = Staff::factory()->superAdmin()->create();
        $client = Admin::factory()->create([
            'first_name' => 'Co',
            'last_name' => 'Contact',
            'email' => 'co.contact@example.com',
            'client_id' => 'COMP2600001',
            'type' => 'client',
            'is_company' => 1,
            'is_archived' => 0,
        ]);
        Company::query()->create([
            'admin_id' => $client->id,
            'company_name' => 'Acme Migration Holdings Pty Ltd',
        ]);

        $response = BansalCrmServer::actingAs($staff, 'admin')
            ->tool(SearchClientsLeadsTool::class, [
                'query' => 'Acme Migration',
            ]);

        $response
            ->assertOk()
            ->assertStructuredContent(fn ($json) => $json
                ->where('count', 1)
                ->where('results.0.id', $client->id)
            );
    }

    #[Test]
    public function get_record_denies_inaccessible_client_for_restricted_staff(): void
    {
        $owner = Staff::factory()->create(['role' => 2]);
        $other = Staff::factory()->create(['role' => 2]);

        $client = Admin::factory()->create([
            'first_name' => 'Locked',
            'last_name' => 'File',
            'email' => 'locked.file@example.com',
            'client_id' => 'LOCK2600001',
            'type' => 'client',
            'user_id' => $owner->id,
            'is_archived' => 0,
        ]);

        config([
            'crm_access.allocation_enabled' => true,
            'crm_access.strict_allocation' => true,
            'crm_access.exempt_role_ids' => [1, 17],
            'crm_access.exempt_staff_ids' => [],
        ]);

        $response = BansalCrmServer::actingAs($other, 'admin')
            ->tool(GetRecordTool::class, [
                'id' => $client->id,
            ]);

        $response->assertHasErrors();
    }

    #[Test]
    public function download_rejects_document_owned_only_by_inaccessible_lead(): void
    {
        $owner = Staff::factory()->create(['role' => 2]);
        $other = Staff::factory()->create(['role' => 2]);

        $lead = Admin::factory()->create([
            'first_name' => 'Lead',
            'last_name' => 'Only',
            'email' => 'lead.only@example.com',
            'client_id' => 'LEAD2600001',
            'type' => 'lead',
            'user_id' => $owner->id,
            'is_archived' => 0,
        ]);

        $document = Document::factory()->create([
            'client_id' => null,
            'lead_id' => $lead->id,
            'file_name' => 'passport',
            'filetype' => 'pdf',
            'myfile' => 'LEAD2600001/personal/passport.pdf',
            'myfile_key' => 'passport.pdf',
            'doc_type' => 'personal',
            'type' => 'lead',
        ]);

        config([
            'crm_access.allocation_enabled' => true,
            'crm_access.strict_allocation' => true,
            'crm_access.exempt_role_ids' => [1, 17],
            'crm_access.exempt_staff_ids' => [],
        ]);

        $response = BansalCrmServer::actingAs($other, 'admin')
            ->tool(DownloadDocumentTool::class, [
                'document_id' => $document->id,
            ]);

        $response->assertHasErrors();
    }

    #[Test]
    public function document_download_service_resolves_owner_from_lead_id(): void
    {
        $staff = Staff::factory()->superAdmin()->create();
        $lead = Admin::factory()->create([
            'first_name' => 'Lead',
            'last_name' => 'Doc',
            'email' => 'lead.doc@example.com',
            'client_id' => 'LEADD260002',
            'type' => 'lead',
            'is_archived' => 0,
        ]);

        $document = Document::factory()->create([
            'client_id' => null,
            'lead_id' => $lead->id,
            'file_name' => 'visa',
            'filetype' => 'pdf',
            'myfile' => null,
            'myfile_key' => 'visa.pdf',
            'doc_type' => 'personal',
            'type' => 'lead',
            'folder_name' => 'Passport',
        ]);

        try {
            $result = app(CrmMcpDocumentDownloadService::class)->temporaryDownload($document, $staff);
            $this->assertSame((int) $document->id, $result['document_id']);
            $this->assertNotEmpty($result['url']);
        } catch (\App\Services\CrmMcp\CrmMcpAccessException $e) {
            // Access must succeed; missing object / non-S3 disk are acceptable here.
            $this->assertNotSame(403, $e->status, $e->getMessage());
            $this->assertContains($e->status, [404, 503]);
        }
    }

    #[Test]
    public function sanctum_token_can_reach_mcp_initialize(): void
    {
        $staff = Staff::factory()->superAdmin()->create();
        Sanctum::actingAs($staff, ['*']);

        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-03-26',
                'capabilities' => new \stdClass,
                'clientInfo' => ['name' => 'test', 'version' => '1.0.0'],
            ],
        ], [
            'Accept' => 'application/json, text/event-stream',
        ]);

        $response->assertSuccessful();
    }
}
