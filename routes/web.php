<?php

declare(strict_types=1);

use App\Ai\Blocks\BlockCollection;
use App\Http\Controllers\AiBlockController;
use App\Http\Controllers\AiConfirmController;
use App\Http\Controllers\AiProposalController;
use App\Http\Controllers\BrowserSessionController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationInvitationAcceptanceController;
use App\Http\Controllers\OrganizationInvitationController;
use App\Http\Controllers\OrganizationMemberController;
use App\Http\Controllers\OrganizationSwitchController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserEmailResetNotificationController;
use App\Http\Controllers\UserEmailVerificationController;
use App\Http\Controllers\UserEmailVerificationNotificationController;
use App\Http\Controllers\UserImpersonationController;
use App\Http\Controllers\UserMagicLinkController;
use App\Http\Controllers\UserPasskeyController;
use App\Http\Controllers\UserPasswordController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\UserTwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('welcome'))->name('home');

Route::middleware(['auth', 'verified', 'organization', 'two-factor'])->group(function (): void {
    Route::get('dashboard', fn () => Inertia::render('dashboard'))->name('dashboard');
});

Route::middleware(['auth', 'two-factor'])->group(function (): void {
    // Organization...
    Route::get('organizations/create', [OrganizationController::class, 'create'])
        ->name('organization.create');
    Route::post('organizations', [OrganizationController::class, 'store'])
        ->name('organization.store');

    Route::middleware('organization')->group(function (): void {
        Route::get('settings/organization', [OrganizationController::class, 'edit'])
            ->name('organization.edit');
        Route::patch('settings/organization', [OrganizationController::class, 'update'])
            ->name('organization.update');

        // Organization Members...
        Route::get('settings/members', [OrganizationMemberController::class, 'edit'])
            ->name('organization-member.edit');
        Route::patch('settings/members/{membership}', [OrganizationMemberController::class, 'update'])
            ->name('organization-member.update');
        Route::delete('settings/members/{membership}', [OrganizationMemberController::class, 'destroy'])
            ->name('organization-member.destroy');

        // Organization Invitations...
        Route::get('settings/members/invite', [OrganizationInvitationController::class, 'create'])
            ->name('organization-invitation.create');
        Route::post('settings/members/invite', [OrganizationInvitationController::class, 'store'])
            ->name('organization-invitation.store');
        Route::delete('settings/invitations/{invitation}', [OrganizationInvitationController::class, 'destroy'])
            ->name('organization-invitation.destroy');

        // Organization Switch...
        Route::put('organization-switch', [OrganizationSwitchController::class, 'update'])
            ->name('organization-switch.update');
    });
});

// Organization Invitation Acceptance...
Route::get('invitations/{token}', [OrganizationInvitationAcceptanceController::class, 'show'])
    ->name('organization-invitation-acceptance.show');
Route::post('invitations/{token}', [OrganizationInvitationAcceptanceController::class, 'update'])
    ->middleware('throttle:6,1')
    ->name('organization-invitation-acceptance.update');

Route::middleware(['auth', 'two-factor'])->group(function (): void {
    // User...
    Route::delete('user', [UserController::class, 'destroy'])
        ->middleware('not-impersonating')
        ->name('user.destroy');

    // User Profile...
    Route::redirect('settings', '/settings/profile');
    Route::get('settings/profile', [UserProfileController::class, 'edit'])->name('user-profile.edit');
    Route::patch('settings/profile', [UserProfileController::class, 'update'])
        ->middleware('not-impersonating')
        ->name('user-profile.update');

    // User Password...
    Route::get('settings/password', [UserPasswordController::class, 'edit'])
        ->middleware('not-impersonating')
        ->name('password.edit');
    Route::put('settings/password', [UserPasswordController::class, 'update'])
        ->middleware(['throttle:6,1', 'not-impersonating'])
        ->name('password.update');

    // Appearance...
    Route::get('settings/appearance', fn () => Inertia::render('appearance/update'))->name('appearance.edit');

    // User Two-Factor Authentication...
    Route::get('settings/two-factor', [UserTwoFactorAuthenticationController::class, 'show'])
        ->middleware('not-impersonating')
        ->name('two-factor.show');

    // Browser Sessions...
    Route::get('settings/sessions', [BrowserSessionController::class, 'show'])
        ->name('browser-session.show');
    Route::delete('settings/sessions', [BrowserSessionController::class, 'destroy'])
        ->middleware('not-impersonating')
        ->name('browser-session.destroy');

    // User Passkeys...
    Route::get('settings/passkeys', [UserPasskeyController::class, 'show'])
        ->middleware('not-impersonating')
        ->name('passkey.show');

    // User Impersonation...
    Route::post('users/{user}/impersonation', [UserImpersonationController::class, 'store'])
        ->middleware('not-impersonating')
        ->name('user-impersonation.store');
    Route::delete('impersonation', [UserImpersonationController::class, 'destroy'])
        ->name('user-impersonation.destroy');

    // AI Confirmations...
    Route::post('ai/confirm/{token}', [AiConfirmController::class, 'store'])
        ->name('ai-confirm.store');

    // AI Blocks...
    Route::post('ai/blocks', [AiBlockController::class, 'store'])
        ->middleware('organization')
        ->name('ai-block.store');
    Route::post('ai/proposals', [AiProposalController::class, 'store'])
        ->middleware('organization')
        ->name('ai-proposal.store');
});

