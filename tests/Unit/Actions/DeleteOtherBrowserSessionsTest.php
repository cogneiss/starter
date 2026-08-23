<?php

declare(strict_types=1);

use App\Actions\DeleteOtherBrowserSessions;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

it('leaves the current session alone and removes the others', function (): void {
    config()->set('session.driver', 'database');

    $user = User::factory()->create(['password' => Hash::make('password')]);

    $this->actingAs($user);

    DB::table('sessions')->insert([
        [
            'id' => 'current-session',
            'user_id' => $user->id,
            'ip_address' => null,
            'user_agent' => null,
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ],
        [
            'id' => 'other-session',
            'user_id' => $user->id,
            'ip_address' => null,
            'user_agent' => null,
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ],
    ]);

    resolve(DeleteOtherBrowserSessions::class)->handle($user, 'password', 'current-session');

    expect(DB::table('sessions')->pluck('id')->all())->toBe(['current-session']);
});

it('touches no rows while sessions are not stored in the database', function (): void {
    config()->set('session.driver', 'array');

    $user = User::factory()->create(['password' => Hash::make('password')]);

    $this->actingAs($user);

    DB::table('sessions')->insert([
        'id' => 'other-session',
        'user_id' => $user->id,
        'ip_address' => null,
        'user_agent' => null,
        'payload' => '',
        'last_activity' => now()->getTimestamp(),
    ]);

    resolve(DeleteOtherBrowserSessions::class)->handle($user, 'password', 'current-session');

    expect(DB::table('sessions')->count())->toBe(1);
});
