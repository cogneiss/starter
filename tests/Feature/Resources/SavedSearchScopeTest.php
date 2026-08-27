<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\SavedSearch;
use App\Models\User;
use App\Support\OrganizationContext;

/**
 * A saved search belongs to one person inside one organization, and both halves
 * of that pair are a where clause on the query rather than a check on the row
 * that came back.
 *
 * The difference is visible in the status code. A record the query never reached
 * is a 404; a 403 would mean the row was fetched, looked at, and then refused —
 * which also tells the person asking that the id is real.
 */
function savedSearchFor(User $user, Organization $organization, string $name): SavedSearch
{
    return resolve(OrganizationContext::class)->runAs(
        $organization,
        fn (): SavedSearch => SavedSearch::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'name' => $name,
        ]),
    );
}

it("lists only the acting person's own saved searches", function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();
    $colleague = User::factory()->forOrganization($organization, 'Member')->create();

    $other = Organization::factory()->create();
    $stranger = User::factory()->forOrganization($other)->create();

    savedSearchFor($owner, $organization, 'Mine');
    savedSearchFor($colleague, $organization, "My colleague's");
    savedSearchFor($stranger, $other, "Another tenant's");

    $this->actingAs($owner)
        ->get(route('organization-member.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('members.searches', 1)
            ->where('members.searches.0.name', 'Mine'));
});

it("does not find a colleague's saved search by id", function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();
    $colleague = User::factory()->forOrganization($organization, 'Member')->create();

    $search = savedSearchFor($colleague, $organization, 'Theirs');

    $this->actingAs($owner)
        ->get(route('saved-search.show', $search->id))
        ->assertNotFound();
});

it("does not find another organization's saved search by id", function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $other = Organization::factory()->create();
    $stranger = User::factory()->forOrganization($other)->create();

    $search = savedSearchFor($stranger, $other, 'Theirs');

    $this->actingAs($owner)
        ->get(route('saved-search.show', $search->id))
        ->assertNotFound();
});

it('opens the list as the saved search describes it', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $search = savedSearchFor($owner, $organization, 'Mine');

    resolve(OrganizationContext::class)->runAs($organization, function () use ($search): void {
        $search->update(['query' => ['q' => 'ada', 'sort' => 'user.name', 'dir' => 'desc']]);
    });

    $response = $this->actingAs($owner)->get(route('saved-search.show', $search->id));

    $response->assertRedirectContains('q=ada')
        ->assertRedirectContains('sort=user.name')
        ->assertRedirectContains('dir=desc');
});
