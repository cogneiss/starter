<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Holds a member inside the two-factor setup screen while their organization
 * requires two-factor authentication and they have not confirmed it yet.
 *
 * The setup, verification and logout routes stay reachable: locking somebody
 * out of the screen that fixes the problem locks them out of the account.
 */
final readonly class EnsureTwoFactorEnabled
{
    public function __construct(private OrganizationContext $context) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->needsTwoFactor($request) && ! $this->isEscapeHatch($request)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Your organization requires two-factor authentication.'),
            ]);

            return to_route('two-factor.show');
        }

        return $next($request);
    }

    private function needsTwoFactor(Request $request): bool
    {
        $organization = $this->context->get();
        $user = $request->user();

        return $organization instanceof Organization
            && $organization->require_two_factor
            && $user instanceof User
            && ! $user->hasEnabledTwoFactorAuthentication();
    }

    /**
     * The routes a member has to keep reaching to satisfy the requirement.
     */
    private function isEscapeHatch(Request $request): bool
    {
        return $request->routeIs('two-factor.*', 'password.confirm*', 'verification.*', 'logout');
    }
}
