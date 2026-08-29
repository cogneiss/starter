<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Sleep;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\Webpage;

/**
 * The list kit is server-driven: every control is a visit that carries the
 * whole list state in the query string. So each step here asserts three things
 * together — the URL says what was asked for, the rows say the server answered
 * it, and the pending state sat on the table body rather than the page, which
 * is what keeps the surrounding chrome from flickering on every keystroke.
 */
function signInWithAListOfMembers(): void
{
    $organization = Organization::factory()->create();

    test()->actingAs(User::factory()->forOrganization($organization)->create(['name' => 'Aaron Owner']));

    // Thirteen people over a page size of ten: two pages, and a last name that
    // only the descending order brings onto the first one.
    foreach (range(1, 12) as $number) {
        User::factory()->forOrganization($organization, 'Member')->create([
            'name' => sprintf('Member %02d', $number),
            'email' => sprintf('member%02d@example.com', $number),
        ]);
    }
}

/**
 * The busy state lands on the table body and nowhere else.
 *
 * A click and the re-render it causes are not the same tick, so reading the DOM
 * the instant the click returns is a race the table loses on a loaded machine.
 * The body stays marked for a floor of 600ms once it is marked, so a short poll
 * catches it every time — and still fails if it is never marked at all.
 */
function assertOnlyTheTableBodyIsPending(Webpage|AwaitableWebpage $page): void
{
    foreach (range(1, 100) as $ignored) {
        if ($page->script('document.querySelectorAll(\'[data-test="table-body"][data-pending="true"]\').length') > 0) {
            break;
        }

        Sleep::usleep(20_000);
    }

    $page->assertPresent('[data-test="table-body"][data-pending="true"]')
        ->assertMissing('[data-pending="true"]:not([data-test="table-body"])');
}

it('sorts, pages and searches through the query string', function (): void {
    signInWithAListOfMembers();

    $page = visit('/settings/members')->wait(1);

    // The default order is ascending by name, and nothing is in the URL yet.
    $page->assertQueryStringMissing('sort')
        ->assertQueryStringMissing('page')
        ->assertQueryStringMissing('q')
        ->assertCount('[data-test="table-body"] tr', 10)
        ->assertSeeIn('[data-test="table-body"]', 'Aaron Owner')
        ->assertDontSeeIn('[data-test="table-body"]', 'Member 12');

    // Sorting: the URL gains the column and the direction, the pending state
    // lands on the table body alone, and the rows come back reversed.
    $page->click('[data-test="sort-user.name"]');

    assertOnlyTheTableBodyIsPending($page);

    $page->waitForText('Member 12')
        ->assertQueryStringHas('sort', 'user.name')
        ->assertQueryStringHas('dir', 'desc')
        ->assertSeeIn('[data-test="table-body"]', 'Member 12')
        ->assertDontSeeIn('[data-test="table-body"]', 'Aaron Owner');

    // Paging: the order is kept, the page is added, and the rows move on.
    $page->click('[data-test="table-next"]');

    assertOnlyTheTableBodyIsPending($page);

    $page->waitForText('Aaron Owner')
        ->assertQueryStringHas('page', '2')
        ->assertQueryStringHas('sort', 'user.name')
        ->assertSeeIn('[data-test="table-body"]', 'Aaron Owner')
        ->assertDontSeeIn('[data-test="table-body"]', 'Member 12')
        ->assertSeeIn('[data-test="table-page"]', 'Page 2 of 2');

    // Searching: the term reaches the URL, the page falls back to the first one
    // because the old page number means nothing against a new result set, and
    // the rows narrow to the single match.
    $page->type('[data-test="table-search"]', 'member 07');

    assertOnlyTheTableBodyIsPending($page);

    $page->waitForText('member07@example.com')
        ->assertQueryStringHas('q', 'member 07')
        ->assertQueryStringMissing('page')
        ->assertCount('[data-test="table-body"] tr', 1)
        ->assertSeeIn('[data-test="table-body"]', 'Member 07')
        ->assertDontSeeIn('[data-test="table-body"]', 'Member 08')
        ->assertNoJavaScriptErrors();
});

it('says so when a search matches nothing', function (): void {
    signInWithAListOfMembers();

    visit('/settings/members')
        ->wait(1)
        ->type('[data-test="table-search"]', 'nobody at all')
        ->waitForText('No member matches that search.')
        ->assertPresent('[data-test="table-empty"]')
        ->assertQueryStringHas('q', 'nobody at all')
        ->assertNoJavaScriptErrors();
});
