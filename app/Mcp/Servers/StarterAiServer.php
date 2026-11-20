<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Ai\Tools\ListResourceRecords;
use App\Ai\Tools\ProposeAction;
use App\Ai\Tools\ShowResourceRecord;
use App\Mcp\Prompts\InspectRecord;
use App\Mcp\Prompts\SummarizeResource;
use App\Mcp\Resources\ResourceCatalog;
use App\Mcp\Tools\AppInfo;
use App\Mcp\Tools\ListRecords;
use App\Mcp\Tools\ProposeChange;
use App\Mcp\Tools\ShowRecord;
use App\Models\User;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Mcp\Server;

/**
 * The application's resource registry, offered to an MCP client.
 *
 * The surface is deliberately the agent's surface: the same record tool classes,
 * the same policies, the same proposal-only write path. DELEGATES says which AI
 * tool each MCP tool stands in for, and a test holds the two sides together so
 * a fork of a tool cannot quietly appear here with its checks left out.
 * AppInfo is the one tool with no delegate: it reads platform detail, not
 * organization records, and is held to the `platform` ability itself.
 *
 * Resources and prompts are read-only and member-scoped through the same
 * ResolvesMember gate. Over stdio nobody signs in, so boot() takes the member
 * named by STARTER_MCP_USER; an unset or unknown email leaves nobody signed in
 * and every call is refused rather than answered broadly.
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

    protected array $tools = [
        ListRecords::class,
        ShowRecord::class,
        ProposeChange::class,
        AppInfo::class,
    ];

    protected array $resources = [
        ResourceCatalog::class,
    ];

    protected array $prompts = [
        SummarizeResource::class,
        InspectRecord::class,
    ];

    /**
     * Over stdio there is no session, so the member is named by the
     * STARTER_MCP_USER environment variable, an email the client passes to the
     * process. A caller already signed in is left alone. A blank or unknown
     * email signs nobody in: the server still starts, and every call is refused
     * by ResolvesMember. There is no fallback to a first or default user.
     */
    public function boot(): void
    {
        if (Auth::check()) {
            return;
        }

        $configured = Env::get('STARTER_MCP_USER');
        $email = is_string($configured) ? Str::lower(mb_trim($configured)) : '';

        $member = $email === '' ? null : User::query()->where('email', $email)->first();

        if ($member instanceof User) {
            Auth::setUser($member);
        }
    }
}
