<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;

beforeEach(function (): void {
    $organization = Organization::factory()->create();

    $this->actingAs(User::factory()->forOrganization($organization)->create());

    User::factory()->forOrganization($organization, 'Member')->create(['name' => 'Marguerite Blythe']);
});

it('shows skeleton rows while the search is in flight', function (): void {
    $page = visit('/dashboard')->wait(1);

    // The palette marks itself busy on the keystroke rather than when the
    // request leaves, so the debounce is covered too and there is no blank
    // pause between typing and results.
    $page->keys('html > body', 'Meta+k')
        ->type('[data-test="palette-input"]', 'Marguerite')
        ->assertPresent('[data-test="palette-skeleton"]')
        ->waitForText('Marguerite Blythe')
        ->assertMissing('[data-test="palette-skeleton"]')
        ->assertNoJavaScriptErrors();
});

it('says so when nothing matches', function (): void {
    $page = visit('/dashboard')->wait(1);

    $page->keys('html > body', 'Meta+k')
        ->type('[data-test="palette-input"]', 'Threadbare Nonsense')
        ->waitForText('Nothing matches that search')
        ->assertMissing('[data-test="palette-result"]')
        ->assertNoJavaScriptErrors();
});

/**
 * A search the endpoint refuses: the term is longer than the validation allows,
 * so the response is an error rather than a result set. The palette must say
 * that and offer another go — a silently blank list reads as "no matches", which
 * is a different and wrong answer.
 */
it('offers a retry when the search fails rather than a blank list', function (): void {
    $page = visit('/dashboard')->wait(1);

    $page->keys('html > body', 'Meta+k')
        ->type('[data-test="palette-input"]', str_repeat('a', 300))
        ->waitForText('Search is not answering right now.')
        ->assertPresent('[data-test="palette-retry"]')
        ->assertMissing('[data-test="palette-empty"]')
        ->assertNoJavaScriptErrors();

    // Retrying re-asks. The term is still too long, so the error comes back —
    // what matters is that the affordance is live and not decoration.
    $page->click('Try again')
        ->assertPresent('[data-test="palette-skeleton"]')
        ->waitForText('Search is not answering right now.');
});
