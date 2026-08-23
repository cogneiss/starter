<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\Gate;

it('lets an owner manage memberships of their own organization', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();
    $membership = $organization->memberships()->sole();

    resolve(OrganizationContext::class)->runAs($organization, function () use ($user, $membership): void {
        expect(Gate::forUser($user)->allows('view', $membership))->toBeTrue()
            ->and(Gate::forUser($user)->allows('update', $membership))->toBeTrue()
            ->and(Gate::forUser($user)->allows('delete', $membership))->toBeTrue();
    });
});

it('refuses a member without the permission', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization, 'Member')->create();
    $membership = $organization->memberships()->sole();

    resolve(OrganizationContext::class)->runAs($organization, function () use ($user, $membership): void {
        expect(Gate::forUser($user)->allows('view', $membership))->toBeTrue()
            ->and(Gate::forUser($user)->allows('update', $membership))->toBeFalse()
            ->and(Gate::forUser($user)->allows('delete', $membership))->toBeFalse();
    });
});

it('refuses a membership from another organization', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();

    $other = Organization::factory()->create();
    $otherMembership = OrganizationMembership::factory()->create([
        'organization_id' => $other->id,
        'user_id' => User::factory()->create()->id,
    ]);

    resolve(OrganizationContext::class)->runAs($organization, function () use ($user, $otherMembership): void {
        expect(Gate::forUser($user)->allows('view', $otherMembership))->toBeFalse();
    });
});
