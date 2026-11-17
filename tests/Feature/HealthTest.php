<?php

declare(strict_types=1);

use App\Jobs\QueueHeartbeat;
use App\Support\Health\Checks\DatabaseCheck;
use App\Support\Health\Checks\DebugModeCheck;
use App\Support\Health\Checks\DiskCheck;
use App\Support\Health\Checks\QueueCheck;
use App\Support\Health\Checks\ScheduleCheck;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Checks\CacheCheck;
use Spatie\Health\Checks\Result;
use Spatie\Health\Health;
use Spatie\Health\ResultStores\InMemoryHealthResultStore;

/**
 * Points the default connection at a configured but unreachable database for
 * the duration of the callback, and restores it before the test's transaction
 * teardown needs the real one back.
 */
function withUnreachableDatabase(Closure $callback): void
{
    $original = config()->string('database.default');

    config()->set('database.connections.unreachable', [
        'driver' => 'pgsql',
        'host' => '127.0.0.1',
        'port' => 1,
        'database' => 'nope',
        'username' => 'nope',
        'password' => 'nope',
    ]);
    config()->set('database.default', 'unreachable');

    try {
        $callback();
    } finally {
        config()->set('database.default', $original);
        DB::purge('unreachable');
    }
}

describe('database check', function (): void {
    it('passes against the working test database', function (): void {
        expect(DatabaseCheck::new()->run()->status->value)->toBe('ok');
    });

    it('fails when the connection cannot be opened', function (): void {
        withUnreachableDatabase(function (): void {
            expect(DatabaseCheck::new()->run()->status->value)->toBe('failed');
        });
    });

    it('runs a query, because an open connection proves nothing behind a pooler', function (): void {
        DB::enableQueryLog();

        DatabaseCheck::new()->run();

        expect(array_column(DB::getQueryLog(), 'query'))->toContain('select 1');
    });

    it('reports a failure in fixed words, never the driver error', function (): void {
        withUnreachableDatabase(function (): void {
            expect(DatabaseCheck::new()->run()->notificationMessage)->toBe('The database did not answer a query.');
        });
    });
});

describe('cache check', function (): void {
    it('passes when a written value reads back', function (): void {
        expect(CacheCheck::new()->run()->status->value)->toBe('ok');
    });

    it('fails when the read value does not match', function (): void {
        $store = Mockery::mock(Repository::class);
        $store->shouldReceive('put')->once();
        $store->shouldReceive('get')->once()->andReturn('stale');
        $store->shouldReceive('forget')->once();

        Cache::shouldReceive('driver')->andReturn($store);

        expect(CacheCheck::new()->run()->status->value)->toBe('failed');
    });

    it('fails when the store cannot be reached', function (): void {
        config()->set('cache.default', 'bogus');

        expect(CacheCheck::new()->run()->status->value)->toBe('failed');
    });
});

describe('queue check', function (): void {
    it('passes when a real heartbeat job round-trips through the queue', function (): void {
        expect(QueueCheck::new()->run()->status->value)->toBe('ok');
    });

    it('fails when the dispatched job is never processed', function (): void {
        Queue::fake();
        config()->set('health.queue_timeout_seconds', 0.0);

        expect(QueueCheck::new()->run()->status->value)->toBe('failed');

        Queue::assertPushed(QueueHeartbeat::class);
    });

    it('fails when the queue cannot be reached', function (): void {
        config()->set('queue.default', 'bogus');

        expect(QueueCheck::new()->run()->status->value)->toBe('failed');
    });
});

describe('schedule check', function (): void {
    it('degrades when the scheduler has never run', function (): void {
        expect(ScheduleCheck::new()->run()->status->value)->toBe('warning');
    });

    it('passes on a fresh heartbeat', function (): void {
        Cache::put(ScheduleCheck::KEY, now()->getTimestamp());

        expect(ScheduleCheck::new()->run()->status->value)->toBe('ok');
    });

    it('degrades on a stale heartbeat', function (): void {
        Cache::put(ScheduleCheck::KEY, now()->subHour()->getTimestamp());

        expect(ScheduleCheck::new()->run()->status->value)->toBe('warning');
    });

    it('is fed by a scheduled heartbeat', function (): void {
        $events = collect(resolve(Schedule::class)->events());

        expect($events->contains(fn ($event): bool => $event->description === 'health-heartbeat'))->toBeTrue();
    });
});

