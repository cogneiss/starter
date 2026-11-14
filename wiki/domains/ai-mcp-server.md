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
    - app/Mcp/Concerns/ResolvesMember.php
    - app/Mcp/Resources/ResourceCatalog.php
    - app/Mcp/Prompts/SummarizeResource.php
    - app/Mcp/Prompts/InspectRecord.php
    - routes/ai.php
    - .mcp.json
    - tests/Feature/Ai/McpServerTest.php
    - tests/Feature/Mcp/McpProtocolTest.php
updated: 2026-11-15
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

## Who the caller is over stdio

A stdio process has no session, so nobody is signed in when it starts.
`StarterAiServer::boot()` signs in the user whose email is in the
`STARTER_MCP_USER` environment variable, and `.mcp.json` passes that variable
through from the shell. Unset, blank or unknown leaves nobody signed in, and
every tool, resource and prompt refuses with a message that names the variable.
There is no fallback to a first or default user. A caller that is already signed
in, as in the test harness, is left alone.

`app/Mcp/Concerns/ResolvesMember.php` is the one place that turns the request
into a member and their current organization, and `authorizedMember()` adds the
`view` check inside the organization context so a refusal is audited.

## A resource and two prompts

- `starter://resources/catalog` lists the resource keys the record tools accept,
  with each one's searchable, sortable and filterable fields. It describes the
  registry, so it carries no stored text.
- `summarize-resource` and `inspect-record` return an instruction that tells the
  model which record tool to call. They read nothing themselves. The record id a
  client supplies is fenced with `UntrustedContent::fence()`.

All three are read-only and refuse a caller with no identity or no organization.

## Propose, then confirm: the worked example

`ProposeChange` is the pattern for a write reached over MCP. The tool never
writes: it returns a confirm block carrying a single-use token. A person then
approves it in the application with `POST ai/confirm/{token}`, as themselves,
and a replay of the same token is refused. `tests/Feature/Mcp/McpProtocolTest.php`
walks the whole path with an `invite-member` proposal. A new write action
registers a confirmable action ([[domains/ai-confirm-tokens]]); it does not add
a second write tool to this server.

## Discovery

The same test drives the protocol directly: `server/discover`, `tools/list`,
`resources/list` and `prompts/list`, and asserts the exact catalog. Adding or
removing a tool, resource or prompt fails it until the list is updated on
purpose.

## It ships disabled

The `.mcp.json` entry carries `"disabled": true`. The repository's standing rule
is that no MCP server is always on: each one re-sends its tool schemas every
turn and on every subagent spawn, which is a real context cost for a server most
sessions never call. Turn it on for the session that needs it, off afterwards.

The registry behind the tools is [[domains/resources]]; the agent-side surface is
[[domains/ai-agents-and-tools]].
