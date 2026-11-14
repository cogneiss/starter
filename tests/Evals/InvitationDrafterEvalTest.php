<?php

declare(strict_types=1);

use App\Ai\Agents\InvitationDrafter;
use App\Models\Organization;
use App\Models\User;
use App\Support\OrganizationContext;

/**
 * Grades the shipped invitation drafter against a real model.
 *
 * The agent answers through a structured schema of `email` and `role`, so a
 * draft is right when both fields match the request. Claiming the invitation
 * was already sent is not something the schema can express, which is why no
 * case checks the wording for it.
 */
it('drafts the address and role the request asked for', function (array $case): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization, 'Owner')->create();

    $answer = resolve(OrganizationContext::class)->runAs(
        $organization,
        fn (): string => expect(new InvitationDrafter($owner, $organization))
            ->prompt($case['request'])
            ->toBeJson()
            ->value,
    );

    expect(json_decode($answer, true, 512, JSON_THROW_ON_ERROR))
        ->toMatchArray(['email' => $case['email'], 'role' => $case['role']]);
})->with(evalCases('invitation-drafter'));
