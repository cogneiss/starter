<?php

declare(strict_types=1);

use App\Contracts\ErrorReporter;
use App\Models\Organization;
use App\Providers\AppServiceProvider;
use App\Resources\ResourceRegistry;
use App\Support\Reporting\NullErrorReporter;
use App\Support\Reporting\SentryErrorReporter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Sentry\ClientBuilder;
use Sentry\State\HubInterface;
use Sentry\State\Scope;

describe('the binding', function (): void {
    it('defaults to the null reporter when no DSN is configured', function (): void {
        expect(config('services.sentry.dsn'))->toBeNull()
            ->and(resolve(ErrorReporter::class))->toBeInstanceOf(NullErrorReporter::class)
            ->and(resolve(ErrorReporter::class))->toBe(resolve(ErrorReporter::class));
    });

    it('switches to the sentry reporter when a DSN is configured', function (): void {
        config()->set('services.sentry.dsn', 'https://examplePublicKey@o0.ingest.sentry.io/0');

        $this->mock(HubInterface::class);
        $this->app->forgetInstance(ErrorReporter::class);

        expect(resolve(ErrorReporter::class))->toBeInstanceOf(SentryErrorReporter::class);
    });
});

describe('the release', function (): void {
    /**
     * Load config/services.php fresh with the given Sentry variables, then put
     * the environment back.
     *
     * @param  array<string, string>  $env
     * @return array{dsn: string|null, release: string|null}
     */
    function sentryServices(array $env): array
    {
        $original = array_intersect_key($_SERVER, $env);
        $_SERVER = [...$_SERVER, ...$env];

        try {
            /** @var array{sentry: array{dsn: string|null, release: string|null}} $services */
            $services = require base_path('config/services.php');

            return $services['sentry'];
        } finally {
            $_SERVER = [...array_diff_key($_SERVER, $env), ...$original];
        }
    }

    it('names the checked-out commit when a DSN is set without a release', function (): void {
        $sentry = sentryServices(['SENTRY_DSN' => 'https://examplePublicKey@o0.ingest.sentry.io/0', 'SENTRY_RELEASE' => '']);

        expect($sentry['release'])->toMatch('/^[0-9a-f]{40}$/');
    });

    it('prefers an explicit release over the commit', function (): void {
        $sentry = sentryServices(['SENTRY_DSN' => 'https://examplePublicKey@o0.ingest.sentry.io/0', 'SENTRY_RELEASE' => 'v1.2.3']);

        expect($sentry['release'])->toBe('v1.2.3');
    });

    it('stays empty with no DSN, so a keyless clone never runs git', function (): void {
        expect(sentryServices(['SENTRY_DSN' => '', 'SENTRY_RELEASE' => ''])['release'])->toBeNull();
    });

    it('hands the resolved release to the SDK', function (): void {
        config()->set('services.sentry.release', 'abc123');

        new AppServiceProvider($this->app)->register();

        expect(resolve(ClientBuilder::class)->getOptions()->getRelease())->toBe('abc123');
    });
});

describe('the null reporter', function (): void {
    it('mints one reference per throwable and remembers it', function (): void {
        $reporter = new NullErrorReporter();
        $throwable = new RuntimeException('kaboom');

        expect($reporter->reference($throwable))->toBeNull();

        $reference = $reporter->report($throwable);

        expect($reporter->report($throwable))->toBe($reference)
            ->and($reporter->reference($throwable))->toBe($reference)
            ->and($reporter->reference(new RuntimeException('other')))->toBeNull();
    });

    it('shares one request id across reports in the same request', function (): void {
        $reporter = new NullErrorReporter();

        $reporter->report(new RuntimeException('one'));
        $reporter->report(new RuntimeException('two'));

        [$first, $second] = $reporter->reports;

        expect($first['context']['request_id'])->toBe($second['context']['request_id'])
            ->and($first['reference'])->not->toBe($second['reference']);
    });
});

describe('the sentry reporter', function (): void {
    it('forwards the reference and identifier context to the hub', function (): void {
        config()->set('services.sentry.release', 'v1.2.3');

        $throwable = new RuntimeException('kaboom');
        $tags = [];
        $contexts = [];

        $scope = Mockery::mock(Scope::class);
        $scope->shouldReceive('setTag')->twice()->andReturnUsing(function (string $key, string $value) use (&$tags, $scope) {
            $tags[$key] = $value;

            return $scope;
        });
        $scope->shouldReceive('setContext')->once()->andReturnUsing(function (string $name, array $context) use (&$contexts, $scope) {
            $contexts[$name] = $context;

            return $scope;
        });

        $hub = Mockery::mock(HubInterface::class);
        $hub->shouldReceive('configureScope')->once()->andReturnUsing(function (callable $callback) use ($scope): void {
            $callback($scope);
        });
        $hub->shouldReceive('captureException')->once()->with($throwable);

        $reporter = new SentryErrorReporter($hub);
        $reference = $reporter->report($throwable);

        expect($tags)->toBe(['reference' => $reference, 'release' => 'v1.2.3'])
            ->and($contexts['app'])->toHaveKeys(['organization_id', 'user_id', 'request_id', 'release'])
            ->and($contexts['app']['release'])->toBe('v1.2.3');
    });

    it('skips the release tag when none is configured', function (): void {
        $scope = Mockery::mock(Scope::class);
        $scope->shouldReceive('setTag')->once()->with('reference', Mockery::type('string'))->andReturn($scope);
        $scope->shouldReceive('setContext')->once()->andReturn($scope);

        $hub = Mockery::mock(HubInterface::class);
        $hub->shouldReceive('configureScope')->once()->andReturnUsing(function (callable $callback) use ($scope): void {
            $callback($scope);
        });
        $hub->shouldReceive('captureException')->once();

        new SentryErrorReporter($hub)->report(new RuntimeException('kaboom'));
    });
});

describe('the 500 payload', function (): void {
    it('carries the same reference the reporter recorded, with identifier-only context', function (): void {
        config()->set('services.sentry.release', 'v9.9.9');

        $organization = Organization::factory()->create();
        [, $bearer] = apiBearer($organization);

        // The registry is final, so a proxy partial around the real instance
        // stands in for it; the catalogue resolves it out of the container.
        $registry = Mockery::mock(resolve(ResourceRegistry::class));
        $registry->shouldReceive('all')->andThrow(new RuntimeException('kaboom'));
        $this->app->instance(ResourceRegistry::class, $registry);

        $response = $this->withHeader('Authorization', $bearer)->getJson('/api/v1')->assertStatus(500);

        $reporter = resolve(ErrorReporter::class);

        expect($reporter)->toBeInstanceOf(NullErrorReporter::class)
            ->and($reporter->reports)->toHaveCount(1);

        $report = $reporter->reports[0];

        expect($response->json())->toBe([
            'message' => 'Something went wrong at our end. The error has been recorded.',
            'reference' => $report['reference'],
        ])
            ->and($report['context'])->toBe([
                'organization_id' => $organization->id,
                'user_id' => $report['context']['user_id'],
                'request_id' => $report['context']['request_id'],
                'release' => 'v9.9.9',
            ])
            ->and($report['context']['user_id'])->not->toBeNull()
            ->and(json_encode($report['context']))->not->toContain(Str::after($bearer, 'Bearer '));
    });

    it('carries a null reference when nothing was reported', function (): void {
        Route::get('/boom-json', fn () => abort(500));

        $response = $this->getJson('/boom-json')->assertStatus(500);

        expect($response->json('reference'))->toBeNull()
            ->and(resolve(ErrorReporter::class)->reports)->toBe([]);
    });
});
