<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Models\Organization;
use App\Support\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pins every API request to the organization its token belongs to.
 *
 * The token is the only source of the organization. A request that names a
 * different one — by header, body field, query parameter or subdomain — is
 * refused with a 404, the same answer a foreign record id gets, so the refusal
 * confirms nothing. Session cookies never authenticate here: anything but a
 * real ApiToken (Sanctum's TransientToken included) is a 401.
 */
final readonly class EnsureTokenMatchesOrganization
{
    /** The request fields and headers a client might use to name an organization. */
    private const array ORGANIZATION_INPUTS = ['organization_id', 'organization', 'org'];

    private const array ORGANIZATION_HEADERS = ['X-Organization-Id', 'X-Organization'];

    public function __construct(private OrganizationContext $context) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        abort_if(! $token instanceof ApiToken || $token->revoked_at !== null, 401);

        $organization = Organization::query()->find($token->organization_id);

        abort_unless($organization instanceof Organization, 401);

        abort_if($this->namesForeignOrganization($request, $organization), 404);

        $this->context->set($organization);

        return $next($request);
    }

    /**
     * Whether any channel of the request names an organization other than the
     * token's own. Naming the token's own organization is harmless.
     */
    private function namesForeignOrganization(Request $request, Organization $organization): bool
    {
        $named = [];

        foreach (self::ORGANIZATION_HEADERS as $header) {
            $named[] = $request->header($header);
        }

        foreach (self::ORGANIZATION_INPUTS as $input) {
            $named[] = $request->input($input);
            $named[] = $request->query($input);
        }

        $named[] = $this->subdomain($request);

        foreach ($named as $value) {
            foreach (is_array($value) ? $value : [$value] as $candidate) {
                if (! is_string($candidate) || $candidate === '') {
                    continue;
                }

                if ($candidate !== $organization->id && $candidate !== $organization->slug) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The first host label when the request arrived on a subdomain of the
     * configured application host, null otherwise.
     */
    private function subdomain(Request $request): ?string
    {
        $host = $request->getHost();
        $appHost = (string) parse_url(config()->string('app.url'), PHP_URL_HOST);

        if ($host === $appHost || ! str_ends_with($host, '.'.$appHost)) {
            return null;
        }

        return explode('.', $host)[0];
    }
}
