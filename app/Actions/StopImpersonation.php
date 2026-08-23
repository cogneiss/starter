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
     * Put the original user back and close the audit row. Doing this twice is
     * not an error: a session with nothing to restore is left alone.
     */
    public function handle(): ?User
    {
        $impersonator = $this->impersonation->impersonator();
        $log = $this->impersonation->log();

        if ($log instanceof ImpersonationLog && $log->ended_at === null) {
            $log->forceFill(['ended_at' => now()])->save();
        }

        $this->impersonation->forget();

        if (! $impersonator instanceof User) {
            return null;
        }

        Auth::guard('web')->login($impersonator);

        $this->session->regenerate();

        return $impersonator;
    }
}
