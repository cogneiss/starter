<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Auth\Contracts\OrganizationResolver;
use App\Models\Organization;
use App\Support\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the resolved organization into the ambient context. Does nothing when
 * nothing resolves, so guest routes keep working.
 */
final readonly class ResolveOrganization
{
    public function __construct(
        private OrganizationResolver $resolver,
        private OrganizationContext $context,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $organization = $this->resolver->resolve($request);

        if ($organization instanceof Organization) {
            $this->context->set($organization);
        }

        return $next($request);
    }
}
