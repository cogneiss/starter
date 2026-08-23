<?php

declare(strict_types=1);

use App\Auth\Drivers\PasswordAuthDriver;
use Illuminate\Http\Request;

it('has no identity to authenticate outside the login form request', function (): void {
    expect(resolve(PasswordAuthDriver::class)->authenticate(Request::create('/login', 'POST')))
        ->toBeNull();
});
