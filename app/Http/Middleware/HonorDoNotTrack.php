<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shares the browser's Do Not Track preference with the front end so any
 * analytics added later can honour it. This application ships the signal, not
 * the analytics.
 */
final readonly class HonorDoNotTrack
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Inertia::share('doNotTrack', $request->header('DNT') === '1');

        return $next($request);
    }
}
