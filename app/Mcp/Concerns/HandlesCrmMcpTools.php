<?php

namespace App\Mcp\Concerns;

use App\Models\Staff;
use App\Services\CrmMcp\CrmMcpAccessException;
use App\Services\CrmMcp\CrmMcpReadService;
use Illuminate\Support\Facades\Log;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Throwable;

trait HandlesCrmMcpTools
{
    protected function staff(Request $request): Staff
    {
        return app(CrmMcpReadService::class)->resolveStaff($request->user());
    }

    protected function fail(CrmMcpAccessException $e): Response
    {
        return Response::error($e->getMessage());
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $data
     */
    protected function ok(array $data, string $summary): ResponseFactory
    {
        return Response::make(
            Response::text($summary)
        )->withStructuredContent($data);
    }

    protected function unexpected(Throwable $e): Response
    {
        Log::error('crm_mcp.tool_failed', [
            'tool' => static::class,
            'message' => $e->getMessage(),
        ]);

        return Response::error('Unable to complete that CRM request. Please try again.');
    }
}
