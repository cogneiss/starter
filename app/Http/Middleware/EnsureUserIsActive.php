<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\StopImpersonation;
use App\Models\User;
use App\Support\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the session of a deactivated account on its next request. Without this
 * middleware, deactivating somebody does nothing until their session expires.
 * When the deactivated account is being impersonated, the impersonation ends
 * instead, which closes its log and hands the session back to the admin.
 */
final readonly class EnsureUserIsActive
{
    public function __construct(
        private Impersonation $impersonation,
        private StopImpersonation $stopImpersonation,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->is_active) {
            if ($this->impersonation->active() && $this->stopImpersonation->handle() instanceof User) {
                Inertia::flash('toast', ['type' => 'success', 'message' => __('Impersonation ended.')]);

                return to_route('dashboard');
            }

            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return to_route('login')->with('status', __('Your account has been deactivated.'));
        }

        return $next($request);
    }
}
