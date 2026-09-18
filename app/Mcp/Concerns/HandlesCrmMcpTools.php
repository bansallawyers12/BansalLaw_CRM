<?php

namespace App\Mcp\Concerns;

use App\Models\Staff;
use App\Services\CrmMcp\CrmMcpAccessException;
use App\Services\CrmMcp\CrmMcpReadService;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

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
}
