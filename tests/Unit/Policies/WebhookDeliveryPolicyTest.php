<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\Gate;

it('lets a member read the delivery log of their own organization', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization, 'Member')->create();

    resolve(OrganizationContext::class)->runAs($organization, function () use ($user): void {
        expect(Gate::forUser($user)->allows('viewAny', WebhookDelivery::class))->toBeTrue();
    });
});

it('refuses the delivery log with no organization bound', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();

    expect(Gate::forUser($user)->allows('viewAny', WebhookDelivery::class))->toBeFalse();
});
