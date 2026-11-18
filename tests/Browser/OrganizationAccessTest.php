<?php

declare(strict_types=1);

use App\Models\LoginHistory;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Pest\Browser\Api\AwaitableWebpage;

/**
 * The manual pass over organizations and access control, written down.
 *
 * Each case walks one screen the way a person does and asserts both what they
 * see and the row the interaction leaves behind. Authorization and enforcement
 * have their own feature tests; these only prove the screens work in a browser.
 */
beforeEach(function (): void {
    $this->organization = Organization::factory()->create(['name' => 'Acme Holdings']);

    $this->owner = User::factory()->withoutTwoFactor()->forOrganization($this->organization)->create(['name' => 'Olivia Owner']);

    /**
     * The test server keeps one app instance for every request, so its auth
     * guard hands each request the user object it resolved at sign-in, with a
     * currentOrganization that never sees a switch. Forgetting the guards when a
     * route matches restores what a real request gets: a user read afresh from
     * the session. actingAs cannot do this, so these cases sign in through the
     * login form.
     */
    $this->signIn = function (User $user, string $path): AwaitableWebpage {
        Event::listen(RouteMatched::class, fn () => auth()->forgetGuards());

        return visit(route('login'))
            ->fill('email', $user->email)
            ->fill('password', 'password')
            ->click('@login-button')
            ->assertNoJavaScriptErrors()
            ->navigate($path);
    };
});

it('creates an organization and lands inside it', function (): void {
    $page = ($this->signIn)($this->owner, route('organization.create'));

    $page->assertSee('Create an organization')
        ->fill('name', 'Globex Labs')
        ->click('@create-organization-button')
        ->assertPathIs('/onboarding')
        ->assertSeeIn('@organization-switcher', 'Globex Labs')
        ->assertNoJavaScriptErrors();

    $created = Organization::query()->where('name', 'Globex Labs')->sole();

    expect($this->owner->fresh()->current_organization_id)->toBe($created->id);
});

it('switches the active organization from the switcher', function (): void {
    $other = Organization::factory()->create(['name' => 'Initech Group']);

    OrganizationMembership::factory()->create([
        'organization_id' => $other->id,
        'user_id' => $this->owner->id,
    ]);

    $page = ($this->signIn)($this->owner, route('dashboard'));

    $page->assertSeeIn('@organization-switcher', 'Acme Holdings')
        ->click('@organization-switcher')
        ->click('Initech Group')
        ->assertSeeIn('@organization-switcher', 'Initech Group')
        ->assertNoJavaScriptErrors();

    expect($this->owner->fresh()->current_organization_id)->toBe($other->id);
});

it('renames the organization from its settings', function (): void {
    $this->actingAs($this->owner);

    $page = visit(route('organization.edit'));

    $page->assertSee('Update your organization name and slug')
        ->fill('name', 'Acme Worldwide')
        ->click('@update-organization-button')
        ->assertSee('Organization updated.')
        ->assertSeeIn('@organization-switcher', 'Acme Worldwide')
        ->assertNoJavaScriptErrors();

    expect($this->organization->fresh()->name)->toBe('Acme Worldwide');
});

it('invites a member and lists the pending invitation', function (): void {
    $this->actingAs($this->owner);

    $page = visit(route('organization-invitation.create'));

    $page->assertSee('Send someone an invitation to join this organization')
        ->fill('email', 'newhire@example.com')
        ->click('@send-invitation-button')
        ->assertPathIs('/settings/invitations')
        ->assertSee('Invitation sent.')
        ->assertSee('newhire@example.com')
        ->assertNoJavaScriptErrors();

    $invitation = resolve(OrganizationContext::class)->runAs(
        $this->organization,
        fn (): OrganizationInvitation => OrganizationInvitation::query()->where('email', 'newhire@example.com')->sole(),
    );

    expect($invitation->organization_id)->toBe($this->organization->id)
        ->and($invitation->accepted_at)->toBeNull();
});

