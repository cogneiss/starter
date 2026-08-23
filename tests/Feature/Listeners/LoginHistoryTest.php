<?php

declare(strict_types=1);

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Hash;

it('records a successful sign-in', function (): void {
    $user = User::factory()->withoutTwoFactor()->create([
        'email' => 'ada@example.com',
        'password' => Hash::make('password'),
    ]);

    $this->post(route('login.store'), [
        'email' => 'ada@example.com',
        'password' => 'password',
    ]);

    $login = LoginHistory::query()->sole();

    expect($login->user_id)->toBe($user->id)
        ->and($login->email)->toBe('ada@example.com')
        ->and($login->successful)->toBeTrue();
});

it('records a failed sign-in on an address nobody owns', function (): void {
    $this->post(route('login.store'), [
        'email' => 'nobody@example.com',
        'password' => 'password',
    ]);

    $login = LoginHistory::query()->sole();

    expect($login->user_id)->toBeNull()
        ->and($login->email)->toBe('nobody@example.com')
        ->and($login->successful)->toBeFalse();
});

it('records a failed sign-in against a known account', function (): void {
    $user = User::factory()->create([
        'email' => 'ada@example.com',
        'password' => Hash::make('password'),
    ]);

    $this->post(route('login.store'), [
        'email' => 'ada@example.com',
        'password' => 'wrong-password',
    ]);

    $login = LoginHistory::query()->sole();

    expect($login->user_id)->toBe($user->id)
        ->and($login->successful)->toBeFalse();
});

it('prunes sign-ins older than ninety days', function (): void {
    LoginHistory::factory()->create(['created_at' => now()->subDays(91)]);
    LoginHistory::factory()->create(['created_at' => now()->subDays(89)]);

    $this->artisan('model:prune', ['--model' => [LoginHistory::class]])->assertSuccessful();

    expect(LoginHistory::query()->count())->toBe(1);
});

it('ignores a sign-in by something that is not a user of this application', function (): void {
    event(new Login('web', new GenericUser(['id' => 'not-a-user']), false));

    expect(LoginHistory::query()->count())->toBe(0);
});
