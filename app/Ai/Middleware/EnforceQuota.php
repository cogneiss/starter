<?php

declare(strict_types=1);

namespace App\Ai\Middleware;

use App\Ai\Contracts\OrganizationScoped;
use App\Enums\AiAuditStatus;
use App\Exceptions\AiQuotaExceededException;
use App\Models\AiAuditLog;
use App\Support\AiQuota;
use App\Support\OrganizationContext;
use Closure;
use Laravel\Ai\PendingStep;

/**
 * First in the pipeline: a request that is going to be refused must not be paid
 * for. The refusal is still written to the audit log, so usage reporting shows
 * the member hitting the ceiling rather than silently going quiet.
 *
 * Middleware wraps every generation step, so only the first step is checked: a
 * run that was let in finishes its tool loop rather than dying halfway.
 */
final readonly class EnforceQuota
{
    public function __construct(
        private OrganizationContext $context,
        private AiQuota $quota,
    ) {}

    public function handle(PendingStep $step, Closure $next): mixed
    {
        $agent = $step->options?->agent;

        if (! $agent instanceof OrganizationScoped || ! $step->isFirstStep()) {
            return $next($step);
        }

        return $this->context->runAs($agent->organization, function () use ($agent, $step, $next): mixed {
            $reason = $this->quota->exceededReason($agent->user);

            if ($reason !== null) {
                AiAuditLog::query()->create([
                    'user_id' => $agent->user->id,
                    'agent' => $agent::class,
                    'status' => AiAuditStatus::Blocked,
                    'blocked_reason' => $reason,
                ]);

                throw new AiQuotaExceededException($reason);
            }

            return $next($step);
        });
    }
}
