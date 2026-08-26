<?php

declare(strict_types=1);

use App\Actions\SearchResources;
use App\Data\SearchGroupData;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Support\OrganizationContext;

// A palette that returns everything is a list screen with worse ergonomics. Six
// records match; five come back, and the sixth is left to the list itself.
it('caps a group at five hits however many match', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    foreach (range(1, 6) as $index) {
        OrganizationInvitation::factory()->create([
            'organization_id' => $organization->id,
            'email' => "candidate{$index}@example.test",
        ]);
    }

    $groups = resolve(OrganizationContext::class)->runAs(
        $organization,
        fn (): array => resolve(SearchResources::class)->handle($owner, 'candidate'),
    );

    $invitations = array_values(array_filter(
        $groups,
        fn (SearchGroupData $group): bool => $group->key === 'organization-invitations',
    ));

    expect($invitations)->toHaveCount(1)
        ->and($invitations[0]->results)->toHaveCount(5);
});
