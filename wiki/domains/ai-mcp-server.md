---
title: AI MCP server
status: current
supersedes: []
code_refs:
    - app/Mcp/Servers/StarterAiServer.php
    - app/Mcp/Tools/Concerns/DelegatesToAiTool.php
    - app/Mcp/Tools/ListRecords.php
    - app/Mcp/Tools/ShowRecord.php
    - app/Mcp/Tools/ProposeChange.php
    - app/Mcp/Tools/AppInfo.php
    - routes/ai.php
    - .mcp.json
    - tests/Feature/Ai/McpServerTest.php
updated: 2026-10-30
---

# AI MCP server

`starter-ai` offers this application's resource registry to an MCP client —
Claude Code, an editor, anything that speaks the protocol. It is registered in
`routes/ai.php` with `Mcp::local()`: stdio only, started by a client on a
developer's own machine, as themselves, never exposed over HTTP.

## Three record tools, and none of them are new

| MCP tool        | Delegates to                       |
| --------------- | ---------------------------------- |
| `ListRecords`   | `App\Ai\Tools\ListResourceRecords` |
| `ShowRecord`    | `App\Ai\Tools\ShowResourceRecord`  |
| `ProposeChange` | `App\Ai\Tools\ProposeAction`       |

`StarterAiServer::DELEGATES` states the mapping and
`tests/Feature/Ai/McpServerTest.php` holds the two sides together, so a forked
tool cannot appear here with its checks left out.

`app/Mcp/Tools/Concerns/DelegatesToAiTool.php` is the whole of what an MCP tool
does: resolve the signed-in member, refuse when there is none, bind their current
organization through `OrganizationContext`, and hand the request to the AI tool.
Everything deciding what may be read or written stays in `app/Ai/Tools` — a
second copy of an authorization check is a second place for it to be wrong.
Writes are proposals here too ([[domains/ai-confirm-tokens]]).

## One tool with no delegate

`app-info` (`app/Mcp/Tools/AppInfo.php`) reports the application name,
environment, URL, PHP version and a short list of package versions, so a client
knows what it is talking to before it reads anything. Nothing it returns belongs
to an organization, so there is no AI tool behind it and it is left out of
`DELEGATES`. Versions and the environment are platform detail: the tool calls
`authorizeFor()` on the `platform` ability, the same gate as the admin area, and
a member without it is refused and logged.

## It ships disabled

The `.mcp.json` entry carries `"disabled": true`. The repository's standing rule
is that no MCP server is always on: each one re-sends its tool schemas every
turn and on every subagent spawn, which is a real context cost for a server most
sessions never call. Turn it on for the session that needs it, off afterwards.

The registry behind the tools is [[domains/resources]]; the agent-side surface is
[[domains/ai-agents-and-tools]].
