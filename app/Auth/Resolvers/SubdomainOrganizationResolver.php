<?php

declare(strict_types=1);

namespace App\Auth\Resolvers;

use App\Auth\Contracts\OrganizationResolver;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Matches the first host label against organizations.slug. Ships but is off by
 * default: it needs wildcard DNS and a wildcard certificate.
 */
final readonly class SubdomainOrganizationResolver implements OrganizationResolver
{
    public function resolve(Request $request): ?Organization
    {
        $host = $request->getHost();
        $appHost = Str::of(config()->string('app.url'))->after('://')->before('/')->before(':')->value();

        if ($host === $appHost || ! Str::endsWith($host, '.'.$appHost)) {
            return null;
        }

        $organization = Organization::query()
            ->where('slug', Str::before($host, '.'))
            ->first();

        if (! $organization instanceof Organization) {
            return null;
        }

        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        return $user->belongsToOrganization($organization) ? $organization : null;
    }
}
