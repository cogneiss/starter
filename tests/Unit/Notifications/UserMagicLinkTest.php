<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\UserMagicLink;

it('is delivered by mail', function (): void {
    $user = User::factory()->create();

    expect(new UserMagicLink('token')->via($user))->toBe(['mail']);
});

it('links to the consumption route', function (): void {
    $user = User::factory()->create();

    $mail = new UserMagicLink('token')->toMail($user);

    expect($mail->actionUrl)->toBe(route('magic-link.update', ['token' => 'token']));
});