it('accepts an invitation as the invitee and lands in the organization', function (): void {
    // The suite freezes time, so the form's dwell check would silently discard the submit.
    config()->set('security.friction.min_seconds', 0);

    $token = Str::random(40);

    $invitation = OrganizationInvitation::factory()->create([
        'organization_id' => $this->organization->id,
        'email' => 'invitee@example.com',
        'role' => 'Member',
        'token' => hash('sha256', $token),
        'invited_by_user_id' => $this->owner->id,
    ]);

    $invitee = User::factory()->withoutTwoFactor()->create(['email' => 'invitee@example.com']);

    $page = ($this->signIn)($invitee, route('organization-invitation-acceptance.show', $token));

    $page->assertSee('Join Acme Holdings')
        ->assertSee('This invitation was sent to invitee@example.com.')
        ->click('@accept-invitation-button')
        ->assertPathIs('/dashboard')
        ->assertSee('Invitation accepted.')
        ->assertSeeIn('@organization-switcher', 'Acme Holdings')
        ->assertNoJavaScriptErrors();

    $membership = OrganizationMembership::query()
        ->where('organization_id', $this->organization->id)
        ->where('user_id', $invitee->id)
        ->sole();

    $accepted = resolve(OrganizationContext::class)->runAs(
        $this->organization,
        fn (): ?OrganizationInvitation => $invitation->fresh(),
    );

    expect($membership->status->value)->toBe('active')
        ->and($accepted?->accepted_at)->not->toBeNull();
});

it('changes a member role and removes a member through the confirm dialog', function (): void {
    $member = User::factory()->forOrganization($this->organization, 'Member')->create(['name' => 'Beth Member']);

    $membership = OrganizationMembership::query()
        ->where('organization_id', $this->organization->id)
        ->where('user_id', $member->id)
        ->sole();

    $this->actingAs($this->owner);

    $page = visit(route('organization-member.edit'));

    $page->assertSee('Manage who belongs to this organization')
        ->assertSee('Beth Member')
        ->select(sprintf('[data-test="role-%s"]', $membership->id), 'Admin')
        ->assertSee('Member updated.')
        ->assertNoJavaScriptErrors();

    $roleOf = fn (): string => resolve(OrganizationContext::class)->runAs(
        $this->organization,
        fn (): string => $member->fresh()->roles->sole()->name,
    );

    expect($roleOf())->toBe('Admin');

    $page->click(sprintf('[data-test="select-%s"]', $membership->id))
        ->select('[data-test="bulk-action"]', 'remove')
        ->click('[data-test="bulk-apply"]')
        ->assertPresent('[data-test="confirm-dialog"]')
        ->click('[data-test="confirm-proceed"]')
        ->assertDontSeeIn('[data-test="table-body"]', 'Beth Member')
        ->assertNoJavaScriptErrors();

    expect(OrganizationMembership::query()->whereKey($membership->id)->exists())->toBeFalse();
});

it('renders the browser sessions and login history', function (): void {
    LoginHistory::factory()->create(['user_id' => $this->owner->id, 'email' => $this->owner->email]);

    $this->actingAs($this->owner);

    $page = visit(route('browser-session.show'));

    $page->assertSee('Devices that are signed in to your account')
        ->assertSee('Recent sign-ins')
        ->assertPresent('[data-test="login-history-entry"]')
        ->assertSee('Successful')
        ->assertNoJavaScriptErrors();
});

it('renders the two-factor screen', function (): void {
    $this->actingAs($this->owner)->session(['auth.password_confirmed_at' => time()]);

    $page = visit(route('two-factor.show'));

    $page->assertSee('Manage your two-factor authentication settings')
        ->assertNoJavaScriptErrors();
});

it('holds a member without two-factor on the setup screen when the organization requires it', function (): void {
    $organization = Organization::factory()->create(['require_two_factor' => true]);

    $member = User::factory()->withoutTwoFactor()->forOrganization($organization)->create();

    $this->actingAs($member)->session(['auth.password_confirmed_at' => time()]);

    $page = visit(route('dashboard'));

    $page->assertPathIs('/settings/two-factor')
        ->assertSee('Your organization requires two-factor authentication.')
        ->assertSee('Manage your two-factor authentication settings')
        ->assertNoJavaScriptErrors();
});
