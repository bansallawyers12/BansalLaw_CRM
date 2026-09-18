<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\HandlesCrmMcpTools;
use App\Services\CrmMcp\CrmMcpAccessException;
use App\Services\CrmMcp\CrmMcpReadService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Search clients and leads by name, email, phone, CRM reference, or numeric id. Returns a short list of matching files the authenticated staff may see.')]
class SearchClientsLeadsTool extends Tool
{
    use HandlesCrmMcpTools;

    public function handle(Request $request, CrmMcpReadService $crm): Response|ResponseFactory
    {
        try {
            $staff = $this->staff($request);
            $results = $crm->search(
                (string) $request->get('query', ''),
                $request->get('type'),
                $staff
            );

            return $this->ok(
                ['results' => $results, 'count' => count($results)],
                'Found '.count($results).' matching client/lead record(s).'
            );
        } catch (CrmMcpAccessException $e) {
            return $this->fail($e);
        }
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Name, email, phone, CRM reference (e.g. SHAL2500295), or numeric id.')
                ->required(),
            'type' => $schema->string()
                ->description('Optional filter: client, lead, or omit for both.')
                ->enum(['client', 'lead'])
                ->nullable(),
        ];
    }
}
