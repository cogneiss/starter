<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\Contracts\OrganizationResolver;
use App\Auth\Resolvers\SessionOrganizationResolver;
use App\Auth\Resolvers\SingleOrganizationResolver;
use App\Auth\Resolvers\SubdomainOrganizationResolver;
use App\Enums\KnownFeatures;
use App\Models\Organization;
use App\Resources\ResourceRegistry;
use App\Support\OrganizationContext;
use App\Support\OrganizationDatabaseChannel;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;

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
        $this->app->singleton(ResourceRegistry::class);

        $this->app->bind(
            OrganizationResolver::class,
            self::RESOLVERS[config()->string('organizations.resolver')] ?? SessionOrganizationResolver::class,
        );
    }

    public function boot(): void
    {
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
    }
}
