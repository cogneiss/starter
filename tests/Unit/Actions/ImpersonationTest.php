<?php

declare(strict_types=1);

use App\Actions\StartImpersonation;
use App\Actions\StopImpersonation;
use App\Models\ImpersonationLog;
use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Auth\Access\AuthorizationException;

it('refuses to impersonate another member of the platform staff', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $target = User::factory()->superAdmin()->create();

    expect(fn () => resolve(StartImpersonation::class)->handle($admin, $target))
        ->toThrow(AuthorizationException::class);
});

it('refuses to impersonate yourself', function (): void {
    $admin = User::factory()->superAdmin()->create();

    expect(fn () => resolve(StartImpersonation::class)->handle($admin, $admin))
        ->toThrow(AuthorizationException::class);
});

it('refuses to nest impersonations', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $target = User::factory()->create();

    session()->put(Impersonation::USER_KEY, $admin->id);

    expect(fn () => resolve(StartImpersonation::class)->handle($admin, $target))
        ->toThrow(AuthorizationException::class);
});

it('leaves a session with nothing to restore alone', function (): void {
    expect(resolve(StopImpersonation::class)->handle())->toBeNull();
});

it('does not stamp an audit row that is already closed', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $log = ImpersonationLog::factory()->create([
        'impersonator_user_id' => $admin->id,
        'ended_at' => now()->subHour(),
    ]);

    session()->put(Impersonation::USER_KEY, $admin->id);
    session()->put(Impersonation::LOG_KEY, $log->id);

    expect(resolve(StopImpersonation::class)->handle()?->id)->toBe($admin->id)
        ->and($log->fresh()?->ended_at?->toDateTimeString())->toBe(now()->subHour()->toDateTimeString());
});

it('reports no impersonator or log for a clean session', function (): void {
    $impersonation = resolve(Impersonation::class);

    expect($impersonation->active())->toBeFalse()
        ->and($impersonation->impersonator())->toBeNull()
        ->and($impersonation->log())->toBeNull();
});
