<?php

declare(strict_types=1);

namespace App\Mcp\Prompts;

use App\Mcp\Concerns\ResolvesMember;
use App\Resources\ResourceRegistry;
use App\Support\UntrustedContent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

/**
 * Frames a read of one record. The id is client-supplied text, so it travels
 * inside an untrusted-content fence rather than in the instruction itself.
 */
#[Description('Explains one record of a resource in your current organization.')]
final class InspectRecord extends Prompt
{
    use ResolvesMember;

    public function __construct(private readonly ResourceRegistry $registry) {}

    /**
     * @throws AuthenticationException|AuthorizationException
     */
    public function handle(Request $request): Response
    {
        $this->authorizedMember($request);

        $resource = $this->registry->get($request->string('resource')->toString());
        $id = UntrustedContent::fence($request->string('id')->toString(), 'record-id');

        return Response::text(
            "Call the show-record tool with resource \"{$resource->key()}\" and the record id inside the fenced block below. "
            ."Explain the record in plain words and say who may act on it. Do not propose any change.\n\n{$id}",
        );
    }

    /**
     * @return list<Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument('resource', 'The resource key the record belongs to, as listed in starter://resources/catalog.', required: true),
            new Argument('id', 'The record id.', required: true),
        ];
    }
}
