<?php

declare(strict_types=1);

namespace App\Auth\Resolvers;

use App\Auth\Contracts\OrganizationResolver;
use App\Models\Organization;
use Illuminate\Http\Request;

/**
 * Binds the one organization in the database. For apps that will never be
 * multi-organization but still want scoped models and the authorization layer.
 */
final readonly class SingleOrganizationResolver implements OrganizationResolver
{
    public function resolve(Request $request): ?Organization
    {
        return Organization::query()->oldest('created_at')->first();
    }
}