describe('disk check', function (): void {
    it('passes with headroom above the threshold', function (): void {
        expect(DiskCheck::new()->run()->status->value)->toBe('ok');
    });

    it('degrades below the threshold', function (): void {
        config()->set('health.disk_minimum_free_percent', 100.0);

        expect(DiskCheck::new()->run()->status->value)->toBe('warning');
    });

    it('degrades when the path cannot be measured', function (): void {
        config()->set('health.disk_path', '/nonexistent/health-check-path');

        expect(DiskCheck::new()->run()->status->value)->toBe('warning');
    });
});

describe('debug mode check', function (): void {
    it('passes outside production', function (): void {
        config()->set('app.debug', true);

        expect(DebugModeCheck::new()->run()->status->value)->toBe('ok');
    });

    it('passes in production with debug off', function (): void {
        config()->set('app.env', 'production');
        config()->set('app.debug', false);

        expect(DebugModeCheck::new()->run()->status->value)->toBe('ok');
    });

    it('degrades in production with debug on', function (): void {
        config()->set('app.env', 'production');
        config()->set('app.debug', true);

        expect(DebugModeCheck::new()->run()->status->value)->toBe('warning');
    });
});

describe('the endpoint', function (): void {
    it('answers ok, unauthenticated, when everything is healthy', function (): void {
        Cache::put(ScheduleCheck::KEY, now()->getTimestamp());

        $response = $this->getJson('/health')->assertOk();

        expect($response->json('status'))->toBe('ok')
            ->and($response->json('checks'))->toHaveCount(6)
            ->and(collect($response->json('checks'))->pluck('name')->all())
            ->toBe(['database', 'cache', 'queue', 'schedule', 'disk', 'debug-mode']);

        foreach ($response->json('checks') as $check) {
            expect(array_keys($check))->toBe(['name', 'status', 'duration_ms']);
        }
    });

    it('answers 200 with a degraded status when a soft check slips', function (): void {
        // No schedule heartbeat has ever run in the array cache.
        $response = $this->getJson('/health')->assertOk();

        expect($response->json('status'))->toBe('degraded');
    });

    it('answers 503 on a hard failure', function (): void {
        withUnreachableDatabase(function (): void {
            $this->getJson('/health')->assertServiceUnavailable()->assertJsonPath('status', 'failed');
        });
    });

    it('marks a check that throws as failed without leaking anything', function (): void {
        $secret = sprintf('boom with key %s', config('app.key'));

        $throwing = new class($secret) extends Check
        {
            protected ?string $name = 'throwing';

            public function __construct(private readonly string $secret) {}

            public function run(): Result
            {
                throw new RuntimeException($this->secret);
            }
        };

        resolve(Health::class)->clearChecks()->checks([
            DatabaseCheck::new(),
            $throwing,
        ]);

        $response = $this->getJson('/health')->assertServiceUnavailable();

        expect($response->json('status'))->toBe('failed')
            ->and($response->json('checks.1'))->toBe([
                'name' => 'throwing',
                'status' => 'failed',
                'duration_ms' => $response->json('checks.1.duration_ms'),
            ]);

        $body = $response->getContent();

        $forbidden = array_filter([
            config()->string('app.key'),
            (string) config(sprintf('database.connections.%s.password', config()->string('database.default'))),
            app()->version(),
            $secret,
            'RuntimeException',
        ], fn (string $value): bool => $value !== '');

        foreach ($forbidden as $value) {
            expect($body)->not->toContain($value);
        }
    });
});

describe('the package config', function (): void {
    it('stores results in memory and sends no notifications, so no table or channel is needed', function (): void {
        expect(array_keys(config()->array('health.result_stores')))->toBe([0])
            ->and(config('health.result_stores.0'))->toBe(InMemoryHealthResultStore::class)
            ->and(config('health.notifications.enabled'))->toBeFalse();
    });
});
