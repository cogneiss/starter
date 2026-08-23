<?php

declare(strict_types=1);

use App\Auth\Contracts\OrganizationResolver;
use App\Auth\Resolvers\SessionOrganizationResolver;
use App\Auth\Resolvers\SingleOrganizationResolver;
use App\Auth\Resolvers\SubdomainOrganizationResolver;
use App\Http\Middleware\ResolveOrganization;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

beforeEach(function (): void {
    config()->set('app.url', 'http://starter.test');
});

/**
 * Run the middleware with the given resolver and return the organization it
 * bound, if any.
 */
function bindOrganization(OrganizationResolver $resolver, Request $request): ?Organization
{
    $context = new OrganizationContext;

    $response = new ResolveOrganization($resolver, $context)
        ->handle($request, fn (): Response => new Response);

    expect($response->getStatusCode())->toBe(200);

    return $context->get();
}

function requestAs(?User $user, string $uri = 'http://starter.test/'): Request
{
    $request = Request::create($uri);

    $request->setUserResolver(fn (): ?User => $user);

    return $request;
}

it('binds the current organization of the user', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();

    expect(bindOrganization(new SessionOrganizationResolver, requestAs($user))?->id)
        ->toBe($organization->id);
});

it('binds nothing for a guest', function (): void {
    expect(bindOrganization(new SessionOrganizationResolver, requestAs(null)))->toBeNull();
});

it('binds nothing when the user has no current organization', function (): void {
    expect(bindOrganization(new SessionOrganizationResolver, requestAs(User::factory()->create())))
        ->toBeNull();
});

it('binds nothing when the membership is suspended', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();

    $user->memberships()->update(['status' => 'suspended']);

    expect(bindOrganization(new SessionOrganizationResolver, requestAs($user)))->toBeNull();
});

it('binds nothing when the user is not a member', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->create(['current_organization_id' => $organization->id]);

    expect(bindOrganization(new SessionOrganizationResolver, requestAs($user)))->toBeNull();
});

it('binds the organization matching the subdomain', function (): void {
    $organization = Organization::factory()->create(['slug' => 'acme']);
    $user = User::factory()->forOrganization($organization)->create();

    expect(bindOrganization(new SubdomainOrganizationResolver, requestAs($user, 'http://acme.starter.test/'))?->id)
        ->toBe($organization->id);
});

it('binds nothing on the application host itself', function (): void {
    $organization = Organization::factory()->create(['slug' => 'acme']);
    $user = User::factory()->forOrganization($organization)->create();

    expect(bindOrganization(new SubdomainOrganizationResolver, requestAs($user)))->toBeNull();
});

it('binds nothing for an unknown subdomain', function (): void {
    $organization = Organization::factory()->create(['slug' => 'acme']);
    $user = User::factory()->forOrganization($organization)->create();

    expect(bindOrganization(new SubdomainOrganizationResolver, requestAs($user, 'http://other.starter.test/')))
        ->toBeNull();
});

it('binds nothing on a subdomain for a guest', function (): void {
    Organization::factory()->create(['slug' => 'acme']);

    expect(bindOrganization(new SubdomainOrganizationResolver, requestAs(null, 'http://acme.starter.test/')))
        ->toBeNull();
});

it('binds nothing on a subdomain the user is not a member of', function (): void {
    Organization::factory()->create(['slug' => 'acme']);

    expect(bindOrganization(new SubdomainOrganizationResolver, requestAs(User::factory()->create(), 'http://acme.starter.test/')))
        ->toBeNull();
});

it('binds the only organization in single mode', function (): void {
    $organization = Organization::factory()->create(['created_at' => now()->subDay()]);
    Organization::factory()->create();

    expect(bindOrganization(new SingleOrganizationResolver, requestAs(null))?->id)
        ->toBe($organization->id);
});
