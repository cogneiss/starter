<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * The broadcasting connection has to be one that actually authorizes. The null
 * broadcaster answers every subscription without consulting the channel
 * callback, so a suite left on it would pass with no authorization at all.
 */
beforeEach(function (): void {
    config()->set('broadcasting.default', 'reverb');
    config()->set('broadcasting.connections.reverb.key', 'test-key');
    config()->set('broadcasting.connections.reverb.secret', 'test-secret');
    config()->set('broadcasting.connections.reverb.app_id', 'test-app');

    // Channels are registered against whichever broadcaster was the default
    // when the file was read, so the shipped file is read again now that this
    // one is. Without it the suite would test an empty channel list.
    require base_path('routes/channels.php');
});

/**
 * Ask the real broadcasting auth endpoint for a subscription, exactly as the
 * browser does.
 */
function subscribe(Organization $organization): TestResponse
{
    return test()->postJson('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => 'private-organization.'.$organization->id,
    ]);
}

// ChannelAuthorizationRoute: the decision is made by the shipped endpoint, not
// by calling the callback directly. A subscription for the caller's own
// organization is signed; one naming an organization they hold no membership in
// is refused there, on the same route, with the same request shape.
it('ChannelAuthorizationRoute signs a member in and refuses another organization over the broadcasting auth route', function (): void {
    $organization = Organization::factory()->create();
    $foreign = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();

    $this->actingAs($user);

    subscribe($organization)->assertOk();
    subscribe($foreign)->assertForbidden();
});

// ChannelAuthorizationUnaffiliated: a signed-in person who belongs to another
// organization entirely. Membership is a query, so nothing about the foreign
// organization is loaded and then discarded.
it('ChannelAuthorizationUnaffiliated refuses a listener from another organization', function (): void {
    $organization = Organization::factory()->create();
    $other = Organization::factory()->create();

    $stranger = User::factory()->forOrganization($other)->create();

    $this->actingAs($stranger);

    subscribe($organization)->assertForbidden();
});

// A membership that no longer exists is not a membership. Somebody removed from
// the organization keeps a browser tab open, and the socket has to close behind
// them rather than carrying on until they reload.
it('ChannelAuthorizationRemoved refuses a member who was removed from the organization', function (): void {
    $organization = Organization::factory()->create();
    $user = User::factory()->forOrganization($organization)->create();

    $this->actingAs($user);
    subscribe($organization)->assertOk();

    OrganizationMembership::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $user->id)
        ->delete();

    subscribe($organization)->assertForbidden();
});

// ChannelActiveOrganization: without an organization in context there is no
// permission to read one, so no channel is joined either. The membership rows
// stay exactly where they were — it is the acting organization that is missing.
it('ChannelActiveOrganization refuses a member with no organization in context', function (): void {
    $organization = Organization::factory()->create();
    $second = Organization::factory()->create();

    $user = User::factory()->forOrganization($organization)->create();
    OrganizationMembership::factory()->create([
        'organization_id' => $second->id,
        'user_id' => $user->id,
    ]);

    $user->forceFill(['current_organization_id' => null])->save();

    $this->actingAs($user);

    subscribe($organization)->assertForbidden();
    subscribe($second)->assertForbidden();
});
