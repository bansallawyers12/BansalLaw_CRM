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

#[Description('Get core details for one client or lead by admins.id (the CRM record id returned from search).')]
class GetRecordTool extends Tool
{
    use HandlesCrmMcpTools;

    public function handle(Request $request, CrmMcpReadService $crm): Response|ResponseFactory
    {
        try {
            $staff = $this->staff($request);
            $record = $crm->getRecord((int) $request->get('id'), $staff);

            return $this->ok(
                ['record' => $record],
                'Loaded '.($record['type'] ?? 'record').' '.$record['reference'].' ('.$record['name'].').'
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
                ->description('CRM record id (admins.id / search result id).')
                ->required(),
        ];
    }
}
