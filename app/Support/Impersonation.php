<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ImpersonationLog;
use App\Models\User;
use Illuminate\Contracts\Session\Session;

/**
 * The impersonation state of the current session. One place owns the session
 * keys so the actions, the middleware and the shared Inertia prop cannot drift.
 */
final readonly class Impersonation
{
    public const string USER_KEY = 'impersonator_user_id';

    public const string LOG_KEY = 'impersonation_log_id';

    public function __construct(private Session $session) {}

    public function active(): bool
    {
        return $this->session->has(self::USER_KEY);
    }

    public function impersonator(): ?User
    {
        $id = $this->session->get(self::USER_KEY);

        return is_string($id) ? User::query()->find($id) : null;
    }

    public function log(): ?ImpersonationLog
    {
        $id = $this->session->get(self::LOG_KEY);

        return is_string($id) ? ImpersonationLog::query()->find($id) : null;
    }

    public function remember(User $impersonator, ImpersonationLog $log): void
    {
        $this->session->put(self::USER_KEY, $impersonator->id);
        $this->session->put(self::LOG_KEY, $log->id);
    }

    public function forget(): void
    {
        $this->session->forget([self::USER_KEY, self::LOG_KEY]);
    }
}
