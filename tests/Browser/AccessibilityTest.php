<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Every page the kit ships, run through axe-core. A starter kit's a11y defects
 * are inherited by every application built on it, so this is a gate, not a
 * report: level 3 means even minor and best-practice violations fail.
 *
 * axe covers the checklist directly — form labels, `aria-live` on error
 * regions, one `<h1>` and no skipped heading levels, accessible names on
 * icon-only buttons, `alt` on images, positive tab indexes, and a document
 * title. The one thing it cannot check is that the title is *distinct*, so
 * every page here asserts its own, and a duplicate shows up as two datasets
 * expecting the same string.
 */
function assertAccessible(string $url, string $title): void
{
    visit($url)
        // axe's own wait stops at `networkidle`, which can land a beat before
        // React has mounted the Inertia root; axe then sees an empty document
        // and reports missing landmarks on a page that has them.
        ->wait(1)
        ->assertNoAccessibilityIssues(level: 3)
        ->assertNoJavaScriptErrors()
        ->assertTitle($title);
}

it('is accessible when signed out', function (string $url, string $title): void {
    assertAccessible($url, $title);
})->with([
    'welcome' => ['/', 'Welcome - Laravel'],
    'login' => ['/login', 'Log in - Laravel'],
    'register' => ['/register', 'Register - Laravel'],
    'forgot password' => ['/forgot-password', 'Forgot password - Laravel'],
    'magic link' => ['/magic-link', 'Email login link - Laravel'],
    'reset password' => ['/reset-password/token-placeholder', 'Reset password - Laravel'],
]);

it('is accessible when signed in', function (string $url, string $title): void {
    $this->actingAs(User::factory()->forOrganization(Organization::factory()->create())->create());

    assertAccessible($url, $title);
})->with([
    'dashboard' => ['/dashboard', 'Dashboard - Laravel'],
    'profile' => ['/settings/profile', 'Profile settings - Laravel'],
    'password' => ['/settings/password', 'Password settings - Laravel'],
    'appearance' => ['/settings/appearance', 'Appearance settings - Laravel'],
    'sessions' => ['/settings/sessions', 'Browser sessions - Laravel'],
    'organization' => ['/settings/organization', 'Organization settings - Laravel'],
    'members' => ['/settings/members', 'Members - Laravel'],
    'invite member' => ['/settings/members/invite', 'Invite member - Laravel'],
    'confirm password' => ['/user/confirm-password', 'Confirm password - Laravel'],
    'block gallery' => ['/_block-gallery', 'Block gallery - Laravel'],
]);

it('is accessible behind the password confirmation gate', function (string $url, string $title): void {
    $this->actingAs(User::factory()->forOrganization(Organization::factory()->create())->create());
    $this->session(['auth.password_confirmed_at' => time()]);

    assertAccessible($url, $title);
})->with([
    'two-factor' => ['/settings/two-factor', 'Two-Factor Authentication - Laravel'],
    'passkeys' => ['/settings/passkeys', 'Passkeys - Laravel'],
]);

it('is accessible on the two-factor challenge', function (): void {
    User::factory()->create([
        'email' => 'two-factor@example.com',
        'password' => Hash::make('password'),
        'two_factor_secret' => encrypt('secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => now(),
    ]);

    // The challenge is only reachable mid-login, so it is driven through the
    // form rather than visited directly.
    visit('/login')
        ->fill('#email', 'two-factor@example.com')
        ->fill('#password', 'password')
        ->press('Log in')
        ->assertPathIs('/two-factor-challenge')
        ->wait(1)
        ->assertNoAccessibilityIssues(level: 3)
        ->assertNoJavaScriptErrors()
        ->assertTitle('Two-factor authentication - Laravel');
});

/**
 * The palette is a layer over the page rather than a page of its own, so it is
 * opened and then audited in place: axe checks the dialog role, the focus trap
 * the dialog brings, the labelled input and the named result list.
 */
it('is accessible with the command palette open on results', function (): void {
    $organization = Organization::factory()->create();

    $this->actingAs(User::factory()->forOrganization($organization)->create());

    User::factory()->forOrganization($organization, 'Member')->create(['name' => 'Marguerite Blythe']);

    visit('/dashboard')
        ->wait(1)
        ->keys('html > body', 'Meta+k')
        ->type('[data-test="palette-input"]', 'Marguerite')
        ->waitForText('Marguerite Blythe')
        ->assertNoAccessibilityIssues(level: 3)
        ->assertNoJavaScriptErrors();
});

it('is accessible while creating a first organization', function (): void {
    $this->actingAs(User::factory()->create());

    assertAccessible('/organizations/create', 'Create an organization - Laravel');
});

it('is accessible on a pending invitation', function (): void {
    OrganizationInvitation::factory()->create([
        'token' => hash('sha256', 'invitation-token'),
    ]);

    assertAccessible('/invitations/invitation-token', 'Accept invitation - Laravel');
});

it('is accessible when the invitation is no longer usable', function (): void {
    assertAccessible('/invitations/token-placeholder', 'Invitation unavailable - Laravel');
});

it('is accessible while waiting on email verification', function (): void {
    $user = User::factory()->unverified()->forOrganization(Organization::factory()->create())->create();

    $this->actingAs($user);

    assertAccessible('/verify-email', 'Email verification - Laravel');
});
