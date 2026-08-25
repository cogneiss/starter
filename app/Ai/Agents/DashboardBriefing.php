<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Agents\Concerns\HasDefaultMiddleware;
use App\Ai\Concerns\OrganizationScopedAgent;
use App\Ai\Contracts\OrganizationScoped;
use App\Enums\KnownFeatures;
use App\Models\AiAuditLog;
use App\Models\OrganizationInvitation;
use App\Support\AiTier;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Promptable;

/**
 * The second vertical: two sentences about this organization, at the top of the
 * dashboard.
 *
 * It reads and never writes — no tools at all, so there is nothing for an
 * injected instruction to reach for. The figures are counted here and handed to
 * the model already decided; the model's only job is to say them in a sentence.
 * The answer is cached because a briefing that is regenerated on every page load
 * is a bill, not a feature.
 */
final class DashboardBriefing implements Agent, HasMiddleware, OrganizationScoped
{
    use HasDefaultMiddleware;
    use OrganizationScopedAgent;
    use Promptable;

    public function instructions(): string
    {
        return mb_trim((string) file_get_contents(resource_path('prompts/dashboard-briefing.md')));
    }

    /**
     * This organization's figures, counted rather than asked for.
     *
     * @return array<string, int>
     */
    public function figures(): array
    {
        return [
            'members' => $this->organization->memberships()->count(),
            'pending_invitations' => OrganizationInvitation::withoutOrganizationScope()
                ->where('organization_id', $this->organization->id)
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->count(),
            'agent_runs_this_week' => AiAuditLog::withoutOrganizationScope()
                ->where('organization_id', $this->organization->id)
                ->where('created_at', '>=', now()->subWeek())
                ->count(),
        ];
    }

    /**
     * The briefing, or null when this organization has the feature turned off.
     */
    public function briefing(): ?string
    {
        if (! KnownFeatures::AiBriefingEnabled->enabledFor($this->organization)) {
            return null;
        }

        $tier = AiTier::for('cheap');

        return Cache::remember(
            'ai:briefing:'.$this->organization->id,
            now()->addSeconds(config()->integer('ai.briefing.ttl')),
            fn (): string => $this->prompt(
                json_encode($this->figures(), JSON_THROW_ON_ERROR),
                provider: $tier['provider'],
                model: $tier['model'],
            )->text,
        );
    }
}
