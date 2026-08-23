<?php

declare(strict_types=1);

use App\Models\ImpersonationLog;
use App\Models\Organization;
use App\Models\User;
use App\Support\Impersonation;

beforeEach(function (): void {
    config()->set('features.defaults.impersonation-enabled', true);
});

it('starts an impersonation and writes the audit row', function (): void {
    $organization = Organization::factory()->create();
    $target = User::factory()->forOrganization($organization)->create();
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->post(route('user-impersonation.store', $target))
        ->assertRedirectToRoute('dashboard')
        ->assertSessionHas(Impersonation::USER_KEY, $admin->id);

    $this->assertAuthenticatedAs($target);

    $log = ImpersonationLog::query()->sole();

    expect($log->impersonator_user_id)->toBe($admin->id)
        ->and($log->impersonated_user_id)->toBe($target->id)
        ->and($log->organization_id)->toBe($organization->id)
        ->and($log->ended_at)->toBeNull();
});

it('stops an impersonation and restores the original user', function (): void {
    $target = User::factory()->create();
    $admin = User::factory()->superAdmin()->create();
    $log = ImpersonationLog::factory()->create([
        'impersonator_user_id' => $admin->id,
        'impersonated_user_id' => $target->id,
    ]);

    $this->actingAs($target)
        ->withSession([
            Impersonation::USER_KEY => $admin->id,
            Impersonation::LOG_KEY => $log->id,
        ])
        ->delete(route('user-impersonation.destroy'))
        ->assertRedirectToRoute('dashboard')
        ->assertSessionMissing(Impersonation::USER_KEY);

    $this->assertAuthenticatedAs($admin);

    expect($log->fresh()?->ended_at)->not->toBeNull();
});

it('refuses to start an impersonation for a user who is not platform staff', function (): void {
    $admin = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('user-impersonation.store', $target))
        ->assertForbidden();

    expect(ImpersonationLog::query()->count())->toBe(0);
});

it('hides impersonation entirely when the feature is off', function (): void {
    config()->set('features.defaults.impersonation-enabled', false);

    $admin = User::factory()->superAdmin()->create();
    $target = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('user-impersonation.store', $target))
        ->assertNotFound();
});

it('forbids the sensitive account routes while impersonating', function (string $method, string $route): void {
    $target = User::factory()->create();
    $admin = User::factory()->superAdmin()->create();
    $log = ImpersonationLog::factory()->create([
        'impersonator_user_id' => $admin->id,
        'impersonated_user_id' => $target->id,
    ]);

    $this->actingAs($target)
        ->withSession([
            Impersonation::USER_KEY => $admin->id,
            Impersonation::LOG_KEY => $log->id,
        ])
        ->call($method, route($route, $route === 'user-impersonation.store' ? $target : []))
        ->assertForbidden();
})->with([
    ['delete', 'user.destroy'],
    ['patch', 'user-profile.update'],
    ['get', 'password.edit'],
    ['put', 'password.update'],
    ['get', 'two-factor.show'],
    ['get', 'passkey.show'],
    ['post', 'user-impersonation.store'],
]);

it('shares the impersonator with the front end', function (): void {
    $organization = Organization::factory()->create();
    $target = User::factory()->forOrganization($organization)->create();
    $admin = User::factory()->superAdmin()->create();
    $log = ImpersonationLog::factory()->create([
        'impersonator_user_id' => $admin->id,
        'impersonated_user_id' => $target->id,
    ]);

    $this->actingAs($target)
        ->withSession([
            Impersonation::USER_KEY => $admin->id,
            Impersonation::LOG_KEY => $log->id,
        ])
        ->get(route('organization.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('impersonating.name', $admin->name));
});
