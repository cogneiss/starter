<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Resources\Definitions\OrganizationInvitationResource;
use App\Resources\Definitions\OrganizationMemberResource;
use App\Support\OrganizationContext;
use App\Support\ResourceQuery;
use Illuminate\Http\Request;

/**
 * A list request with the given query string, as the framework would hand it to
 * a controller.
 */
function listRequest(array $query = []): Request
{
    return Request::create('/settings/members', 'GET', $query);
}

it('falls back to the default order when the sort column is unknown', function (): void {
    $query = ResourceQuery::fromRequest(
        listRequest(['sort' => 'two_factor_secret']),
        new OrganizationMemberResource,
    );

    expect($query->sort)->toBe('user.name')
        ->and($query->dir)->toBe('asc');
});

it('keeps a sort column the resource allows, in the direction asked for', function (): void {
    $query = ResourceQuery::fromRequest(
        listRequest(['sort' => 'status', 'dir' => 'desc']),
        new OrganizationMemberResource,
    );

    expect($query->sort)->toBe('status')
        ->and($query->dir)->toBe('desc');
});

it('renders a list rather than an error when the sort column does not exist', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $this->actingAs($owner)
        ->get(route('organization-member.edit', ['sort' => 'two_factor_secret', 'dir' => 'sideways']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('members.query.sort', 'user.name')
            ->where('members.query.dir', 'asc')
            ->has('members.rows', 1));
});

it('clamps the page size to an allowed one', function (array $asked, int $expected): void {
    $query = ResourceQuery::fromRequest(listRequest($asked), new OrganizationMemberResource);

    expect($query->per)->toBe($expected);
})->with([
    'far too many' => [['per' => '100000'], 100],
    'between two sizes' => [['per' => '30'], 25],
    'smaller than the smallest' => [['per' => '2'], 10],
    'not a number' => [['per' => 'all'], 10],
    'absent' => [[], 10],
]);

it('survives a page that is not a page', function (array $asked, int $expected): void {
    $query = ResourceQuery::fromRequest(listRequest($asked), new OrganizationMemberResource);

    expect($query->page)->toBe($expected);
})->with([
    'negative' => [['page' => '-4'], 1],
    'zero' => [['page' => '0'], 1],
    'a word' => [['page' => 'last'], 1],
    'an array' => [['page' => ['1']], 1],
    'a real page' => [['page' => '3'], 3],
]);

it('takes a term as text and nothing else', function (): void {
    $long = str_repeat('a', 300);

    expect(ResourceQuery::fromRequest(listRequest(['q' => "  {$long}  "]), new OrganizationMemberResource)->q)
        ->toBe(str_repeat('a', 255))
        ->and(ResourceQuery::fromRequest(listRequest(['q' => ['array']]), new OrganizationMemberResource)->q)
        ->toBe('');
});

it('names the relations a term reaches through, once each', function (): void {
    expect(ResourceQuery::relationsIn(['user.name', 'user.email', 'status']))->toBe(['user']);
});

it('orders through a relation without multiplying rows', function (): void {
    $organization = Organization::factory()->create();
    User::factory()->forOrganization($organization)->create(['name' => 'Zoe']);
    User::factory()->forOrganization($organization, 'Member')->create(['name' => 'Ada']);

    $resource = new OrganizationMemberResource;

    $names = resolve(OrganizationContext::class)->runAs($organization, function () use ($resource): array {
        $query = ResourceQuery::fromRequest(listRequest(['sort' => 'user.name']), $resource);

        return $query->applyTo($resource->scopedQuery(), $resource)
            ->get()
            ->map(fn ($membership): string => (string) $membership->user->name)
            ->all();
    });

    expect($names)->toBe(['Ada', 'Zoe']);
});

it('orders by a plain column and matches a term literally', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    foreach (['b100%@example.com', 'a_b@example.com', 'axb@example.com'] as $email) {
        OrganizationInvitation::factory()->create([
            'organization_id' => $organization->id,
            'email' => $email,
        ]);
    }

    $resource = new OrganizationInvitationResource;

    $emails = resolve(OrganizationContext::class)->runAs($organization, function () use ($resource): array {
        $query = ResourceQuery::fromRequest(listRequest(['sort' => 'email', 'q' => 'a_b']), $resource);

        return $query->applyTo($resource->scopedQuery(), $resource)
            ->pluck('email')
            ->all();
    });

    // The underscore is a wildcard in LIKE; a person typing it means the character.
    expect($emails)->toBe(['a_b@example.com'])
        ->and($owner->email)->not->toBeEmpty();
});
