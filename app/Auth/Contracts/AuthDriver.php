<?php

declare(strict_types=1);

namespace App\Auth\Contracts;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * One way of proving who somebody is. The login flow only ever talks to this
 * contract, so adding SSO later is writing a driver rather than rewriting the
 * controller.
 *
 * A future SAML or OIDC driver must verify the assertion against the request
 * host before trusting it. An assertion minted for one host and replayed at
 * another signs the attacker into a different organization.
 *
 * SSO users are default-deny: a driver must not provision an account for an
 * unknown identity unless the organization has explicitly opted in.
 */
interface AuthDriver
{
    /**
     * The config key this driver is registered under.
     */
    public function key(): string;

    /**
     * Start the flow: the page or the redirect that collects the proof.
     */
    public function redirect(Request $request): RedirectResponse|Response;

    /**
     * Finish the flow, or null when the request carries no valid identity.
     */
    public function authenticate(Request $request): ?User;
}
