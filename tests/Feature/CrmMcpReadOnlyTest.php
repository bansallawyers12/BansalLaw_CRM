<?php

namespace Tests\Feature;

use App\Mcp\Servers\BansalCrmServer;
use App\Mcp\Tools\GetRecordTool;
use App\Mcp\Tools\SearchClientsLeadsTool;
use App\Models\Admin;
use App\Models\Staff;
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
