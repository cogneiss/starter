<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\Contracts\OrganizationResolver;
use App\Auth\Resolvers\SessionOrganizationResolver;
use App\Auth\Resolvers\SingleOrganizationResolver;
use App\Auth\Resolvers\SubdomainOrganizationResolver;
use App\Contracts\AnalyticsReporter;
use App\Contracts\ErrorReporter;
use App\Contracts\FileScanner;
use App\Enums\KnownFeatures;
use App\Listeners\DispatchWebhookEvents;
use App\Listeners\RecordAnalyticsEvent;
use App\Listeners\RecordModelActivity;
use App\Models\ApiToken;
use App\Models\Organization;
use App\Resources\ResourceRegistry;
use App\Support\Analytics\NullAnalyticsReporter;
use App\Support\Analytics\PostHogReporter;
use App\Support\Health\Checks\CacheCheck;
use App\Support\Health\Checks\DatabaseCheck;
use App\Support\Health\Checks\DebugModeCheck;
use App\Support\Health\Checks\DiskCheck;
use App\Support\Health\Checks\QueueCheck;
use App\Support\Health\Checks\ScheduleCheck;
use App\Support\Health\HealthReport;
use App\Support\OrganizationContext;
use App\Support\OrganizationDatabaseChannel;
use App\Support\Reporting\NullErrorReporter;
use App\Support\Reporting\SentryErrorReporter;
use App\Support\Scanners\NullScanner;
use App\Webhooks\NativeHostnameResolver;
use App\Webhooks\ResolvesHostnames;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;
use Laravel\Sanctum\Sanctum;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * @var array<string, class-string<OrganizationResolver>>
     */
    private const array RESOLVERS = [
        'session' => SessionOrganizationResolver::class,
        'subdomain' => SubdomainOrganizationResolver::class,
        'single' => SingleOrganizationResolver::class,
    ];

    public function register(): void
    {
        $this->app->singleton(OrganizationContext::class);

        $scanner = config()->array('uploads.scanners')[config()->string('uploads.scanner')] ?? NullScanner::class;

        $this->app->bind(FileScanner::class, is_string($scanner) ? $scanner : NullScanner::class);

        $this->app->singleton(ResourceRegistry::class);

        $this->app->bind(ResolvesHostnames::class, NativeHostnameResolver::class);

        $this->app->bind(
            OrganizationResolver::class,
            self::RESOLVERS[config()->string('organizations.resolver')] ?? SessionOrganizationResolver::class,
        );

        // Null unless a DSN is configured, so a clone with blank keys boots
        // clean and sends nothing anywhere.
        $this->app->singleton(ErrorReporter::class, fn (): ErrorReporter => config('services.sentry.dsn') === null
            ? new NullErrorReporter()
            : $this->app->make(SentryErrorReporter::class));

        // Same shape as the error reporter: null unless a key is configured.
        $this->app->singleton(AnalyticsReporter::class, fn (): AnalyticsReporter => config('services.posthog.key') === null
            ? new NullAnalyticsReporter()
            : new PostHogReporter());

        $this->app->bind(HealthReport::class, fn (): HealthReport => new HealthReport([
            new DatabaseCheck(),
            new CacheCheck(),
            new QueueCheck(),
            new ScheduleCheck(),
            new DiskCheck(),
            new DebugModeCheck(),
        ]));
    }

    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(ApiToken::class);

        Event::listen(
            ['eloquent.created: *', 'eloquent.updated: *', 'eloquent.deleted: *'],
            RecordModelActivity::class,
        );

        Event::listen(
            ['eloquent.created: *', 'eloquent.updated: *', 'eloquent.deleted: *'],
            RecordAnalyticsEvent::class,
        );

        Event::listen(
            ['eloquent.created: *', 'eloquent.updated: *', 'eloquent.deleted: *'],
            DispatchWebhookEvents::class,
        );

        // Every stored notification carries the organization it was raised in.
        // Laravel resolves the `database` driver through the container, so the
        // stamping channel replaces it there rather than at each call site.
        Notification::resolved(function (ChannelManager $manager): void {
            $manager->extend('database', fn (): OrganizationDatabaseChannel => new OrganizationDatabaseChannel(
                resolve(OrganizationContext::class),
            ));
        });

        $this->optimizes(optimize: 'resource:cache', clear: 'resource:clear', key: 'resources');

        Feature::resolveScopeUsing(fn (): ?Organization => resolve(OrganizationContext::class)->get());

        foreach (KnownFeatures::cases() as $feature) {
            Feature::define(
                $feature->value,
                fn (?Organization $organization): bool => $feature->enabledFor($organization),
            );
        }

        // A plan's API tier is a string feature, so a billing integration can
        // move an organization between tiers without a deploy.
        Feature::define(
            KnownFeatures::API_RATE_TIER,
            fn (?Organization $organization): string => config()->string('api.rate_tiers.default'),
        );
    }
}
