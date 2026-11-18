<?php

declare(strict_types=1);

use App\Models\Activity;
use App\Models\ImpersonationLog;
use App\Models\Organization;
use App\Models\User;
use App\Support\Impersonation;

beforeEach(function (): void {
    config()->set('features.defaults.impersonation-enabled', true);
    config()->set('session.lifetime', 120);
    $this->freezeSecond();
});

function impersonating(User $target, User $admin): ImpersonationLog
{
    return ImpersonationLog::factory()->create([
        'impersonator_user_id' => $admin->id,
        'impersonated_user_id' => $target->id,
        'updated_at' => now()->subHour(),
    ]);
}

it('stamps the open log on every impersonated request', function (): void {
    $target = User::factory()->forOrganization(Organization::factory()->create())->create();
    $admin = User::factory()->superAdmin()->create();
    $log = impersonating($target, $admin);

    $this->actingAs($target)
        ->withSession([Impersonation::USER_KEY => $admin->id, Impersonation::LOG_KEY => $log->id])
        ->get(route('dashboard'));

    expect($log->fresh()->updated_at->equalTo(now()))->toBeTrue();
});

it('ends a log whose session expired at the time it was last used', function (): void {
    $lastUsed = now()->subMinutes(121)->startOfSecond();
    $stale = ImpersonationLog::factory()->create(['updated_at' => $lastUsed]);
    $open = ImpersonationLog::factory()->create(['updated_at' => now()->subMinutes(119)]);

    $this->artisan('impersonation:end-stale')->assertSuccessful();

    $entry = Activity::withoutOrganizationScope()
        ->where('subject_id', $stale->id)
        ->where('event', 'updated')
        ->sole();

    expect($stale->fresh()->ended_at->equalTo($lastUsed))->toBeTrue()
        ->and($open->fresh()->ended_at)->toBeNull()
        ->and($entry->organization_id)->toBeNull();
});

it('ends the impersonation when the target is deactivated mid-session', function (): void {
    $target = User::factory()->create(['is_active' => false]);
    $admin = User::factory()->superAdmin()->create();
    $log = impersonating($target, $admin);

    $this->actingAs($target)
        ->withSession([Impersonation::USER_KEY => $admin->id, Impersonation::LOG_KEY => $log->id])
        ->get(route('dashboard'))
        ->assertRedirectToRoute('dashboard')
        ->assertSessionMissing(Impersonation::USER_KEY);

    $this->assertAuthenticatedAs($admin);

    expect($log->fresh()->ended_at)->not->toBeNull();
});
