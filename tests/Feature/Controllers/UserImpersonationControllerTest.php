<?php

declare(strict_types=1);

use App\Models\ImpersonationLog;
use App\Models\LoginHistory;
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

it('leaves an admin signed in when stop is sent without an impersonation', function (): void {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->delete(route('user-impersonation.destroy'))
        ->assertRedirectToRoute('dashboard')
        ->assertSessionMissing('inertia.flash_data');

    $this->assertAuthenticatedAs($admin);
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
        ->assertNotFound();

    expect(ImpersonationLog::query()->count())->toBe(0);
});

it('sends a guest to the login page instead of the impersonation route', function (): void {
    $this->post(route('user-impersonation.store', User::factory()->create()))
        ->assertRedirectToRoute('login');
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

it('clears the password confirmation when impersonation starts', function (): void {
    $target = User::factory()->create();
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('user-impersonation.store', $target))
        ->assertSessionMissing('auth.password_confirmed_at');
});

it('clears the password confirmation when impersonation stops', function (): void {
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
            'auth.password_confirmed_at' => time(),
        ])
        ->delete(route('user-impersonation.destroy'))
        ->assertSessionMissing('auth.password_confirmed_at');
});

it('lets the admin stop impersonating a member of an organization that forces two-factor', function (): void {
    $organization = Organization::factory()->create(['require_two_factor' => true]);
    $target = User::factory()->forOrganization($organization)->withoutTwoFactor()->create();
    $admin = User::factory()->superAdmin()->create();
    $log = ImpersonationLog::factory()->create([
        'impersonator_user_id' => $admin->id,
        'impersonated_user_id' => $target->id,
    ]);
    $session = [Impersonation::USER_KEY => $admin->id, Impersonation::LOG_KEY => $log->id];

    $this->actingAs($target)->withSession($session)->get(route('dashboard'))->assertOk();

    $this->actingAs($target)
        ->withSession($session)
        ->delete(route('user-impersonation.destroy'))
        ->assertRedirectToRoute('dashboard');

    $this->assertAuthenticatedAs($admin);
});

it('signs out and closes the log when the impersonator can no longer be loaded', function (Closure $break): void {
    $target = User::factory()->create();
    $admin = User::factory()->superAdmin()->create();
    $log = ImpersonationLog::factory()->create([
        'impersonator_user_id' => $admin->id,
        'impersonated_user_id' => $target->id,
    ]);
    $impersonatorId = $break($admin);

    $this->actingAs($target)
        ->withSession([Impersonation::USER_KEY => $impersonatorId, Impersonation::LOG_KEY => $log->id])
        ->delete(route('user-impersonation.destroy'))
        ->assertRedirectToRoute('login');

    $this->assertGuest();

    expect($log->fresh()?->ended_at)->not->toBeNull();
})->with([
    'soft-deleted' => fn (): Closure => function (User $admin): string {
        $admin->delete();

        return $admin->id;
    },
    'hard-deleted' => fn (): Closure => function (User $admin): string {
        $admin->forceDelete();

        return $admin->id;
    },
    'not a string' => fn (): Closure => fn (User $admin): int => 42,
]);

it('writes no login history when impersonation starts', function (): void {
    $target = User::factory()->create();
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)->post(route('user-impersonation.store', $target));

    expect(LoginHistory::query()->count())->toBe(0);
});

it('writes no login history when impersonation stops', function (): void {
    $target = User::factory()->create();
    $admin = User::factory()->superAdmin()->create();
    $log = ImpersonationLog::factory()->create([
        'impersonator_user_id' => $admin->id,
        'impersonated_user_id' => $target->id,
    ]);

    $this->actingAs($target)
        ->withSession([Impersonation::USER_KEY => $admin->id, Impersonation::LOG_KEY => $log->id])
        ->delete(route('user-impersonation.destroy'));

    $this->assertAuthenticatedAs($admin);

    expect(LoginHistory::query()->count())->toBe(0);
});

it('closes the impersonation log when the admin logs out while impersonating', function (): void {
    $target = User::factory()->create();
    $admin = User::factory()->superAdmin()->create();
    $log = ImpersonationLog::factory()->create([
        'impersonator_user_id' => $admin->id,
        'impersonated_user_id' => $target->id,
    ]);

    $this->actingAs($target)
        ->withSession([Impersonation::USER_KEY => $admin->id, Impersonation::LOG_KEY => $log->id])
        ->post(route('logout'))
        ->assertRedirect('/');

    $this->assertGuest();

    expect($log->refresh()->ended_at)->not->toBeNull();
});
