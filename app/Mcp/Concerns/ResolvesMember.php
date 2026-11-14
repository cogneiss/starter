<?php

declare(strict_types=1);

namespace App\Mcp\Concerns;

use App\Ai\Tools\Concerns\AuthorizesToolCall;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Laravel\Mcp\Request;

/**
 * Who is calling, and which organization they are calling in.
 *
 * Every MCP primitive starts here, so a client with no identity is refused the
 * same way whether it called a tool, read a resource or asked for a prompt.
 */
trait ResolvesMember
{
    use AuthorizesToolCall;

    /**
     * @return array{0: User, 1: Organization}
     *
     * @throws AuthenticationException|AuthorizationException
     */
    protected function member(Request $request): array
    {
        $user = $request->user();

        throw_unless($user instanceof User, AuthenticationException::class, 'This server answers a signed-in member only. Over stdio, set STARTER_MCP_USER to the email of your account.');

        $organization = $user->currentOrganization;

        throw_unless($organization instanceof Organization, AuthorizationException::class, 'This member has no current organization to work in.');

        return [$user, $organization];
    }

    /**
     * The member, once allowed into their organization. Authorizing runs inside
     * the organization context so a refusal can be audited against it.
     *
     * @return array{0: User, 1: Organization}
     *
     * @throws AuthenticationException|AuthorizationException
     */
    protected function authorizedMember(Request $request): array
    {
        [$user, $organization] = $this->member($request);

        resolve(OrganizationContext::class)->runAs($organization, fn () => $this->authorizeFor($user, 'view', $organization));

        return [$user, $organization];
    }
}
