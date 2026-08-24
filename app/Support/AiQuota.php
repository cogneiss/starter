<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\AiAuditStatus;
use App\Models\AiAuditLog;
use App\Models\AiCreditLedgerEntry;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * The three limits an agent request is measured against: how much one member
 * may spend in an hour, how much the organization may spend in a day, and how
 * much money it may burn in a month. Counting reads the audit log, so a limit
 * cannot be reset by anything short of time passing.
 *
 * Both queries run inside the organization bound to OrganizationContext — the
 * caller binds it, the global scope applies it.
 */
final class AiQuota
{
    /**
     * The reason the next request has to be refused, or null to let it run.
     */
    public function exceededReason(User $user): ?string
    {
        $userLimit = config()->integer('ai.quotas.user_requests_per_hour');

        if ($this->requestsSince($user, now()->subHour()) >= $userLimit) {
            return "You have used all {$userLimit} AI requests allowed in an hour.";
        }

        $organizationLimit = config()->integer('ai.quotas.org_requests_per_day');

        if ($this->requestsSince(null, now()->subDay()) >= $organizationLimit) {
            return "This organization has used all {$organizationLimit} AI requests allowed in a day.";
        }

        $budget = config()->integer('ai.quotas.org_budget_micros_per_month');

        if ($this->spentThisMonthMicros() >= $budget) {
            return 'This organization has spent its AI budget for the month.';
        }

        return null;
    }

    /**
     * Requests that reached a provider inside the window. Blocked requests are
     * not counted: refusing one costs nothing, so it must not push the member
     * further over the limit.
     */
    private function requestsSince(?User $user, CarbonInterface $since): int
    {
        return AiAuditLog::query()
            ->when($user, fn ($query) => $query->where('user_id', $user?->id))
            ->where('status', '!=', AiAuditStatus::Blocked)
            ->where('created_at', '>=', $since)
            ->count();
    }

    /**
     * What the organization has been charged this calendar month, in micros.
     */
    private function spentThisMonthMicros(): int
    {
        return abs((int) AiCreditLedgerEntry::query()
            ->where('delta_micros', '<', 0)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('delta_micros'));
    }
}
