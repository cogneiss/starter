<?php

declare(strict_types=1);

use App\Ai\Agents\DashboardBriefing;
use App\Enums\KnownFeatures;
use App\Models\AiAuditLog;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * An organization with the briefing turned on, its owner, and figures worth
 * reporting: three members, one live invitation, two agent runs this week.
 *
 * @return array{0: User, 1: Organization}
 */
function briefedOrganization(): array
{
    config()->set('features.defaults.ai-briefing-enabled', true);

    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization, 'Owner')->create();
    User::factory()->count(2)->forOrganization($organization)->create();

    OrganizationInvitation::factory()->create(['organization_id' => $organization->id]);

    AiAuditLog::factory()->count(2)->create(['organization_id' => $organization->id]);

    return [$owner, $organization];
}

it('hands the model figures it counted rather than figures it asked for', function (): void {
    [$user, $organization] = briefedOrganization();

    DashboardBriefing::fake(['Three members, one invitation outstanding.'])->preventStrayPrompts();

    $briefing = new DashboardBriefing($user, $organization);

    expect($briefing->figures())->toBe([
        'members' => 3,
        'pending_invitations' => 1,
        'agent_runs_this_week' => 2,
    ])->and($briefing->briefing())->toBe('Three members, one invitation outstanding.');

    DashboardBriefing::assertPrompted(fn (object $prompt): bool => str_contains(
        (string) $prompt->prompt,
        '{"members":3,"pending_invitations":1,"agent_runs_this_week":2}',
    ));
});

it('reports a zero as a zero instead of leaving the model to guess', function (): void {
    config()->set('features.defaults.ai-briefing-enabled', true);

    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization, 'Owner')->create();

    DashboardBriefing::fake(['A quiet week.'])->preventStrayPrompts();

    $briefing = new DashboardBriefing($owner, $organization);

    expect($briefing->figures())->toBe([
        'members' => 1,
        'pending_invitations' => 0,
        'agent_runs_this_week' => 0,
    ])->and($briefing->briefing())->toBe('A quiet week.');

    DashboardBriefing::assertPrompted(fn (object $prompt): bool => str_contains(
        (string) $prompt->prompt,
        '"agent_runs_this_week":0',
    ));
});

it('answers a second dashboard load from the cache', function (): void {
    [$user, $organization] = briefedOrganization();

    DashboardBriefing::fake(['Three members, one invitation outstanding.'])->preventStrayPrompts();

    $first = new DashboardBriefing($user, $organization)->briefing();
    $second = new DashboardBriefing($user, $organization)->briefing();

    expect($second)->toBe($first);

    DashboardBriefing::assertPromptedTimes(1);
});

it('caches one organization away from another', function (): void {
    [$user, $organization] = briefedOrganization();
    [$otherUser, $otherOrganization] = briefedOrganization();

    DashboardBriefing::fake(['The first briefing.', 'The second briefing.'])->preventStrayPrompts();

    expect(new DashboardBriefing($user, $organization)->briefing())->toBe('The first briefing.')
        ->and(new DashboardBriefing($otherUser, $otherOrganization)->briefing())->toBe('The second briefing.');

    DashboardBriefing::assertPromptedTimes(2);
});

it('prompts nothing at all while the feature flag is off', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization, 'Owner')->create();

    DashboardBriefing::fake(['Never reached.'])->preventStrayPrompts();

    expect(KnownFeatures::AiBriefingEnabled->default())->toBeFalse()
        ->and(new DashboardBriefing($owner, $organization)->briefing())->toBeNull();

    DashboardBriefing::assertNeverPrompted();
});

it('refuses to brief someone who is not a member', function (): void {
    $organization = Organization::factory()->create();
    $stranger = User::factory()->create();

    expect(fn (): DashboardBriefing => new DashboardBriefing($stranger, $organization))
        ->toThrow(AuthorizationException::class);
});
