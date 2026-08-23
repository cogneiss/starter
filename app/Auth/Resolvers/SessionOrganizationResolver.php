<?php

declare(strict_types=1);

namespace App\Auth\Resolvers;

use App\Auth\Contracts\OrganizationResolver;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Resolves the organization the user last switched to. The default: it works
 * on a single host and needs no DNS.
 */
final readonly class SessionOrganizationResolver implements OrganizationResolver
{
    public function resolve(Request $request): ?Organization
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        $organization = $user->currentOrganization;

        if (! $organization instanceof Organization) {
            return null;
        }

        return $user->belongsToOrganization($organization) ? $organization : null;
    }
}
