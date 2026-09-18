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

#[Description('Get billing summary for a client: trust balance, outstanding invoices, invoiced total, and recent invoice rows. Optional matter filter.')]
class GetBillingTool extends Tool
{
    use HandlesCrmMcpTools;

    public function handle(Request $request, CrmMcpReadService $crm): Response|ResponseFactory
    {
        try {
            $staff = $this->staff($request);
            $matterId = $request->get('client_matter_id');
            $payload = $crm->getBilling(
                (int) $request->get('id'),
                $staff,
                $matterId !== null && $matterId !== '' ? (int) $matterId : null,
            );

            return $this->ok(
                $payload,
                'Billing: outstanding '.$payload['outstanding_balance']
                    .', trust '.$payload['trust_balance']
                    .', invoiced '.$payload['invoiced_total'].'.'
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
            'client_matter_id' => $schema->integer()
                ->description('Optional client_matters.id to scope billing to one matter.')
                ->nullable(),
        ];
    }
}
