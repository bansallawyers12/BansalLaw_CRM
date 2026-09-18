<?php

use App\Http\Middleware\SetAdminGuardFromSanctumUser;
use App\Mcp\Servers\BansalCrmServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| CRM MCP (read-only) — for Grok Bot / remote agents
|--------------------------------------------------------------------------
|
| Public URL (production): https://legal.bansalcrm.com/mcp/crm
| Auth: Authorization: Bearer <staff Sanctum token>
|
| Create a token:
|   php artisan mcp:issue-token staff@example.com --name="Grok Bot"
|
| In Grok Bot chat:
|   Add this custom MCP server named BansalLaw CRM: https://legal.bansalcrm.com/mcp/crm
|   Use header Authorization: Bearer <token>
|
*/

Mcp::web('/mcp/crm', BansalCrmServer::class)
    ->middleware(['auth:sanctum', SetAdminGuardFromSanctumUser::class, 'throttle:60,1']);
