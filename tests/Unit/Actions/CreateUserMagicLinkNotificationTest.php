<?php

declare(strict_types=1);

use App\Actions\CreateUserMagicLinkNotification;
use App\Models\User;
use App\Notifications\UserMagicLink;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

it('may send a magic link notification', function (): void {
    Notification::fake();
    Str::createRandomStringsUsing(fn (): string => 'fixed-token');

    $user = User::factory()->create([
        'email' => 'test@example.com',
    ]);

    $action = resolve(CreateUserMagicLinkNotification::class);

    $action->handle('test@example.com');

    expect(Cache::get('magic-link:fixed-token'))->toBe($user->id);

    Notification::assertSentTo($user, UserMagicLink::class);
});

it('does nothing for a non-existent email', function (): void {
    Notification::fake();

    $action = resolve(CreateUserMagicLinkNotification::class);

    $action->handle('nonexistent@example.com');

    Notification::assertNothingSent();
});
