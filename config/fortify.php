<?php

declare(strict_types=1);

use Laravel\Fortify\Features;

/**
 * Only the values that differ from Fortify's defaults. Fortify merges its own
 * config underneath this file one level deep, so each key here replaces the
 * whole default array of the same name.
 */
return [
    'lowercase_usernames' => true,

    'home' => '/dashboard',

    /**
     * Named limiters defined in app/Providers/FortifyServiceProvider.php.
     */
    'limiters' => [
        'login' => 'login',
        'two-factor' => 'two-factor',
        'passkeys' => 'passkeys',
    ],

    /**
     * Registration, password reset, email verification, profile and password
     * updates are the kit's own routes and controllers, so Fortify only serves
     * two-factor authentication and passkeys.
     */
    'features' => [
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]),
        Features::passkeys([
            'confirmPassword' => true,
        ]),
    ],
];
