<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ImpersonationLog;
use App\Models\User;
use App\Support\Impersonation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final readonly class StartImpersonation
{
    public function __construct(
        private Impersonation $impersonation,
        private Request $request,
        private Session $session,
    ) {}

    /**
     * Sign the platform staff member in as another user and write the audit row
     * that makes the whole feature acceptable.
     *
     * @throws AuthorizationException
     */
    public function handle(User $impersonator, User $target): ImpersonationLog
    {
        throw_unless($impersonator->is_super_admin, AuthorizationException::class);
        throw_if($target->is_super_admin, AuthorizationException::class);
        throw_if($this->impersonation->active(), AuthorizationException::class);
        throw_if($impersonator->is($target), AuthorizationException::class);

        $log = ImpersonationLog::query()->create([
            'impersonator_user_id' => $impersonator->id,
            'impersonated_user_id' => $target->id,
            'organization_id' => $target->current_organization_id,
            'started_at' => now(),
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
        ]);

        Auth::guard('web')->login($target);

        $this->session->regenerate();

        $this->impersonation->remember($impersonator, $log);

        return $log;
    }
}
