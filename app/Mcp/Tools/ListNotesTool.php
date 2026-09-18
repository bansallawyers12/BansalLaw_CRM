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

#[Description('List notes on a client or lead file (pinned first). Returns plain-text note bodies, not HTML.')]
class ListNotesTool extends Tool
{
    use HandlesCrmMcpTools;

    public function handle(Request $request, CrmMcpReadService $crm): Response|ResponseFactory
    {
        try {
            $staff = $this->staff($request);
            $payload = $crm->listNotes(
                (int) $request->get('id'),
                $staff,
                (int) $request->get('offset', 0),
                $request->get('limit') !== null ? (int) $request->get('limit') : null,
            );

            return $this->ok(
                $payload,
                'Returned '.count($payload['notes']).' note(s) of '.$payload['total'].'.'
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
            'offset' => $schema->integer()
                ->description('Pagination offset (default 0).')
                ->nullable(),
            'limit' => $schema->integer()
                ->description('Page size (default from server config, max 100).')
                ->nullable(),
        ];
    }
}
