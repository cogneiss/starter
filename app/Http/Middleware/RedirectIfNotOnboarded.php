<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\OnboardingProgress;
use App\Models\Organization;
use App\Models\User;
use App\Onboarding\Checklist;
use App\Support\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Holds a new owner on the onboarding screen until the required steps are done.
 *
 * The gate is deliberately easy to get out of. It only applies to the person who
 * can actually finish the steps, it can be skipped once and for all, and the
 * screen it sends people to is the same one every time, so a refresh or a return
 * visit resumes rather than restarts.
 *
 * It excludes onboarding itself and the sign out route. A gate that redirects the
 * screen it redirects to is an infinite loop, and a gate that catches sign out
 * traps a person inside an organization with no way back out.
 */
final readonly class RedirectIfNotOnboarded
{
    public function __construct(
        private OrganizationContext $context,
        private Checklist $checklist,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->passes($request)) {
            return $next($request);
        }

        return to_route('onboarding.show');
    }

    private function passes(Request $request): bool
    {
        $user = $request->user();
        $organization = $this->context->get();

        throw_unless($user instanceof User, RuntimeException::class, 'The onboarding gate belongs behind the auth middleware.');
        throw_unless($organization instanceof Organization, RuntimeException::class, 'The onboarding gate belongs behind the organization middleware.');

        return $this->except($request, 'logout', 'onboarding.*')
            || ! $user->can('organization.update')
            || $this->checklist->decision($user) instanceof OnboardingProgress
            || $this->checklist->isSatisfied($user, $organization);
    }

    /**
     * The routes the gate never applies to.
     */
    private function except(Request $request, string ...$routes): bool
    {
        return $request->routeIs(...$routes);
    }
}
