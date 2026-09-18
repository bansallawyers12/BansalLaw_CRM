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

#[Description('List matters for a client or lead record (client_matters rows).')]
class ListMattersTool extends Tool
{
    use HandlesCrmMcpTools;

    public function handle(Request $request, CrmMcpReadService $crm): Response|ResponseFactory
    {
        try {
            $staff = $this->staff($request);
            $matters = $crm->listMatters((int) $request->get('id'), $staff);

            return $this->ok(
                ['matters' => $matters, 'count' => count($matters)],
                'Found '.count($matters).' matter(s).'
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
        ];
    }
}
