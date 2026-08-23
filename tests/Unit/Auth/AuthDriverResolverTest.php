<?php

declare(strict_types=1);

use App\Auth\AuthDriverResolver;
use App\Auth\Drivers\PasswordAuthDriver;

it('resolves the configured default driver', function (): void {
    expect(resolve(AuthDriverResolver::class)->driver())
        ->toBeInstanceOf(PasswordAuthDriver::class);
});

it('resolves a driver by key', function (): void {
    expect(resolve(AuthDriverResolver::class)->driver('password')->key())
        ->toBe('password');
});

it('throws on a driver key that is not configured', function (): void {
    expect(fn () => resolve(AuthDriverResolver::class)->driver('saml'))
        ->toThrow(InvalidArgumentException::class, 'Auth driver [saml] is not configured.');
});
