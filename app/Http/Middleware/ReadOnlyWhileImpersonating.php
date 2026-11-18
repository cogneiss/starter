<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Impersonation;
use App\Support\ResourceQuery;
use Closure;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * An impersonated session is read-only. Every write is refused with a 403 here,
 * in one place, rather than route by route, so a route added tomorrow is covered
 * the day it exists. A short list of reads that would hand over the target's
 * credentials, second factors or exports is refused too. Every impersonated
 * request also stamps the log's heartbeat.
 */
final readonly class ReadOnlyWhileImpersonating
{
    private const array SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    private const array DENIED_PATHS = ['user/two-factor-*', 'user/passkeys*', 'user/confirm-password*'];

    private const array DENIED_ROUTES = [
        'two-factor.*',
        'passkey.*',
        'browser-session.show',
        'import.download',
        'gdpr-export.download',
    ];

    public function __construct(private Impersonation $impersonation) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->impersonation->active()) {
            return $next($request);
        }

        $this->impersonation->heartbeat();

        abort_if($this->isDenied($request), 403);
        abort_unless($this->isReadOnly($request) || $this->isAllowedWrite($request), 403);

        return $next($request);
    }

    private function isDenied(Request $request): bool
    {
        return $request->is(...self::DENIED_PATHS)
            || $request->routeIs(...self::DENIED_ROUTES)
            || ResourceQuery::wantsCsv($request);
    }

    private function isReadOnly(Request $request): bool
    {
        return in_array($request->method(), self::SAFE_METHODS, true);
    }

    /**
     * The only writes an impersonating admin needs: leaving, and the private
     * channel handshake. A precognitive request counts only on a route that
     * carries the middleware that stops it before the controller, because the
     * header on any other route runs the controller for real.
     */
    private function isAllowedWrite(Request $request): bool
    {
        return $request->routeIs('user-impersonation.destroy', 'logout')
            || $request->is('broadcasting/auth')
            || ($request->isAttemptingPrecognition() && $this->routeStopsPrecognition($request));
    }

    private function routeStopsPrecognition(Request $request): bool
    {
        $route = $request->route();

        return $route instanceof Route
            && in_array(HandlePrecognitiveRequests::class, $route->gatherMiddleware(), true)
            && ! in_array(HandlePrecognitiveRequests::class, $route->excludedMiddleware(), true);
    }
}
