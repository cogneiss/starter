<?php

declare(strict_types=1);

use App\Models\LoginHistory;
use App\Models\User;

it('belongs to the user who signed in', function (): void {
    $user = User::factory()->create();

    $login = LoginHistory::factory()->create(['user_id' => $user->id]);

    expect($login->user?->is($user))->toBeTrue();
});

it('has no user when the address belongs to nobody', function (): void {
    $login = LoginHistory::factory()->create(['user_id' => null]);

    expect($login->user)->toBeNull();
});
