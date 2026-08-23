<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\BrowserSession;
use Illuminate\Support\Facades\DB;

it('labels a user agent', function (?string $agent, string $device): void {
    expect(BrowserSession::device($agent))->toBe($device);
})->with([
    ['Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 Chrome/120.0 Safari/537.36 Edg/120.0', 'Edge on Windows'],
    ['Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/120.0 Safari/537.36 OPR/106.0', 'Opera on Linux'],
    ['Mozilla/5.0 (Android 14; Mobile) Gecko/120.0 Firefox/120.0', 'Firefox on Android'],
    ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120.0 Safari/537.36', 'Chrome on macOS'],
    ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Safari/604.1', 'Safari on iOS'],
    ['Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Safari/604.1', 'Safari on iOS'],
    [null, 'Unknown browser on unknown platform'],
]);

it('lists the sessions belonging to the user, newest first', function (): void {
    config()->set('session.driver', 'database');

    $user = User::factory()->create();
    $other = User::factory()->create();

    DB::table('sessions')->insert([
        [
            'id' => 'current-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Chrome/120.0 Safari/537.36',
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ],
        [
            'id' => 'older-session',
            'user_id' => $user->id,
            'ip_address' => null,
            'user_agent' => null,
            'payload' => '',
            'last_activity' => now()->subHour()->getTimestamp(),
        ],
        [
            'id' => 'someone-else',
            'user_id' => $other->id,
            'ip_address' => '10.0.0.1',
            'user_agent' => null,
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ],
    ]);

    $sessions = BrowserSession::forUser($user, 'current-session');

    expect($sessions)->toHaveCount(2)
        ->and($sessions[0]['id'])->toBe('current-session')
        ->and($sessions[0]['device'])->toBe('Chrome on macOS')
        ->and($sessions[0]['current'])->toBeTrue()
        ->and($sessions[1]['id'])->toBe('older-session')
        ->and($sessions[1]['ip_address'])->toBeNull()
        ->and($sessions[1]['current'])->toBeFalse();
});

it('lists nothing while sessions are not stored in the database', function (): void {
    config()->set('session.driver', 'array');

    expect(BrowserSession::forUser(User::factory()->create(), 'current-session'))->toBe([]);
});
