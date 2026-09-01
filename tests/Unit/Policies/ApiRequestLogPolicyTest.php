<?php

declare(strict_types=1);

use App\Models\ApiRequestLog;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\Gate;

it('lets a member read the usage of their own organization', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization, 'Member')->create();
    $log = ApiRequestLog::factory()->create(['organization_id' => $organization->id]);

    resolve(OrganizationContext::class)->runAs($organization, function () use ($user, $log): void {
        expect(Gate::forUser($user)->allows('viewAny', ApiRequestLog::class))->toBeTrue()
            ->and(Gate::forUser($user)->allows('view', $log))->toBeTrue();
    });
});

it('refuses a usage row that belongs to another organization', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();
    $log = ApiRequestLog::factory()->create();

    resolve(OrganizationContext::class)->runAs($organization, function () use ($user, $log): void {
        expect(Gate::forUser($user)->allows('view', $log))->toBeFalse();
    });
});

it('refuses a user with no organization bound', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();

    expect(Gate::forUser($user)->allows('viewAny', ApiRequestLog::class))->toBeFalse();
});
