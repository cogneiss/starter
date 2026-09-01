<?php

declare(strict_types=1);

use App\Models\ImpersonationLog;
use App\Models\Organization;
use App\Models\User;
use App\Support\Impersonation;

beforeEach(function (): void {
    config()->set('features.defaults.impersonation-enabled', true);
});

it('starts an impersonation from the admin through StartImpersonation, writing the log', function (): void {
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
        ->and($log->ended_at)->toBeNull();
});

it('shares the impersonation banner state while impersonating', function (): void {
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
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('impersonating.id', $admin->id)
            ->where('impersonating.name', $admin->name));
});

it('still forbids sensitive routes during an impersonation', function (): void {
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
        ->patch(route('user-profile.update'), ['name' => 'New Name'])
        ->assertForbidden();
});
