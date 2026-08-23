<?php

declare(strict_types=1);

use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationInvitationAcceptanceController;
use App\Http\Controllers\OrganizationInvitationController;
use App\Http\Controllers\OrganizationMemberController;
use App\Http\Controllers\OrganizationSwitchController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserEmailResetNotificationController;
use App\Http\Controllers\UserEmailVerificationController;
use App\Http\Controllers\UserEmailVerificationNotificationController;
use App\Http\Controllers\UserMagicLinkController;
use App\Http\Controllers\UserPasskeyController;
use App\Http\Controllers\UserPasswordController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\UserTwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('welcome'))->name('home');

Route::middleware(['auth', 'verified', 'organization'])->group(function (): void {
    Route::get('dashboard', fn () => Inertia::render('dashboard'))->name('dashboard');
});

Route::middleware('auth')->group(function (): void {
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

Route::middleware('auth')->group(function (): void {
    // User...
    Route::delete('user', [UserController::class, 'destroy'])->name('user.destroy');

    // User Profile...
    Route::redirect('settings', '/settings/profile');
    Route::get('settings/profile', [UserProfileController::class, 'edit'])->name('user-profile.edit');
    Route::patch('settings/profile', [UserProfileController::class, 'update'])->name('user-profile.update');

    // User Password...
    Route::get('settings/password', [UserPasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [UserPasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('password.update');

    // Appearance...
    Route::get('settings/appearance', fn () => Inertia::render('appearance/update'))->name('appearance.edit');

    // User Two-Factor Authentication...
    Route::get('settings/two-factor', [UserTwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');

    // User Passkeys...
    Route::get('settings/passkeys', [UserPasskeyController::class, 'show'])
        ->name('passkey.show');
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
