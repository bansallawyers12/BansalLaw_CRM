<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\DownloadDocumentTool;
use App\Mcp\Tools\GetBillingTool;
use App\Mcp\Tools\GetRecordTool;
use App\Mcp\Tools\ListActivitiesTool;
use App\Mcp\Tools\ListDocumentsTool;
use App\Mcp\Tools\ListMattersTool;
use App\Mcp\Tools\ListNotesTool;
use App\Mcp\Tools\SearchClientsLeadsTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Bansal Law CRM')]
#[Version('1.0.0')]
#[Instructions('Read-only access to Bansal Law CRM client and lead files. Use search_clients_leads first to find a record id, then get_record, list_matters, list_notes, list_activities, get_billing, list_documents, and download_document. Document upload is not available. Always respect staff visibility — you only see files the authenticated staff token may access.')]
class BansalCrmServer extends Server
{
    protected array $tools = [
        SearchClientsLeadsTool::class,
        GetRecordTool::class,
        ListMattersTool::class,
        ListNotesTool::class,
        ListActivitiesTool::class,
        GetBillingTool::class,
        ListDocumentsTool::class,
        DownloadDocumentTool::class,
    ];

    protected array $resources = [];

    protected array $prompts = [];
}
