<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends the user to the organization creation screen when nothing is bound.
 * Apply it to route groups whose data is organization-scoped.
 */
final readonly class RequireOrganization
{
    public function __construct(private OrganizationContext $context) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->context->has()) {
            return to_route('organization.create');
        }

        return $next($request);
    }
}
