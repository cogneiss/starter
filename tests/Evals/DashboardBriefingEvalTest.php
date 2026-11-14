<?php

declare(strict_types=1);

use App\Ai\Agents\DashboardBriefing;
use App\Models\Organization;
use App\Models\User;
use App\Support\AiTier;
use App\Support\OrganizationContext;

/**
 * Grades the shipped briefing agent: the model is handed figures we counted,
 * and the briefing has to report those figures rather than invent nicer ones.
 * The closure prompts on the cheap tier, the same call `briefing()` makes,
 * without the feature flag and the cache around it.
 */
it('reports the figures it was handed, invents none, and fits on a dashboard', function (array $case): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization, 'Owner')->create();
    $agent = new DashboardBriefing($owner, $organization);
    $tier = AiTier::for('cheap');

    $answer = resolve(OrganizationContext::class)->runAs(
        $organization,
        fn (): string => expect(fn (string $input): string => $agent->prompt($input, provider: $tier['provider'], model: $tier['model'])->text)
            ->prompt(json_encode($case['figures'], JSON_THROW_ON_ERROR))
            ->toSatisfy('States only the figures given in the input and invents none.')
            ->value,
    );

    foreach ($case['mentions'] as $figure) {
        expect($answer)->toContain($figure);
    }

    expect(mb_strlen($answer))->toBeGreaterThan(0)->toBeLessThan(400);
})->with(evalCases('dashboard-briefing'));
