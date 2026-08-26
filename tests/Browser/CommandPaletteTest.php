<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;

/**
 * The palette is a keyboard surface. Every step here is a key press: nothing is
 * clicked, because a person who reaches for ⌘K is not reaching for the mouse.
 */
function signInWithMembers(): void
{
    $organization = Organization::factory()->create();

    test()->actingAs(User::factory()->forOrganization($organization)->create());

    User::factory()->forOrganization($organization, 'Member')->create(['name' => 'Marguerite Blythe']);
    User::factory()->forOrganization($organization, 'Member')->create(['name' => 'Marguerite Thorne']);
}

it('opens with the command key, walks the results and opens one', function (): void {
    signInWithMembers();

    $page = visit('/dashboard')->wait(1);

    // Closed until asked for.
    $page->assertMissing('[data-test="command-palette"]')
        ->keys('html > body', 'Meta+k')
        ->assertPresent('[data-test="command-palette"]')
        ->assertPresent('[role="dialog"]');

    // Both people match, and each of them is a hit in two groups: the member
    // list and the user list. The keyboard walks all four as one list.
    $page->type('[data-test="palette-input"]', 'Marguerite')
        ->waitForText('Marguerite Blythe')
        ->assertCount('[data-test="palette-result"]', 4);

    // The first hit is selected on arrival; the arrow keys move that selection
    // without the focus ever leaving the input.
    $page->assertAriaAttribute('#command-palette-result-0', 'selected', 'true')
        ->keys('[data-test="palette-input"]', 'ArrowDown')
        ->assertAriaAttribute('#command-palette-result-1', 'selected', 'true')
        ->assertAriaAttribute('#command-palette-result-0', 'selected', 'false')
        ->keys('[data-test="palette-input"]', 'ArrowUp')
        ->assertAriaAttribute('#command-palette-result-0', 'selected', 'true')
        ->assertCount('[data-test="palette-result"][data-selected="true"]', 1);

    // Enter lands on the URL the selected result carried. The member group sorts
    // before the user group, so the first hit is a member and its URL is the
    // member screen rather than the profile one the user group would have given.
    $page->keys('[data-test="palette-input"]', 'Enter')
        ->assertPathIs('/settings/members')
        ->assertMissing('[data-test="command-palette"]')
        ->assertNoJavaScriptErrors();
});

it('closes on escape without navigating', function (): void {
    signInWithMembers();

    $page = visit('/dashboard')->wait(1);

    $page->keys('html > body', 'Meta+k')
        ->assertPresent('[data-test="command-palette"]')
        ->keys('[data-test="palette-input"]', 'Escape')
        ->assertMissing('[data-test="command-palette"]')
        ->assertPathIs('/dashboard')
        ->assertNoJavaScriptErrors();
});
