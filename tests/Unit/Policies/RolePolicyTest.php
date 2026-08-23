<?php

declare(strict_types=1);

use App\Actions\SeedOrganizationRoles;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\Gate;

it('lets an owner manage the roles their organization owns', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();
    $roles = resolve(SeedOrganizationRoles::class)->handle($organization);

    resolve(OrganizationContext::class)->runAs($organization, function () use ($user, $roles): void {
        expect(Gate::forUser($user)->allows('view', $roles['Member']))->toBeTrue()
            ->and(Gate::forUser($user)->allows('update', $roles['Member']))->toBeTrue()
            ->and(Gate::forUser($user)->allows('delete', $roles['Member']))->toBeTrue();
    });
});

it('never lets a protected role be edited or deleted', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();
    $roles = resolve(SeedOrganizationRoles::class)->handle($organization);

    resolve(OrganizationContext::class)->runAs($organization, function () use ($user, $roles): void {
        expect(Gate::forUser($user)->allows('view', $roles['Owner']))->toBeTrue()
            ->and(Gate::forUser($user)->allows('update', $roles['Owner']))->toBeFalse()
            ->and(Gate::forUser($user)->allows('delete', $roles['Owner']))->toBeFalse();
    });
});

it('refuses a role from another organization', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();

    $other = Organization::factory()->create();
    $otherRoles = resolve(SeedOrganizationRoles::class)->handle($other);

    resolve(OrganizationContext::class)->runAs($organization, function () use ($user, $otherRoles): void {
        expect(Gate::forUser($user)->allows('view', $otherRoles['Member']))->toBeFalse();
    });
});
