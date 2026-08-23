<?php

declare(strict_types=1);

namespace App\Auth\Contracts;

use App\Models\Organization;
use Illuminate\Http\Request;

interface OrganizationResolver
{
    /**
     * Resolve the organization for the request, or null when there is none.
     *
     * An implementation must verify that the current user holds an active
     * membership before returning an organization. Returning one the user is
     * not a member of hands them another organization's data.
     */
    public function resolve(Request $request): ?Organization;
}
