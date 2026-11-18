<?php

declare(strict_types=1);

use App\Models\ImpersonationLog;
use App\Models\User;
use App\Support\Impersonation;

beforeEach(function (): void {
    config()->set('features.defaults.impersonation-enabled', true);
});

it('refuses to impersonate a deactivated user', function (): void {
    $target = User::factory()->create(['is_active' => false]);
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->post(route('user-impersonation.store', $target))
        ->assertForbidden()
        ->assertSessionMissing(Impersonation::USER_KEY);

    $this->assertAuthenticatedAs($admin);

    expect(ImpersonationLog::query()->exists())->toBeFalse();
});
