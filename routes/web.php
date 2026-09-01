<?php

declare(strict_types=1);

use App\Ai\Blocks\BlockCollection;
use App\Http\Controllers\AiBlockController;
use App\Http\Controllers\AiConfirmController;
use App\Http\Controllers\AiProposalController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BrowserSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GdprController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ImportDownloadController;
use App\Http\Controllers\ImportRetryController;
use App\Http\Controllers\ImportTemplateController;
use App\Http\Controllers\NotificationBulkController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\OrganizationAiUsageController;
use App\Http\Controllers\OrganizationApiTokenController;
use App\Http\Controllers\OrganizationApiUsageController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationInvitationAcceptanceController;
use App\Http\Controllers\OrganizationInvitationController;
use App\Http\Controllers\OrganizationMemberBulkController;
use App\Http\Controllers\OrganizationMemberController;
use App\Http\Controllers\OrganizationSwitchController;
use App\Http\Controllers\OrganizationWebhookEndpointController;
use App\Http\Controllers\SavedSearchController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserEmailResetNotificationController;
use App\Http\Controllers\UserEmailVerificationController;
use App\Http\Controllers\UserEmailVerificationNotificationController;
use App\Http\Controllers\UserImpersonationController;
use App\Http\Controllers\UserLocaleController;
use App\Http\Controllers\UserMagicLinkController;
use App\Http\Controllers\UserNotificationPreferenceController;
use App\Http\Controllers\UserPasskeyController;
use App\Http\Controllers\UserPasswordController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\UserTwoFactorAuthenticationController;
use App\Http\Controllers\WebhookDeliveryReplayController;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('welcome'))->name('home');

// A fresh session token. An expired token is the one failure the browser can
// recover from on its own: it asks here, gets the cookie reissued, and offers
// the request again rather than telling a person to reload and retype.
Route::get('csrf-token', fn () => response()->noContent())->name('csrf-token');

Route::middleware(['auth', 'verified', 'organization', 'two-factor', 'onboarded', HandlePrecognitiveRequests::class])->group(function (): void {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Onboarding... the gate lives on this group too, and excludes these two
    // routes, so the screen it sends people to is reachable from inside it.
    Route::get('onboarding', [OnboardingController::class, 'show'])
        ->name('onboarding.show');
    Route::post('onboarding/skip', [OnboardingController::class, 'store'])
        ->name('onboarding.skip');

    // Search... the command palette calls this on every keystroke, so it is
    // throttled well above a human typing speed and well below a scraper's.
    Route::get('search', SearchController::class)
        ->middleware('throttle:60,1')
        ->name('search');

    // Notifications... the inbox rides on the shared page props, so these only
    // have to mark rows read and let the next response carry the new count.
    Route::patch('notifications', NotificationBulkController::class)
        ->name('notification.update-all');
    Route::patch('notifications/{notification}', [NotificationController::class, 'update'])
        ->name('notification.update');

    Route::get('settings/notifications', [UserNotificationPreferenceController::class, 'edit'])
        ->name('user-notification-preference.edit');
    Route::patch('settings/notifications', [UserNotificationPreferenceController::class, 'update'])
        ->name('user-notification-preference.update');
});

