<?php

declare(strict_types=1);

use App\Actions\CreateOrganization;
use App\Enums\MembershipStatus;
use App\Events\OrganizationCreated;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Event;

it('creates an organization owned by the user', function (): void {
    Event::fake();

    $user = User::factory()->create();

    $organization = resolve(CreateOrganization::class)->handle($user, 'Acme Inc.');

    expect($organization->slug)->toBe('acme-inc')
        ->and($organization->personal)->toBeFalse()
        ->and($user->refresh()->current_organization_id)->toBe($organization->id)
        ->and($user->belongsToOrganization($organization))->toBeTrue();

    $membership = $organization->memberships()->sole();

    expect($membership->status)->toBe(MembershipStatus::Active)
        ->and($membership->joined_at)->not->toBeNull();

    Event::assertDispatched(OrganizationCreated::class, fn (OrganizationCreated $event): bool => $event->organization->is($organization) && $event->owner->is($user));
});

it('creates a personal organization when asked', function (): void {
    $user = User::factory()->create();

    expect(resolve(CreateOrganization::class)->handle($user, 'Jane Doe', personal: true)->personal)
        ->toBeTrue();
});

it('never reuses a slug', function (): void {
    Organization::factory()->create(['slug' => 'acme-inc']);

    $organization = resolve(CreateOrganization::class)->handle(User::factory()->create(), 'Acme Inc.');

    expect($organization->slug)->toStartWith('acme-inc-');
});

it('falls back to a generic slug when the name has none', function (): void {
    $organization = resolve(CreateOrganization::class)->handle(User::factory()->create(), '###');

    expect($organization->slug)->toBe('organization');
});
