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

    public function impersonatorId(): ?string
    {
        $id = $this->session->get(self::USER_KEY);

        return is_string($id) ? $id : null;
    }

    public function impersonator(): ?User
    {
        $id = $this->impersonatorId();

        return $id === null ? null : User::query()->find($id);
    }

    public function log(): ?ImpersonationLog
    {
        $id = $this->session->get(self::LOG_KEY);

        return is_string($id) ? ImpersonationLog::query()->find($id) : null;
    }

    /**
     * Stamps the open log's updated_at on every impersonated request, so
     * impersonation:end-stale can close a log whose session expired at the
     * time it was last used. A builder update, so the audit bridge ignores it.
     */
    public function heartbeat(): void
    {
        ImpersonationLog::query()
            ->whereKey($this->session->get(self::LOG_KEY))
            ->whereNull('ended_at')
            ->touch();
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
