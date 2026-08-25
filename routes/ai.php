<?php

declare(strict_types=1);

use App\Mcp\Servers\StarterAiServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP Servers
|--------------------------------------------------------------------------
|
| A local, stdio server only: it is started by a client on the developer's own
| machine, as themselves, and never exposed over HTTP. The repository's standing
| rule is that no MCP server is always on, so the .mcp.json entry ships
| disabled — see wiki/domains/ai-mcp-server.md.
|
*/

Mcp::local('starter-ai', StarterAiServer::class);
