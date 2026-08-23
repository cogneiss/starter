<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes the doors an impersonated session must never walk through: credential
 * changes, second factors, account deletion and nested impersonation. One
 * middleware rather than checks scattered across controllers.
 */
final readonly class ForbiddenDuringImpersonation
{
    public function __construct(private Impersonation $impersonation) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($this->impersonation->active(), 403);

        return $next($request);
    }
}
