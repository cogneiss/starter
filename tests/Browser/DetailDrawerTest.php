<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;

/**
 * The drawer is a URL, not a piece of client state: `?peek=<id>` can be linked
 * to, reloaded and shared, and the record it names is fetched by the same
 * scoped lookup the list itself used.
 *
 * Which is what decides the second case. An id from another organization is not
 * a record this request is refused — it is not a record at all, so the answer is
 * 404 and not a 403 confirming that the id is real.
 */
it('renders the record named by peek in the address bar', function (): void {
    $organization = Organization::factory()->create();

    $this->actingAs(User::factory()->forOrganization($organization)->create([
        'name' => 'Aaron Owner',
    ]));

    $member = User::factory()->forOrganization($organization, 'Member')->create([
        'name' => 'Beth Member',
        'email' => 'beth@example.com',
    ]);

    $membership = $organization->memberships()->where('user_id', $member->id)->sole();

    visit('/settings/members?peek='.$membership->id)
        ->wait(1)
        ->assertPresent('[data-test="detail-drawer"]')
        ->assertSeeIn('[data-test="detail-drawer"]', 'Beth Member')
        ->assertSeeIn('[data-test="detail-drawer"]', 'beth@example.com')
        ->assertSeeIn('[data-test="peek-field-role"]', 'Member')
        ->assertNoJavaScriptErrors();
});

it('answers 404 for a peek id from another organization', function (): void {
    $organization = Organization::factory()->create();

    $this->actingAs(User::factory()->forOrganization($organization)->create());

    $elsewhere = Organization::factory()->create();
    $stranger = User::factory()->forOrganization($elsewhere)->create();
    $foreign = $elsewhere->memberships()->where('user_id', $stranger->id)->sole();

    $this->get('/settings/members?peek='.$foreign->id)->assertNotFound();
});
