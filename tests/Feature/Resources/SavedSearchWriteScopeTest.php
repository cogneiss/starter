<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\SavedSearch;
use App\Models\User;
use App\Support\OrganizationContext;

/**
 * The mutating routes are scoped by the same predicate as the reads.
 *
 * A read-only scope is the easier mistake and the worse one: the list looks
 * right, nobody sees anything they should not, and a rename or a delete against
 * a guessed id still lands. So each write is aimed at a saved search belonging
 * to someone else and the record is read back afterwards to prove it did not
 * move.
 */
$foreign = function (bool $sameOrganization): array {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $host = $sameOrganization ? $organization : Organization::factory()->create();

    $other = $sameOrganization
        ? User::factory()->forOrganization($organization, 'Member')->create()
        : User::factory()->forOrganization($host)->create();

    $search = resolve(OrganizationContext::class)->runAs(
        $host,
        fn (): SavedSearch => SavedSearch::factory()->create([
            'organization_id' => $host->id,
            'user_id' => $other->id,
            'name' => 'Theirs',
            'is_default' => false,
        ]),
    );

    return [$owner, $host, $search];
};

dataset('foreign saved searches', [
    "a colleague's" => [true],
    "another organization's" => [false],
]);

it('refuses to rename a saved search it cannot see', function (bool $sameOrganization) use ($foreign): void {
    [$owner, $host, $search] = $foreign($sameOrganization);

    $this->actingAs($owner)
        ->patch(route('saved-search.update', $search->id), ['name' => 'Renamed'])
        ->assertNotFound();

    resolve(OrganizationContext::class)->runAs($host, function () use ($search): void {
        expect(SavedSearch::query()->findOrFail($search->id)->name)->toBe('Theirs');
    });
})->with('foreign saved searches');

it('refuses to make a saved search it cannot see the default', function (bool $sameOrganization) use ($foreign): void {
    [$owner, $host, $search] = $foreign($sameOrganization);

    $this->actingAs($owner)
        ->patch(route('saved-search.update', $search->id), ['is_default' => true])
        ->assertNotFound();

    resolve(OrganizationContext::class)->runAs($host, function () use ($search): void {
        expect(SavedSearch::query()->findOrFail($search->id)->is_default)->toBeFalse();
    });
})->with('foreign saved searches');

it('refuses to delete a saved search it cannot see', function (bool $sameOrganization) use ($foreign): void {
    [$owner, $host, $search] = $foreign($sameOrganization);

    $this->actingAs($owner)
        ->delete(route('saved-search.destroy', $search->id))
        ->assertNotFound();

    resolve(OrganizationContext::class)->runAs($host, function () use ($search): void {
        expect(SavedSearch::query()->whereKey($search->id)->exists())->toBeTrue();
    });
})->with('foreign saved searches');

it("renames, defaults and deletes a person's own saved search", function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $search = resolve(OrganizationContext::class)->runAs(
        $organization,
        fn (): SavedSearch => SavedSearch::factory()->create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'name' => 'Mine',
        ]),
    );

    $previous = resolve(OrganizationContext::class)->runAs(
        $organization,
        fn (): SavedSearch => SavedSearch::factory()->default()->create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'name' => 'Was the default',
        ]),
    );

    $this->actingAs($owner)
        ->fromRoute('organization-member.edit')
        ->patch(route('saved-search.update', $search->id), ['name' => 'Renamed', 'is_default' => true])
        ->assertRedirectToRoute('organization-member.edit');

    resolve(OrganizationContext::class)->runAs($organization, function () use ($search, $previous): void {
        expect(SavedSearch::query()->findOrFail($search->id))
            ->name->toBe('Renamed')
            ->is_default->toBeTrue()
            // One default per list, or a first visit has two answers.
            ->and(SavedSearch::query()->findOrFail($previous->id)->is_default)->toBeFalse();
    });

    $this->actingAs($owner)
        ->fromRoute('organization-member.edit')
        ->delete(route('saved-search.destroy', $search->id))
        ->assertRedirectToRoute('organization-member.edit');

    resolve(OrganizationContext::class)->runAs($organization, function () use ($search): void {
        expect(SavedSearch::query()->whereKey($search->id)->exists())->toBeFalse();
    });
});
