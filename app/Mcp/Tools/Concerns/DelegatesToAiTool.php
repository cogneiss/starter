<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Concerns;

use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Laravel\Ai\Contracts\Tool as AiTool;
use Laravel\Ai\Tools\Request as AiRequest;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Stringable;

/**
 * The whole of what an MCP tool in this application does.
 *
 * An MCP client is another entry point, not another set of rules: the tool it
 * calls is the same class the agent calls, run for the same member, inside the
 * same organization context. Everything that decides what may be read or
 * written lives in App\Ai\Tools and is not repeated here — a second copy of an
 * authorization check is a second place for it to be wrong.
 */
trait DelegatesToAiTool
{
    /**
     * @param  Closure(User, Organization): AiTool  $delegate
     *
     * @throws AuthenticationException|AuthorizationException
     */
    protected function answer(Request $request, Closure $delegate): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException('This server answers a signed-in member only.');
        }

        $organization = $user->currentOrganization;

        if (! $organization instanceof Organization) {
            throw new AuthorizationException('This member has no current organization to work in.');
        }

        $answer = resolve(OrganizationContext::class)->runAs(
            $organization,
            fn (): string|Stringable => $delegate($user, $organization)->handle(new AiRequest($request->all())),
        );

        return Response::text((string) $answer);
    }
}
