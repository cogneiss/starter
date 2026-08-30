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
 * Start recording every element that is marked busy from here on.
 *
 * Polling the DOM after the click is a race the table loses on a loaded
 * machine: the busy state can come and go inside the same call that dispatched
 * the click. An observer installed beforehand cannot miss it, however short it
 * was, so the assertion below is about what happened rather than about what is
 * still on the screen when the test gets a turn.
 */
function watchWhatGoesPending(Webpage|AwaitableWebpage $page): void
{
    $page->script(<<<'JS'
        window.__pending = [];

        if (window.__pendingObserver) {
            window.__pendingObserver.disconnect();
        }

        window.__pendingObserver = new MutationObserver(() => {
            for (const element of document.querySelectorAll('[data-pending="true"]')) {
                window.__pending.push(element.getAttribute('data-test') ?? element.tagName);
            }
        });

        window.__pendingObserver.observe(document.body, {
            subtree: true,
            childList: true,
            attributes: true,
        });
    JS);
}

/**
 * The busy state landed on the table body and nowhere else.
 */
function assertOnlyTheTableBodyWentPending(Webpage|AwaitableWebpage $page): void
{
    $seen = [];

    foreach (range(1, 250) as $ignored) {
        /** @var list<string> $seen */
        $seen = $page->script('window.__pending');

        if ($seen !== []) {
            break;
        }

        Sleep::usleep(20_000);
    }

    expect(array_values(array_unique($seen)))->toBe(['table-body']);
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
    watchWhatGoesPending($page);

    $page->click('[data-test="sort-user.name"]');

    assertOnlyTheTableBodyWentPending($page);

    $page->waitForText('Member 12')
        ->assertQueryStringHas('sort', 'user.name')
        ->assertQueryStringHas('dir', 'desc')
        ->assertSeeIn('[data-test="table-body"]', 'Member 12')
        ->assertDontSeeIn('[data-test="table-body"]', 'Aaron Owner');

    // Paging: the order is kept, the page is added, and the rows move on.
    watchWhatGoesPending($page);

    $page->click('[data-test="table-next"]');

    assertOnlyTheTableBodyWentPending($page);

    $page->waitForText('Aaron Owner')
        ->assertQueryStringHas('page', '2')
        ->assertQueryStringHas('sort', 'user.name')
        ->assertSeeIn('[data-test="table-body"]', 'Aaron Owner')
        ->assertDontSeeIn('[data-test="table-body"]', 'Member 12')
        ->assertSeeIn('[data-test="table-page"]', 'Page 2 of 2');

    // Searching: the term reaches the URL, the page falls back to the first one
    // because the old page number means nothing against a new result set, and
    // the rows narrow to the single match.
    watchWhatGoesPending($page);

    $page->type('[data-test="table-search"]', 'member 07');

    assertOnlyTheTableBodyWentPending($page);

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
