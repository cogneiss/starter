<?php

declare(strict_types=1);

use App\Models\ImpersonationLog;
use App\Models\User;

it('belongs to the impersonator and the impersonated user', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $target = User::factory()->create();

    $log = ImpersonationLog::factory()->create([
        'impersonator_user_id' => $admin->id,
        'impersonated_user_id' => $target->id,
    ]);

    expect($log->impersonator->is($admin))->toBeTrue()
        ->and($log->impersonated->is($target))->toBeTrue()
        ->and($log->started_at)->not->toBeNull();
});
