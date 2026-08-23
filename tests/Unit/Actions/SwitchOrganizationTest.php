<?php

declare(strict_types=1);

use App\Actions\SwitchOrganization;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

it('points the user at another organization they belong to', function (): void {
    $current = Organization::factory()->create();
    $other = Organization::factory()->create();

    $user = User::factory()->forOrganization($current)->create();
    $other->memberships()->create([
        'user_id' => $user->id,
        'status' => 'active',
        'joined_at' => now(),
    ]);

    resolve(SwitchOrganization::class)->handle($user, $other);

    expect($user->refresh()->current_organization_id)->toBe($other->id);
});

it('refuses to switch to an organization the user does not belong to', function (): void {
    $current = Organization::factory()->create();
    $user = User::factory()->forOrganization($current)->create();

    expect(fn () => resolve(SwitchOrganization::class)->handle($user, Organization::factory()->create()))
        ->toThrow(AuthorizationException::class);

    expect($user->refresh()->current_organization_id)->toBe($current->id);
});
