<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\SavedSearch;
use App\Models\User;
use App\Support\OrganizationContext;

/**
 * A saved search is a bookmark, and the table it points at keeps moving.
 *
 * Columns get renamed and filters get retired, and neither of those is a reason
 * to hand somebody a 500 for opening a view they saved last year. The stored
 * query goes back through `ResourceQuery` on the way out, so a sort on a column
 * that no longer exists becomes the resource's default order and a filter key
 * nobody recognises is dropped.
 */
function staleSavedSearch(Organization $organization, User $user, array $query): SavedSearch
{
    return resolve(OrganizationContext::class)->runAs(
        $organization,
        fn (): SavedSearch => SavedSearch::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'query' => $query,
        ]),
    );
}

it('degrades a saved sort on a column the resource dropped', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $search = staleSavedSearch($organization, $owner, [
        'q' => '',
        'sort' => 'a_column_that_was_renamed',
        'dir' => 'desc',
    ]);

    $this->actingAs($owner)
        ->get(route('saved-search.show', $search->id))
        ->assertRedirect();

    $this->actingAs($owner)
        ->get(route('organization-member.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('members.searches.0.query.sort', 'user.name'));
});

it('drops a saved filter key the resource no longer offers', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $search = staleSavedSearch($organization, $owner, [
        'f' => ['a_filter_that_was_retired' => 'yes'],
    ]);

    $this->actingAs($owner)
        ->get(route('saved-search.show', $search->id))
        ->assertRedirect();

    $this->actingAs($owner)
        ->get(route('organization-member.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('members.searches.0.query.filters', []));
});

it('renders the list rather than failing when the default is stale', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    resolve(OrganizationContext::class)->runAs(
        $organization,
        fn (): SavedSearch => SavedSearch::factory()->default()->create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'query' => ['sort' => 'gone', 'per' => 'all', 'page' => '-3', 'f' => 'not-an-array'],
        ]),
    );

    $this->actingAs($owner)
        ->get(route('organization-member.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('members.query.sort', 'user.name')
            ->where('members.query.page', 1)
            ->where('members.query.per', 10));
});
