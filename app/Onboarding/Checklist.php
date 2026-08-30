<?php

declare(strict_types=1);

namespace App\Onboarding;

use App\Models\OnboardingProgress;
use App\Models\Organization;
use App\Models\User;

/**
 * The activation checklist for one person in one organization.
 *
 * Everything here is derived on read. Each step is asked whether it is done and
 * answers from the data it is about, so this never writes: a person who brands
 * their organization from the settings screen has finished that step before the
 * checklist is next rendered, with nothing in between to keep in sync.
 *
 * The only stored fact is the decision to skip or dismiss, which no amount of
 * looking at the data could recover.
 */
final readonly class Checklist
{
    public function __construct(private StepRegistry $registry) {}

    /**
     * @return array{
     *     steps: list<array{key: string, title: string, description: string, href: string, required: bool, complete: bool}>,
     *     next: string|null,
     *     complete: bool,
     *     dismissed: bool,
     * }
     */
    public function for(User $user, Organization $organization): array
    {
        $steps = [];
        $next = null;

        foreach ($this->registry->all() as $step) {
            $complete = $step->isComplete($user, $organization);

            if (! $complete && $next === null) {
                $next = $step->key();
            }

            $steps[] = [
                'key' => $step->key(),
                'title' => $step->title(),
                'description' => $step->description(),
                'href' => route($step->route()),
                'required' => $step->isRequired(),
                'complete' => $complete,
            ];
        }

        return [
            'steps' => $steps,
            'next' => $next,
            'complete' => $this->isSatisfied($user, $organization),
            'dismissed' => $this->decision($user) instanceof OnboardingProgress,
        ];
    }

    /**
     * Whether the required steps are behind this person.
     *
     * Optional steps stay on the checklist and are never asked about here, which
     * is the whole difference between the two: one gates, the other suggests.
     */
    public function isSatisfied(User $user, Organization $organization): bool
    {
        return array_all($this->registry->required(), fn (StepContract $step): bool => $step->isComplete($user, $organization));
    }

    /**
     * The stored decision, if this person has made one.
     */
    public function decision(User $user): ?OnboardingProgress
    {
        return OnboardingProgress::ownedBy($user)->first();
    }
}
