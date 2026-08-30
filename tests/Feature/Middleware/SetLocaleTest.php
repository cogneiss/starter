<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;

/**
 * The active locale is decided once, in front of the request.
 *
 * Four sources answer in order — the person's saved choice, this session, the
 * browser's header, the application default — and nothing outside
 * `app.supported_locales` is ever made active, whichever source offered it.
 */
function userWithLocale(?string $locale): User
{
    return User::factory()
        ->forOrganization(Organization::factory()->create())
        ->withoutTwoFactor()
        ->create(['locale' => $locale]);
}

it('SetLocalePrefersUserPreference uses the saved choice over the browser', function (): void {
    $this->actingAs(userWithLocale('nl'))
        ->withHeader('Accept-Language', 'en')
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('locale', 'nl'));

    expect(app()->getLocale())->toBe('nl');
});

it('SetLocalePrefersUserPreference ignores a saved locale nobody supports', function (): void {
    $this->actingAs(userWithLocale('kl'))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('locale', 'en'));
});

it('SetLocaleFallsBackToSession reads the session when the person saved nothing', function (): void {
    $this->actingAs(userWithLocale(null))
        ->withSession(['locale' => 'nl'])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('locale', 'nl'));
});

it('SetLocaleFallsBackToAcceptLanguage reads the browser when nothing else answers', function (): void {
    $this->actingAs(userWithLocale(null))
        ->withHeader('Accept-Language', 'nl-NL,nl;q=0.9,en;q=0.8')
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('locale', 'nl'));
});

it('SetLocaleFallsBackToAcceptLanguage falls through to the default for an unsupported browser locale', function (): void {
    $this->actingAs(userWithLocale(null))
        ->withHeader('Accept-Language', 'kl-GL,kl;q=0.9')
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('locale', 'en'));
});

it('SetLocaleFallsBackToAcceptLanguage uses the default when the browser sends no header', function (): void {
    $this->actingAs(userWithLocale(null))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('locale', 'en'));
});

it('SetLocalePersistsExplicitChoice remembers the choice on the person and in the session', function (): void {
    $user = userWithLocale(null);

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->put(route('user-locale.update'), ['locale' => 'nl'])
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('locale', 'nl');

    expect($user->fresh()?->locale)->toBe('nl');
});

it('SetLocalePersistsExplicitChoice refuses a locale the application does not ship', function (): void {
    $user = userWithLocale(null);

    $this->actingAs($user)
        ->from(route('dashboard'))
        ->put(route('user-locale.update'), ['locale' => 'kl'])
        ->assertSessionHasErrors('locale');

    expect($user->fresh()?->locale)->toBeNull();
});
