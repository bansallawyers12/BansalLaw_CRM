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

#[Description('List timeline / activity-log entries for a client or lead file.')]
class ListActivitiesTool extends Tool
{
    use HandlesCrmMcpTools;

    public function handle(Request $request, CrmMcpReadService $crm): Response|ResponseFactory
    {
        try {
            $staff = $this->staff($request);
            $payload = $crm->listActivities(
                (int) $request->get('id'),
                $staff,
                (int) $request->get('page', 1),
                $request->get('per_page') !== null ? (int) $request->get('per_page') : null,
                $request->get('keyword'),
            );

            return $this->ok(
                $payload,
                'Returned '.count($payload['activities']).' activit(y/ies) on page '.$payload['page'].'.'
            );
        } catch (CrmMcpAccessException $e) {
            return $this->fail($e);
        } catch (\Throwable $e) {
            return $this->unexpected($e);
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
            'page' => $schema->integer()
                ->description('Page number (default 1).')
                ->nullable(),
            'per_page' => $schema->integer()
                ->description('Rows per page (default from server config, max 100).')
                ->nullable(),
            'keyword' => $schema->string()
                ->description('Optional keyword filter on subject/description.')
                ->nullable(),
        ];
    }
}
