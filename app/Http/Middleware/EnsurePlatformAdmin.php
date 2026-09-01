<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * The one door into the admin control plane. The `platform` ability is a gate
 * on the user record itself — no organization role can grant it — and denial
 * is a 404, so to anyone without it the control plane does not exist.
 */
final readonly class EnsurePlatformAdmin
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Gate::allows('platform'), 404);

        return $next($request);
    }
}
