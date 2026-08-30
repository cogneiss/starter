<?php

declare(strict_types=1);

use App\Models\OnboardingProgress;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\Gate;

it('lets somebody manage their own onboarding decision', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();

    $progress = OnboardingProgress::withoutOrganizationScope()
        ->where('user_id', $user->id)
        ->firstOrFail();

    resolve(OrganizationContext::class)->runAs($organization, function () use ($user, $progress): void {
        expect(Gate::forUser($user)->allows('manage', $progress))->toBeTrue();
    });
});

it('refuses a colleague their own owner cannot see', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();
    $colleague = User::factory()->forOrganization($organization)->create();

    $theirs = OnboardingProgress::withoutOrganizationScope()
        ->where('user_id', $colleague->id)
        ->firstOrFail();

    resolve(OrganizationContext::class)->runAs($organization, function () use ($user, $theirs): void {
        expect(Gate::forUser($user)->allows('manage', $theirs))->toBeFalse();
    });
});

it('refuses a decision from another organization', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();
    $elsewhere = OnboardingProgress::factory()->create();

    resolve(OrganizationContext::class)->runAs($organization, function () use ($user, $elsewhere): void {
        expect(Gate::forUser($user)->allows('manage', $elsewhere))->toBeFalse();
    });
});
