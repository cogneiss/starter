<?php

declare(strict_types=1);

use App\Actions\CreateSessionFromMagicLink;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

it('resolves the user and consumes the token', function (): void {
    $user = User::factory()->create();

    Cache::put('magic-link:token', $user->id, now()->addMinutes(15));

    $action = resolve(CreateSessionFromMagicLink::class);

    expect($action->handle('token')?->id)->toBe($user->id)
        ->and(Cache::has('magic-link:token'))->toBeFalse();
});

it('returns null for an unknown token', function (): void {
    $action = resolve(CreateSessionFromMagicLink::class);

    expect($action->handle('missing'))->toBeNull();
});

it('returns null when the user is gone', function (): void {
    Cache::put('magic-link:token', 'deleted-user-id', now()->addMinutes(15));

    $action = resolve(CreateSessionFromMagicLink::class);

    expect($action->handle('token'))->toBeNull();
});
