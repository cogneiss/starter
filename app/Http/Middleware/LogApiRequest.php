<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiRequestLog;
use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Writes one api_request_logs row per authenticated API request, after the
 * response has been sent, so logging never adds to the request's latency.
 *
 * The row records routing facts only — method, path, resource key, status,
 * duration. No request body value, no query value, no header ever reaches it:
 * a usage log is not an audit log and must not become an accidental PII store.
 * Sitting outside the rate limiter in the stack, it sees and records 429s too.
 */
final readonly class LogApiRequest
{
    private const string STARTED_AT = 'api_request_started_at';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(self::STARTED_AT, microtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $started = $request->attributes->get(self::STARTED_AT);
        $token = $request->user()?->currentAccessToken();

        // Nothing to attribute the request to: authentication never completed,
        // so this middleware's handle() never ran either.
        if (! is_float($started) || ! $token instanceof ApiToken) {
            return;
        }

        $resource = $request->route()?->parameter('resource');

        ApiRequestLog::query()->create([
            'organization_id' => $token->organization_id,
            'api_token_id' => $token->id,
            'method' => $request->method(),
            'path' => $request->path(),
            'resource' => is_string($resource) ? $resource : null,
            'status' => $response->getStatusCode(),
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'created_at' => now(),
        ]);
    }
}
