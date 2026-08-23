<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\Gate;

it('lets an owner manage their own organization', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();

    resolve(OrganizationContext::class)->runAs($organization, function () use ($user, $organization): void {
        expect(Gate::forUser($user)->allows('view', $organization))->toBeTrue()
            ->and(Gate::forUser($user)->allows('update', $organization))->toBeTrue()
            ->and(Gate::forUser($user)->allows('delete', $organization))->toBeTrue();
    });
});

it('refuses a member without the permission', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization, 'Member')->create();

    resolve(OrganizationContext::class)->runAs($organization, function () use ($user, $organization): void {
        expect(Gate::forUser($user)->allows('view', $organization))->toBeTrue()
            ->and(Gate::forUser($user)->allows('update', $organization))->toBeFalse()
            ->and(Gate::forUser($user)->allows('delete', $organization))->toBeFalse();
    });
});

it('refuses an organization outside the bound context', function (): void {
    $organization = Organization::factory()->create();
    $other = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();

    resolve(OrganizationContext::class)->runAs($organization, function () use ($user, $other): void {
        expect(Gate::forUser($user)->allows('view', $other))->toBeFalse();
    });
});
