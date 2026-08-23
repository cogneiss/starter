<?php

declare(strict_types=1);

namespace App\Providers;

use App\Auth\Contracts\OrganizationResolver;
use App\Auth\Resolvers\SessionOrganizationResolver;
use App\Auth\Resolvers\SingleOrganizationResolver;
use App\Auth\Resolvers\SubdomainOrganizationResolver;
use App\Support\OrganizationContext;
use Illuminate\Support\ServiceProvider;

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

        $this->app->bind(
            OrganizationResolver::class,
            self::RESOLVERS[config()->string('organizations.resolver')] ?? SessionOrganizationResolver::class,
        );
    }
}
