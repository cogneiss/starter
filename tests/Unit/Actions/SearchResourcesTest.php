<?php

declare(strict_types=1);

use App\Actions\SearchResources;
use App\Data\SearchGroupData;
use App\Data\SearchResultData;
use App\Models\AiConfirmToken;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\Auth;

/**
 * @return list<SearchGroupData>
 */
function searchAs(Organization $organization, User $user, string $term): array
{
    return resolve(OrganizationContext::class)->runAs(
        $organization,
        fn (): array => resolve(SearchResources::class)->handle($user, $term),
    );
}

/**
 * @return list<string>
 */
function searchLabels(Organization $organization, User $user, string $term, string $group): array
{
    foreach (searchAs($organization, $user, $term) as $found) {
        if ($found->key === $group) {
            return array_map(fn (SearchResultData $result): string => $result->label, $found->results);
        }
    }

    return [];
}

it('finds a member of the acting organization by name', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();
    User::factory()->forOrganization($organization, 'Member')->create(['name' => 'Marguerite Blythe']);

    expect(searchLabels($organization, $owner, 'Marguerite', 'organization-members'))
        ->toBe(['Marguerite Blythe']);
});

it('finds an invitation by email', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    OrganizationInvitation::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'ines@example.test',
    ]);

    expect(searchLabels($organization, $owner, 'ines@', 'organization-invitations'))
        ->toBe(['ines@example.test']);
});

it('finds the acting organization by name', function (): void {
    $organization = Organization::factory()->create(['name' => 'Thornbury Works']);
    $owner = User::factory()->forOrganization($organization)->create();

    expect(searchLabels($organization, $owner, 'Thornbury', 'organizations'))
        ->toBe(['Thornbury Works']);
});

// A confirmation is addressed to one person. The other member's row is in the
// same organization, so only the user predicate keeps it out.
it('finds the acting user own confirmation and no one else', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();
    $other = User::factory()->forOrganization($organization, 'Member')->create();

    AiConfirmToken::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $owner->id,
        'summary' => 'Invite Marguerite to the organization.',
    ]);

    AiConfirmToken::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $other->id,
        'summary' => 'Invite Percy to the organization.',
    ]);

    Auth::login($owner);

    expect(searchLabels($organization, $owner, 'Invite', 'ai-confirm-tokens'))
        ->toBe(['Invite Marguerite to the organization.']);
});

it('returns nothing for a blank term', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    expect(searchAs($organization, $owner, '   '))->toBe([]);
});

it('treats a wildcard in the term as a literal character', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();
    User::factory()->forOrganization($organization, 'Member')->create(['name' => 'Percy Wildgoose']);

    expect(searchLabels($organization, $owner, '%', 'organization-members'))->toBe([]);
});

// The organization gate. The other organization's rows are excluded by the
// resource's where clause, so they are never loaded to be filtered out.
it('never returns a record belonging to another organization', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $other = Organization::factory()->create();
    User::factory()->forOrganization($other, 'Member')->create(['name' => 'Marguerite Blythe']);
    OrganizationInvitation::factory()->create([
        'organization_id' => $other->id,
        'email' => 'marguerite@example.test',
    ]);

    expect(searchAs($organization, $owner, 'Marguerite'))->toBe([]);
});

// Fail closed. With no organization bound there is nothing to scope to, so the
// answer is no rows rather than every row.
it('returns nothing when no organization is bound', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();
    User::factory()->forOrganization($organization, 'Member')->create(['name' => 'Marguerite Blythe']);

    expect(resolve(SearchResources::class)->handle($owner, 'Marguerite'))->toBe([]);
});

// The permission gate. This is the control bin/prove-control.sh disables: with
// the policy check gone, the member sees the groups their role forbids.
it('omits a group the acting member has no permission to view', function (): void {
    $organization = Organization::factory()->create();
    $member = User::factory()->forOrganization($organization, 'Member')->create();

    resolve(OrganizationContext::class)->runAs($organization, fn () => $member->syncRoles([]));

    OrganizationInvitation::factory()->create([
        'organization_id' => $organization->id,
        'email' => 'ines@example.test',
    ]);

    $keys = array_map(
        fn (SearchGroupData $group): string => $group->key,
        searchAs($organization, $member, 'ines@'),
    );

    expect($keys)->not->toContain('organization-invitations');
});
