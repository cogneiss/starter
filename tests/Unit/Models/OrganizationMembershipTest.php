<?php

declare(strict_types=1);

use App\Enums\MembershipStatus;
use App\Models\Organization;
use App\Models\User;

it('belongs to an organization and a user', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();

    $membership = $organization->memberships()->sole();

    expect($membership->organization->id)->toBe($organization->id)
        ->and($membership->user->id)->toBe($user->id)
        ->and($membership->isActive())->toBeTrue();
});

it('is not active when suspended', function (): void {
    $organization = Organization::factory()->create();
    User::factory()->forOrganization($organization)->create();

    $membership = $organization->memberships()->sole();
    $membership->update(['status' => MembershipStatus::Suspended]);

    expect($membership->isActive())->toBeFalse();
});
