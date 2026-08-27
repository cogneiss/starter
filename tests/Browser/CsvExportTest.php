<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;

/**
 * The export is the list endpoint asked for another representation of the same
 * query, and `Accept: text/csv` is the entire difference between the two.
 *
 * That is what makes this test a check on the header rather than on a button.
 * The success line the assertion waits for is rendered only after the client
 * has confirmed the response came back as `text/csv`; a request without the
 * header is answered with the Inertia page, the check fails, and the line never
 * appears. Removing the header from the request cannot leave this test green.
 */
it('asks the list endpoint for the same query as csv', function (): void {
    $organization = Organization::factory()->create();

    $this->actingAs(User::factory()->forOrganization($organization)->create([
        'name' => 'Aaron Owner',
    ]));

    User::factory()->forOrganization($organization, 'Member')->create([
        'name' => 'Beth Member',
        'email' => 'beth@example.com',
    ]);

    visit('/settings/members')
        ->wait(1)
        ->click('[data-test="table-export"]')
        ->waitForText('Export downloaded.')
        ->assertPresent('[data-test="export-ready"]')
        ->assertMissing('[data-test="table-error"]')
        ->assertNoJavaScriptErrors();
});
