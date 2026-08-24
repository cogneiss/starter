<?php

declare(strict_types=1);

use App\Support\AiAvailability;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Ai;
use Laravel\Ai\AnonymousAgent;

use function Laravel\Ai\agent;

/**
 * The promise of phase 2: a checkout with no provider keys is a working
 * checkout. The suite itself runs that way — every provider key is blanked in
 * phpunit.xml — so these assertions describe the state a fresh clone is in.
 */
it('reports no configured provider when every key is blank', function (): void {
    expect(AiAvailability::providers())->toBe([])
        ->and(AiAvailability::configured())->toBeFalse()
        ->and(AiAvailability::faked())->toBeTrue();
});

it('reports a configured provider once a key is set', function (): void {
    config()->set('ai.providers.anthropic.key', 'a-key');

    expect(AiAvailability::configured())->toBeTrue()
        ->and(AiAvailability::providers())->toContain('anthropic')
        ->and(AiAvailability::faked())->toBeFalse();
});

it('stays faked on a configured machine when AI_FAKE is on', function (): void {
    config()->set('ai.providers.anthropic.key', 'a-key');
    config()->set('ai.fake', true);

    expect(AiAvailability::configured())->toBeTrue()
        ->and(AiAvailability::faked())->toBeTrue();
});

it('falls back to the cheap tier when none is configured', function (): void {
    config()->set('ai.default_tier', null);

    expect(AiAvailability::defaultTier())->toBe('cheap');
});

it('answers a prompt from the fake gateway with zero keys', function (): void {
    // Asserted before prompting: without the fake registered this would reach a
    // provider, and the blocking suite never makes a real provider call.
    expect(Ai::hasFakeGatewayFor(AnonymousAgent::class))->toBeTrue();

    $response = agent(instructions: 'You are a test fixture.')->prompt('Say hello.');

    expect($response->text)->toBeString()->not->toBeEmpty();
});

// What `app:doctor` reports about the AI layer is asserted in
// tests/Feature/Console/DoctorCommandTest.php, next to the rest of the command.

it('creates the vector extension on postgresql', function (): void {
    expect(Artisan::call('ai:install'))->toBe(0)
        ->and(DB::selectOne("select 1 as ok from pg_extension where extname = 'vector'"))->not->toBeNull();
})->group('pgvector');

it('does nothing on a connection that cannot carry pgvector', function (): void {
    // Left out of the way of the transaction this test is already running in,
    // and put back before the assertions so a failure cannot strand it.
    $default = config('database.default');

    config()->set('database.connections.vectorless', ['driver' => 'sqlite', 'database' => ':memory:']);
    config()->set('database.default', 'vectorless');

    $statements = [];
    DB::listen(function (QueryExecuted $query) use (&$statements): void {
        $statements[] = $query->sql;
    });

    $exit = Artisan::call('ai:install');

    config()->set('database.default', $default);

    expect($exit)->toBe(0)
        ->and($statements)->toBe([]);
});
