<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\SavedSearch;
use App\Models\User;

/**
 * A page left open long enough for its token to expire is the commonest failure
 * nobody reads as a failure: the request is refused, and the usual advice is to
 * reload — which throws away whatever was typed.
 *
 * Here the browser fixes it. It asks for a fresh token and sends the same
 * request again, and the thing the person was doing completes.
 *
 * The refusal is staged in the browser rather than by expiring the cookie,
 * because Laravel's CSRF middleware stands aside while the application is being
 * tested, and the browser tests run it in that same process. What is under test
 * is the client half regardless: the first POST is answered 419, and everything
 * after that — the sentence, the new token, the second POST and the record it
 * writes — is the application's own doing. The server half, that a 419 is left
 * as it is for a request the browser can retry, is asserted in
 * `tests/Feature/FriendlyErrorTest.php`.
 */
it('recovers from an expired token instead of asking for a reload', function (): void {
    $organization = Organization::factory()->create();

    $this->actingAs(User::factory()->forOrganization($organization)->create());

    $saved = fn (): bool => SavedSearch::query()->where('name', 'Everyone')->exists();

    $page = visit('/settings/members')->wait(1);

    // The next write is refused the way an expired token refuses it: one 419,
    // then the transport goes back to normal.
    $page->script(<<<'JS'
        (() => {
            const Real = window.XMLHttpRequest;

            window.XMLHttpRequest = function () {
                const request = new Real();
                const open = request.open.bind(request);
                const send = request.send.bind(request);
                let method = 'GET';

                request.open = (verb, ...rest) => {
                    method = String(verb).toUpperCase();

                    return open(verb, ...rest);
                };

                request.send = (...args) => {
                    if (method !== 'POST') {
                        return send(...args);
                    }

                    window.XMLHttpRequest = Real;

                    Object.defineProperty(request, 'status', { value: 419 });
                    Object.defineProperty(request, 'responseText', { value: '' });
                    request.getAllResponseHeaders = () => '';

                    window.setTimeout(() => request.onload(new Event('load')), 0);
                };

                return request;
            };
        })();
    JS);

    $page->click('[data-test="table-views"]')
        ->click('[data-test="view-save"]')
        ->type('[data-test="view-name"]', 'Everyone')
        ->click('[data-test="view-confirm"]')
        ->waitForText('Your session expired while that page was open.');

    expect($saved())->toBeFalse();

    // The offer sends the same write again, and this time it lands: the view is
    // in the list without anybody retyping its name.
    $page->click('Try again')
        ->wait(2)
        ->click('[data-test="table-views"]')
        ->waitForText('Everyone')
        ->assertNoJavaScriptErrors();

    expect($saved())->toBeTrue();
});
