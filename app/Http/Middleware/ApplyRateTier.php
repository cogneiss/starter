<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\KnownFeatures;
use App\Models\ApiToken;
use App\Models\Organization;
use App\Support\OrganizationContext;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Pennant\Feature;
use Symfony\Component\HttpFoundation\Response;

/**
 * Throttles API requests by the organization's plan tier.
 *
 * The tier name comes from the `api-rate-tier` Pennant feature, so a billing
 * integration can move an organization between tiers without a deploy; the
 * per-minute numbers live in config/api.php. Two windows are counted — one per
 * token, one per organization shared by all its tokens — and the stricter one
 * answers. Every response, throttled or not, carries the X-RateLimit-* headers.
 */
final readonly class ApplyRateTier
{
    public function __construct(private OrganizationContext $context) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $organization = $this->context->get();
        $token = $request->user()?->currentAccessToken();

        assert($organization instanceof Organization && $token instanceof ApiToken);

        $limits = $this->limits($organization);

        $windows = [
            'api:token:'.$token->id => $limits['per_token'],
            'api:org:'.$organization->id => $limits['per_organization'],
        ];

        foreach ($windows as $key => $limit) {
            if (RateLimiter::tooManyAttempts($key, $limit)) {
                $retryAfter = RateLimiter::availableIn($key);

                return $this->headers(new JsonResponse(['message' => 'Too many requests.'], 429, [
                    'Retry-After' => (string) $retryAfter,
                ]), $windows, $retryAfter);
            }
        }

        foreach (array_keys($windows) as $key) {
            RateLimiter::hit($key);
        }

        return $this->headers($next($request), $windows, 60);
    }

    /**
     * The per-minute numbers for the organization's tier, falling back to the
     * documented default tier when the feature names one config does not know.
     *
     * @return array{per_token: int, per_organization: int}
     */
    private function limits(Organization $organization): array
    {
        $tier = Feature::for($organization)->value(KnownFeatures::API_RATE_TIER);
        $tiers = config()->array('api.rate_tiers.tiers');

        $limits = is_string($tier) && array_key_exists($tier, $tiers)
            ? $tiers[$tier]
            : $tiers[config()->string('api.rate_tiers.default')];

        assert(is_array($limits) && is_int($limits['per_token']) && is_int($limits['per_organization']));

        return ['per_token' => $limits['per_token'], 'per_organization' => $limits['per_organization']];
    }

    /**
     * Stamps the response with the stricter of the two windows: the limit and
     * remaining of whichever side has less room.
     *
     * @param  array<string, int>  $windows
     */
    private function headers(Response $response, array $windows, int $reset): Response
    {
        $limit = 0;
        $remaining = PHP_INT_MAX;

        foreach ($windows as $key => $max) {
            $left = max(0, RateLimiter::remaining($key, $max));

            if ($left < $remaining) {
                $remaining = $left;
                $limit = $max;
            }
        }

        $response->headers->set('X-RateLimit-Limit', (string) $limit);
        $response->headers->set('X-RateLimit-Remaining', (string) $remaining);
        $response->headers->set('X-RateLimit-Reset', (string) (time() + $reset));

        return $response;
    }
}