Route::middleware('guest')->group(function (): void {
    // User...
    Route::get('register', [UserController::class, 'create'])
        ->name('register');
    Route::post('register', [UserController::class, 'store'])
        ->name('register.store');

    // User Password...
    Route::get('reset-password/{token}', [UserPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('reset-password', [UserPasswordController::class, 'store'])
        ->name('password.store');

    // User Email Reset Notification...
    Route::get('forgot-password', [UserEmailResetNotificationController::class, 'create'])
        ->name('password.request');
    Route::post('forgot-password', [UserEmailResetNotificationController::class, 'store'])
        ->name('password.email');

    // User Magic Link...
    Route::get('magic-link', [UserMagicLinkController::class, 'create'])
        ->name('magic-link.create');
    Route::post('magic-link', [UserMagicLinkController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('magic-link.store');
    Route::get('magic-link/{token}', [UserMagicLinkController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('magic-link.update');

    // Social Authentication...
    Route::get('auth/{provider}/redirect', [SocialAuthController::class, 'show'])
        ->name('social-auth.show');
    Route::get('auth/{provider}/callback', [SocialAuthController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('social-auth.update');

    // Session...
    Route::get('login', [SessionController::class, 'create'])
        ->name('login');
    Route::post('login', [SessionController::class, 'store'])
        ->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    // User Email Verification...
    Route::get('verify-email', [UserEmailVerificationNotificationController::class, 'create'])
        ->name('verification.notice');
    Route::post('email/verification-notification', [UserEmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // User Email Verification...
    Route::get('verify-email/{id}/{hash}', [UserEmailVerificationController::class, 'update'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    // Session...
    Route::post('logout', [SessionController::class, 'destroy'])
        ->name('logout');
});

// Value gallery... every value component with a value and with nothing. It is a
// reference page and the browser test's subject, never part of production.
if (! app()->environment('production')) {
    Route::get('_value-gallery', fn () => Inertia::render('value-gallery', [
        'now' => now()->toIso8601String(),
    ]))->name('value-gallery');

    // Block gallery... every AI block the renderer knows, plus one it does not,
    // on one page. It is the reference for what each block looks like and what
    // the browser tests read.
    Route::get('_block-gallery', fn () => Inertia::render('block-gallery', [
        'blocks' => [
            ...BlockCollection::fromPayloads([
                ['type' => 'text', 'text' => 'A sentence the agent wrote.'],
                ['type' => 'markdown', 'markdown' => "## Heading\n\nA [link](https://example.com) and `code`.<script>alert(1)</script>"],
                ['type' => 'table', 'columns' => ['Member', 'Role'], 'rows' => [['Taylor', 'Owner'], ['Jess', 'Admin']]],
                ['type' => 'list', 'ordered' => false, 'items' => ['First', 'Second', 'Third']],
                ['type' => 'metric', 'label' => 'Active members', 'value' => '128', 'delta' => '+12', 'trend' => 'up'],
                ['type' => 'form', 'action' => 'invite-member', 'values' => ['email' => 'new@example.com', 'role' => 'member']],
            ])->toArray(),
            ['type' => 'quantum-hologram', 'text' => 'A block from the future.'],
        ],
    ]))->name('block-gallery');
}
