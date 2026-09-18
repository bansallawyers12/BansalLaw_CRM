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

#[Description('Create a short-lived download URL for an existing document by documents.id. Does not upload or modify files.')]
class DownloadDocumentTool extends Tool
{
    use HandlesCrmMcpTools;

    public function handle(Request $request, CrmMcpReadService $crm): Response|ResponseFactory
    {
        try {
            $staff = $this->staff($request);
            $payload = $crm->downloadDocument((int) $request->get('document_id'), $staff);

            return $this->ok(
                $payload,
                'Download URL for '.$payload['filename'].' (expires in '.$payload['expires_in_minutes'].' minutes).'
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
            'document_id' => $schema->integer()
                ->description('documents.id from list_documents.')
                ->required(),
        ];
    }
}
