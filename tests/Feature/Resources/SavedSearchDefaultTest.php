<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\SavedSearch;
use App\Models\User;
use App\Support\OrganizationContext;

/**
 * A default view answers the question the first visit does not ask.
 *
 * It must also stop answering it. The moment the URL carries any of the list's
 * own parameters the person has said something themselves, and a stored
 * preference that spoke over that would snap the list back on every sort click
 * and make the second page unreachable.
 */
function defaultSavedSearch(Organization $organization, User $user): SavedSearch
{
    return resolve(OrganizationContext::class)->runAs(
        $organization,
        fn (): SavedSearch => SavedSearch::factory()->default()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'query' => ['q' => 'zoe', 'sort' => 'user.name', 'dir' => 'desc'],
        ]),
    );
}

it('applies the default on the first visit to the list', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create(['name' => 'Ada']);
    User::factory()->forOrganization($organization, 'Member')->create(['name' => 'Zoe']);

    defaultSavedSearch($organization, $owner);

    $this->actingAs($owner)
        ->get(route('organization-member.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('members.query.q', 'zoe')
            ->where('members.query.dir', 'desc')
            ->has('members.rows', 1));
});

it('lets the url win once the person has said something about the list', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create(['name' => 'Ada']);
    User::factory()->forOrganization($organization, 'Member')->create(['name' => 'Zoe']);

    defaultSavedSearch($organization, $owner);

    $this->actingAs($owner)
        ->get(route('organization-member.edit', ['sort' => 'user.name', 'dir' => 'asc']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('members.query.q', '')
            ->where('members.query.dir', 'asc')
            ->has('members.rows', 2));
});

it('saves the current view and makes it the default', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $previous = defaultSavedSearch($organization, $owner);

    $this->actingAs($owner)
        ->fromRoute('organization-member.edit')
        ->post(route('saved-search.store'), [
            'resource' => 'organization-members',
            'name' => 'Suspended first',
            'query' => ['q' => 'ada', 'sort' => 'nonsense', 'dir' => 'desc'],
            'is_default' => true,
        ])
        ->assertRedirectToRoute('organization-member.edit');

    resolve(OrganizationContext::class)->runAs($organization, function () use ($previous, $owner): void {
        $saved = SavedSearch::query()->where('name', 'Suspended first')->sole();

        expect($saved->user_id)->toBe($owner->id)
            ->and($saved->organization_id)->toBe($owner->organizations()->sole()->id)
            ->and($saved->is_default)->toBeTrue()
            // Normalised on the way in as well as on the way out, so a nonsense
            // sort is never written down as if the resource had agreed to it.
            ->and($saved->query['sort'] ?? null)->toBe('user.name')
            ->and(SavedSearch::query()->findOrFail($previous->id)->is_default)->toBeFalse();
    });
});

it('refuses to save a view of a resource that does not exist', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $this->actingAs($owner)
        ->fromRoute('organization-member.edit')
        ->post(route('saved-search.store'), [
            'resource' => 'not-a-resource',
            'name' => 'Nope',
        ])
        ->assertSessionHasErrors('resource');
});
