<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;

/**
 * Two kinds of state sit on this screen and they must not be confused. What
 * data is shown — search, sort, page, filters — is in the URL, so it can be
 * linked, reloaded and shared. How the table looks to one person — which
 * columns they hid, how wide they dragged them — is theirs alone and stays in
 * their browser, under a key carrying a version so a later shape change can
 * discard the old one instead of choking on it.
 *
 * Selection follows the rows. A sort reorders the same result set, so a ticked
 * row is still ticked. A filter change produces a different result set, so a
 * selection made against the old one is dropped rather than quietly carried
 * into a bulk action.
 */
it('keeps look-and-feel in local storage and data state in the url', function (): void {
    $organization = Organization::factory()->create();

    $owner = User::factory()->forOrganization($organization)->create(['name' => 'Aaron Owner']);

    $this->actingAs($owner);

    foreach (range(1, 3) as $number) {
        User::factory()->forOrganization($organization, 'Member')->create([
            'name' => sprintf('Member %02d', $number),
            'email' => sprintf('member%02d@example.com', $number),
        ]);
    }

    $key = sprintf('table:v1:/settings/members:%s', $owner->id);

    $page = visit('/settings/members')->wait(1);

    // Hiding a column is a preference, not a query: the column leaves the table
    // and the URL does not move.
    $page->assertSeeIn('[data-test="table-body"]', 'member01@example.com')
        ->click('[data-test="column-controls"]')
        ->click('[data-test="column-email"]')
        ->assertDontSeeIn('[data-test="table-body"]', 'member01@example.com')
        ->assertQueryStringMissing('columns')
        ->assertQueryStringMissing('f')
        ->keys('[data-test="column-controls"]', ['Escape']);

    $page->assertScript(
        sprintf('JSON.parse(window.localStorage.getItem(%s)).hidden.includes("email")', json_encode($key)),
    );

    // A width dragged with the keyboard is remembered under the same key.
    $page->keys('[data-test="resize-name"]', ['ArrowRight'])
        ->assertScript(
            sprintf('typeof JSON.parse(window.localStorage.getItem(%s)).widths.name === "number"', json_encode($key)),
        );

    // Sorting reorders the rows a selection was made against, so it survives.
    $page->click('[data-test="select-page"]')
        ->assertPresent('[data-test="bulk-bar"]')
        ->click('[data-test="sort-user.name"]')
        ->waitForText('Member 03')
        ->assertQueryStringHas('sort', 'user.name')
        ->assertPresent('[data-test="bulk-bar"]');

    // Filtering produces a different result set, so it does not.
    $page->fill('[data-test="filter-joined-from"]', '2000-01-01')
        ->waitForText('Aaron Owner')
        ->assertQueryStringHas('f')
        ->assertScript("window.location.search.includes('f%5Bjoined%5D%5Bfrom%5D=2000-01-01')")
        ->assertMissing('[data-test="bulk-bar"]')
        ->assertNoJavaScriptErrors();
});
