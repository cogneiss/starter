<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Passkeys\Passkey;

test('to array', function (): void {
    $user = User::factory()->create()->refresh();

    expect(array_keys($user->toArray()))
        ->toBe([
            'id',
            'name',
            'email',
            'email_verified_at',
            'two_factor_confirmed_at',
            'created_at',
            'updated_at',
            'current_organization_id',
            'is_active',
        ]);
});

test('implements the passkey user contract', function (): void {
    $user = User::factory()->create();

    expect($user)->toBeInstanceOf(PasskeyUser::class)
        ->and($user->passkeys())->toBeInstanceOf(HasMany::class)
        ->and($user->hasPasskeysEnabled())->toBeFalse();
});

test('reports passkeys enabled once one is registered', function (): void {
    $user = User::factory()->create();

    $user->passkeys()->create([
        'name' => 'MacBook Pro',
        'credential_id' => 'credential-id-1',
        'credential' => ['aaguid' => 'unknown'],
    ]);

    expect($user->hasPasskeysEnabled())->toBeTrue()
        ->and($user->passkeys()->first())->toBeInstanceOf(Passkey::class);
});
