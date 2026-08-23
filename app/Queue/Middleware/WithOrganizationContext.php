<?php

declare(strict_types=1);

namespace App\Queue\Middleware;

use App\Contracts\OrganizationAware;
use App\Models\Organization;
use App\Support\OrganizationContext;
use Closure;

/**
 * Binds the job's organization for the duration of the job, and unbinds it
 * afterwards so the next job on the same worker starts clean.
 */
final readonly class WithOrganizationContext
{
    public function handle(OrganizationAware $job, Closure $next): mixed
    {
        $organizationId = $job->organizationId();

        if ($organizationId === null) {
            return $next($job);
        }

        $organization = Organization::query()->findOrFail($organizationId);

        return resolve(OrganizationContext::class)->runAs(
            $organization,
            fn (): mixed => $next($job),
        );
    }
}
