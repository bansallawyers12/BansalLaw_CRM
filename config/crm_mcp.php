<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Read-only CRM MCP limits
    |--------------------------------------------------------------------------
    |
    | Caps for Grok / remote MCP tool responses. Keep these modest so a single
    | tool call cannot dump an entire matter file into the model context.
    |
    */

    'search_limit' => (int) env('CRM_MCP_SEARCH_LIMIT', 20),

    'notes_limit' => (int) env('CRM_MCP_NOTES_LIMIT', 30),

    'activities_limit' => (int) env('CRM_MCP_ACTIVITIES_LIMIT', 40),

    'documents_limit' => (int) env('CRM_MCP_DOCUMENTS_LIMIT', 50),

    'invoice_rows_limit' => (int) env('CRM_MCP_INVOICE_ROWS_LIMIT', 25),

    'download_url_minutes' => (int) env('CRM_MCP_DOWNLOAD_URL_MINUTES', 5),

];
