<?php

declare(strict_types=1);

use App\Enums\HealthStatus;
use App\Jobs\QueueHeartbeat;
use App\Support\Health\Check;
use App\Support\Health\Checks\CacheCheck;
use App\Support\Health\Checks\DatabaseCheck;
use App\Support\Health\Checks\DebugModeCheck;
use App\Support\Health\Checks\DiskCheck;
use App\Support\Health\Checks\QueueCheck;
use App\Support\Health\Checks\ScheduleCheck;
use App\Support\Health\HealthReport;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

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
        expect(new DatabaseCheck()->run())->toBe(HealthStatus::Ok);
    });

    it('fails when the connection cannot be opened', function (): void {
        withUnreachableDatabase(function (): void {
            expect(new DatabaseCheck()->run())->toBe(HealthStatus::Failed);
        });
    });
});

describe('cache check', function (): void {
    it('passes when a written value reads back', function (): void {
        expect(new CacheCheck()->run())->toBe(HealthStatus::Ok);
    });

    it('fails when the read value does not match', function (): void {
        Cache::shouldReceive('put')->once();
        Cache::shouldReceive('get')->once()->with('health:cache')->andReturn('stale');

        expect(new CacheCheck()->run())->toBe(HealthStatus::Failed);
    });

    it('fails when the store cannot be reached', function (): void {
        config()->set('cache.default', 'bogus');

        expect(new CacheCheck()->run())->toBe(HealthStatus::Failed);
    });
});

describe('queue check', function (): void {
    it('passes when a real heartbeat job round-trips through the queue', function (): void {
        expect(new QueueCheck()->run())->toBe(HealthStatus::Ok);
    });

    it('fails when the dispatched job is never processed', function (): void {
        Queue::fake();
        config()->set('health.queue_timeout_seconds', 0.0);

        expect(new QueueCheck()->run())->toBe(HealthStatus::Failed);

        Queue::assertPushed(QueueHeartbeat::class);
    });

    it('fails when the queue cannot be reached', function (): void {
        config()->set('queue.default', 'bogus');

        expect(new QueueCheck()->run())->toBe(HealthStatus::Failed);
    });
});

describe('schedule check', function (): void {
    it('degrades when the scheduler has never run', function (): void {
        expect(new ScheduleCheck()->run())->toBe(HealthStatus::Degraded);
    });

    it('passes on a fresh heartbeat', function (): void {
        Cache::put(ScheduleCheck::KEY, now()->getTimestamp());

        expect(new ScheduleCheck()->run())->toBe(HealthStatus::Ok);
    });

    it('degrades on a stale heartbeat', function (): void {
        Cache::put(ScheduleCheck::KEY, now()->subHour()->getTimestamp());

        expect(new ScheduleCheck()->run())->toBe(HealthStatus::Degraded);
    });

    it('is fed by a scheduled heartbeat', function (): void {
        $events = collect(resolve(Schedule::class)->events());

        expect($events->contains(fn ($event): bool => $event->description === 'health-heartbeat'))->toBeTrue();
    });
});

describe('disk check', function (): void {
    it('passes with headroom above the threshold', function (): void {
        expect(new DiskCheck()->run())->toBe(HealthStatus::Ok);
    });

    it('degrades below the threshold', function (): void {
        config()->set('health.disk_minimum_free_percent', 100.0);

        expect(new DiskCheck()->run())->toBe(HealthStatus::Degraded);
    });

    it('degrades when the path cannot be measured', function (): void {
        config()->set('health.disk_path', '/nonexistent/health-check-path');

        expect(new DiskCheck()->run())->toBe(HealthStatus::Degraded);
    });
});

describe('debug mode check', function (): void {
    it('passes outside production', function (): void {
        config()->set('app.debug', true);

        expect(new DebugModeCheck()->run())->toBe(HealthStatus::Ok);
    });

    it('passes in production with debug off', function (): void {
        config()->set('app.env', 'production');
        config()->set('app.debug', false);

        expect(new DebugModeCheck()->run())->toBe(HealthStatus::Ok);
    });

    it('degrades in production with debug on', function (): void {
        config()->set('app.env', 'production');
        config()->set('app.debug', true);

        expect(new DebugModeCheck()->run())->toBe(HealthStatus::Degraded);
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

        $throwing = new class($secret) implements Check
        {
            public function __construct(private readonly string $secret) {}

            public function name(): string
            {
                return 'throwing';
            }

            public function run(): HealthStatus
            {
                throw new RuntimeException($this->secret);
            }
        };

        $this->app->bind(HealthReport::class, fn (): HealthReport => new HealthReport([
            new DatabaseCheck(),
            $throwing,
        ]));

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