Route::middleware(['auth', 'two-factor', HandlePrecognitiveRequests::class])->group(function (): void {
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
        Route::get('settings/organization/ai-usage', [OrganizationAiUsageController::class, 'index'])
            ->name('organization.ai-usage');
        Route::get('settings/organization/api-usage', [OrganizationApiUsageController::class, 'index'])
            ->name('organization.api-usage');

        // API Tokens...
        Route::get('settings/api-tokens', [OrganizationApiTokenController::class, 'edit'])
            ->name('api-token.edit');
        Route::post('settings/api-tokens', [OrganizationApiTokenController::class, 'store'])
            ->name('api-token.store');
        Route::delete('settings/api-tokens/{token}', [OrganizationApiTokenController::class, 'destroy'])
            ->name('api-token.destroy');

        // Webhooks...
        Route::get('settings/webhooks', [OrganizationWebhookEndpointController::class, 'edit'])
            ->name('webhook.edit');
        Route::post('settings/webhooks', [OrganizationWebhookEndpointController::class, 'store'])
            ->name('webhook.store');
        Route::patch('settings/webhooks/{endpoint}', [OrganizationWebhookEndpointController::class, 'update'])
            ->name('webhook.update');
        Route::delete('settings/webhooks/{endpoint}', [OrganizationWebhookEndpointController::class, 'destroy'])
            ->name('webhook.destroy');
        Route::post('settings/webhooks/deliveries/{delivery}/replay', WebhookDeliveryReplayController::class)
            ->name('webhook.replay');

        // Organization Members...
        Route::get('settings/members', [OrganizationMemberController::class, 'edit'])
            ->name('organization-member.edit');
        Route::post('settings/members/bulk', OrganizationMemberBulkController::class)
            ->name('organization-member.bulk');
        Route::patch('settings/members/{membership}', [OrganizationMemberController::class, 'update'])
            ->name('organization-member.update');
        Route::delete('settings/members/{membership}', [OrganizationMemberController::class, 'destroy'])
            ->name('organization-member.destroy');

        // Imports... the batch routes come first so a batch id is never read as
        // an import key.
        Route::get('settings/imports/batches/{batch}', [ImportController::class, 'show'])
            ->name('import.show');
        Route::post('settings/imports/batches/{batch}/retry', ImportRetryController::class)
            ->name('import.retry');
        Route::get('settings/imports/uploads/{upload}', ImportDownloadController::class)
            ->name('import.download');
        Route::get('settings/imports/{import}', [ImportController::class, 'create'])
            ->name('import.create');
        Route::get('settings/imports/{import}/template', ImportTemplateController::class)
            ->name('import.template');
        Route::post('settings/imports/{import}', [ImportController::class, 'store'])
            ->name('import.store');

        // Organization Invitations...
        Route::get('settings/invitations', [OrganizationInvitationController::class, 'index'])
            ->name('organization-invitation.index');
        Route::get('settings/members/invite', [OrganizationInvitationController::class, 'create'])
            ->name('organization-invitation.create');
        Route::post('settings/members/invite', [OrganizationInvitationController::class, 'store'])
            ->name('organization-invitation.store');
        Route::delete('settings/invitations/{invitation}', [OrganizationInvitationController::class, 'destroy'])
            ->name('organization-invitation.destroy');

        // Audit Log...
        Route::get('settings/audit', [AuditLogController::class, 'index'])
            ->name('audit-log.index');

        // Saved Searches...
        Route::post('settings/saved-searches', [SavedSearchController::class, 'store'])
            ->name('saved-search.store');
        Route::get('settings/saved-searches/{search}', [SavedSearchController::class, 'show'])
            ->name('saved-search.show');
        Route::patch('settings/saved-searches/{search}', [SavedSearchController::class, 'update'])
            ->name('saved-search.update');
        Route::delete('settings/saved-searches/{search}', [SavedSearchController::class, 'destroy'])
            ->name('saved-search.destroy');

        // Organization Switch...
        Route::put('organization-switch', [OrganizationSwitchController::class, 'update'])
            ->name('organization-switch.update');
    });
});

// Organization Invitation Acceptance...
Route::get('invitations/{token}', [OrganizationInvitationAcceptanceController::class, 'show'])
    ->name('organization-invitation-acceptance.show');
Route::post('invitations/{token}', [OrganizationInvitationAcceptanceController::class, 'update'])
    ->middleware(['throttle:6,1', 'friction'])
    ->name('organization-invitation-acceptance.update');

Route::middleware(['auth', 'two-factor', HandlePrecognitiveRequests::class])->group(function (): void {
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

    // Personal-data export... the download link arrives signed and expiring
    // through a notification; the file lives under the requester's own id, so
    // a foreign file name is a 404.
    Route::post('settings/profile/gdpr-export', [GdprController::class, 'store'])
        ->middleware('not-impersonating')
        ->name('gdpr-export.store');
    Route::get('settings/profile/gdpr-export/{file}', [GdprController::class, 'show'])
        ->middleware('signed')
        ->name('gdpr-export.download');

    // User Password...
    Route::get('settings/password', [UserPasswordController::class, 'edit'])
        ->middleware('not-impersonating')
        ->name('password.edit');
    Route::put('settings/password', [UserPasswordController::class, 'update'])
        ->middleware(['throttle:6,1', 'not-impersonating'])
        ->name('password.update');

    // Appearance...
    Route::get('settings/appearance', fn () => Inertia::render('appearance/update'))->name('appearance.edit');

    // Locale... an explicit choice is a write, so it is a PUT rather than a
    // link, and it is remembered on the person rather than in this tab.
    Route::put('settings/locale', UserLocaleController::class)
        ->name('user-locale.update');

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

Route::middleware(['guest', HandlePrecognitiveRequests::class])->group(function (): void {
    // User...
    Route::get('register', [UserController::class, 'create'])
        ->name('register');
    Route::post('register', [UserController::class, 'store'])
        ->middleware('friction')
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
        ->middleware(['throttle:6,1', 'friction'])
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

Route::middleware(['auth', HandlePrecognitiveRequests::class])->group(function (): void {
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
