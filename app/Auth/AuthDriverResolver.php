<?php

declare(strict_types=1);

namespace App\Auth;

use App\Auth\Contracts\AuthDriver;
use Illuminate\Support\Arr;
use InvalidArgumentException;

final readonly class AuthDriverResolver
{
    /**
     * Resolve a driver by key, or the configured default when none is given.
     * An unknown key is a configuration bug, so it throws rather than quietly
     * falling back to passwords.
     */
    public function driver(?string $key = null): AuthDriver
    {
        $key ??= config()->string('auth.default_driver');

        $class = Arr::string(config()->array('auth.drivers'), $key, '');

        $driver = is_a($class, AuthDriver::class, true) ? resolve($class) : null;

        throw_unless($driver instanceof AuthDriver, InvalidArgumentException::class, "Auth driver [{$key}] is not configured.");

        return $driver;
    }
}
