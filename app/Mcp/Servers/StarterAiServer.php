<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Ai\Tools\ListResourceRecords;
use App\Ai\Tools\ProposeAction;
use App\Ai\Tools\ShowResourceRecord;
use App\Mcp\Tools\ListRecords;
use App\Mcp\Tools\ProposeChange;
use App\Mcp\Tools\ShowRecord;
use Laravel\Mcp\Server;

/**
 * The application's resource registry, offered to an MCP client.
 *
 * The surface is deliberately the agent's surface: the same three tool classes,
 * the same policies, the same proposal-only write path. DELEGATES says which AI
 * tool each MCP tool stands in for, and a test holds the two sides together so
 * a fork of a tool cannot quietly appear here with its checks left out.
 */
final class StarterAiServer extends Server
{
    /**
     * The AI tool behind each MCP tool.
     *
     * @var array<class-string, class-string>
     */
    public const array DELEGATES = [
        ListRecords::class => ListResourceRecords::class,
        ShowRecord::class => ShowResourceRecord::class,
        ProposeChange::class => ProposeAction::class,
    ];

    protected string $name = 'Starter AI';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MARKDOWN'
    Reads the records of this application's registered resources, as the member
    you are signed in as. Writes are proposed, never performed: a proposal comes
    back as a confirm token the person approves in the application itself.
    MARKDOWN;

    /**
     * @var list<class-string<Server\Tool>>
     */
    protected array $tools = [
        ListRecords::class,
        ShowRecord::class,
        ProposeChange::class,
    ];
}
