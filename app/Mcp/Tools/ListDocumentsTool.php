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

#[Description('List documents on a client or lead file. Filter by folder_name, doc_type (personal/matter/visa/…), or client_matter_id. Read-only — no upload.')]
class ListDocumentsTool extends Tool
{
    use HandlesCrmMcpTools;

    public function handle(Request $request, CrmMcpReadService $crm): Response|ResponseFactory
    {
        try {
            $staff = $this->staff($request);
            $matterId = $request->get('client_matter_id');
            $payload = $crm->listDocuments(
                (int) $request->get('id'),
                $staff,
                $request->get('folder_name'),
                $request->get('doc_type'),
                $matterId !== null && $matterId !== '' ? (int) $matterId : null,
                $request->get('limit') !== null ? (int) $request->get('limit') : null,
            );

            return $this->ok(
                $payload,
                'Returned '.count($payload['documents']).' document(s) of '.$payload['total'].'.'
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
            'id' => $schema->integer()
                ->description('CRM client/lead record id (admins.id).')
                ->required(),
            'folder_name' => $schema->string()
                ->description('Optional folder / checklist folder name.')
                ->nullable(),
            'doc_type' => $schema->string()
                ->description('Optional doc_type filter (personal, matter, visa, migration, …).')
                ->nullable(),
            'client_matter_id' => $schema->integer()
                ->description('Optional matter id filter.')
                ->nullable(),
            'limit' => $schema->integer()
                ->description('Max rows to return (default from server config).')
                ->nullable(),
        ];
    }
}
