<?php

declare(strict_types=1);

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

it('shows the browser sessions and sign-ins of the current user', function (): void {
    config()->set('session.driver', 'database');

    $user = User::factory()->create();

    DB::table('sessions')->insert([
        'id' => 'a-session',
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/120.0 Safari/537.36',
        'payload' => '',
        'last_activity' => now()->getTimestamp(),
    ]);

    LoginHistory::factory()->create(['user_id' => $user->id, 'ip_address' => '127.0.0.1']);
    LoginHistory::factory()->create();

    $this->actingAs($user)
        ->get(route('browser-session.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('browser-session/show')
            ->has('sessions', 1)
            ->where('sessions.0.device', 'Chrome on macOS')
            ->has('logins', 1)
            ->where('logins.0.ip_address', '127.0.0.1')
        );
});

it('shows at most ten sign-ins', function (): void {
    $user = User::factory()->create();

    LoginHistory::factory()->count(12)->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('browser-session.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('logins', 10));
});

it('logs out other sessions when the password is confirmed', function (): void {
    config()->set('session.driver', 'database');

    $user = User::factory()->create(['password' => Hash::make('password')]);

    DB::table('sessions')->insert([
        [
            'id' => 'other-session',
            'user_id' => $user->id,
            'ip_address' => null,
            'user_agent' => null,
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ],
    ]);

    $this->actingAs($user)
        ->delete(route('browser-session.destroy'), ['password' => 'password'])
        ->assertRedirect();

    expect(DB::table('sessions')->where('id', 'other-session')->exists())->toBeFalse();

    $this->assertAuthenticatedAs($user);
});

it('refuses to log out other sessions without the right password', function (): void {
    config()->set('session.driver', 'database');

    $user = User::factory()->create(['password' => Hash::make('password')]);

    DB::table('sessions')->insert([
        [
            'id' => 'other-session',
            'user_id' => $user->id,
            'ip_address' => null,
            'user_agent' => null,
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ],
    ]);

    $this->actingAs($user)
        ->from(route('browser-session.show'))
        ->delete(route('browser-session.destroy'), ['password' => 'wrong-password'])
        ->assertSessionHasErrors('password');

    expect(DB::table('sessions')->where('id', 'other-session')->exists())->toBeTrue();
});
