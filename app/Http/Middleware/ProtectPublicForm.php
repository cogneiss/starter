<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\FormFriction;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards a public form with the friction checks. A tripped check answers
 * exactly like a success and is logged without PII: telling a bot which check
 * it tripped is free tuning, and an address it typed is not ours to keep.
 */
final readonly class ProtectPublicForm
{
    public function __construct(private FormFriction $friction) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // A precognitive request only validates — the action never runs, so no
        // record can come out of it — and it fires on the first keystroke,
        // long before a person could satisfy the dwell window.
        if ($request->isPrecognitive()) {
            return $next($request);
        }

        $reason = $this->friction->tripped($request);

        if ($reason === null) {
            return $next($request);
        }

        Log::warning('Public form friction tripped.', [
            'route' => $request->route()?->getName(),
            'reason' => $reason,
        ]);

        return match ($request->route()?->getName()) {
            'magic-link.store' => back()->with('status', __('A login link will be sent if the account exists.')),
            default => redirect()->intended(route('dashboard', absolute: false)),
        };
    }
}
