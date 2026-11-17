<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ImpersonationLog;
use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;

final readonly class StopImpersonation
{
    public function __construct(
        private Impersonation $impersonation,
        private Session $session,
    ) {}

    /**
     * Put the original user back and close the audit row. A session with no
     * impersonation is left alone. When the original
     * user can no longer be loaded the session is signed out, never left on the
     * target with the read-only guard lifted. The impersonation keys are cleared
     * after the sign-in so the login listener still sees an impersonation and
     * writes no history for the admin's return.
     */
    public function handle(): ?User
    {
        if (! $this->impersonation->active()) {
            return null;
        }

        $impersonator = $this->impersonation->impersonator();
        $log = $this->impersonation->log();

        if ($log instanceof ImpersonationLog && $log->ended_at === null) {
            $log->forceFill(['ended_at' => now()])->save();
        }

        if (! $impersonator instanceof User) {
            Auth::guard('web')->logout();
            $this->session->invalidate();
            $this->session->regenerateToken();

            return null;
        }

        Auth::guard('web')->login($impersonator);

        $this->impersonation->forget();
        $this->session->forget('auth.password_confirmed_at');
        $this->session->regenerate();

        return $impersonator;
    }
}
