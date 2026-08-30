<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;

/**
 * A key with no translation renders as the key.
 *
 * The application ships one translation source — the PHP files under `lang/` —
 * so a locale with no file of its own has no strings at all. What the screen
 * must not do is go blank or quietly fall back to English: it shows the key, and
 * the missing string names itself.
 */
it('renders the key itself when the active locale translates nothing', function (): void {
    config()->set('app.supported_locales', ['en', 'nl', 'fr']);

    $organization = Organization::factory()->create();

    $this->actingAs(
        User::factory()->forOrganization($organization)->create(['locale' => 'fr'])
    );

    visit('/dashboard')
        ->wait(1)
        ->assertSee('nav.dashboard')
        ->assertDontSee('Overzicht')
        ->assertNoJavaScriptErrors();
});

it('renders the translated string when the active locale has one', function (): void {
    $organization = Organization::factory()->create();

    $this->actingAs(
        User::factory()->forOrganization($organization)->create(['locale' => 'nl'])
    );

    visit('/dashboard')
        ->wait(1)
        ->assertSee('Overzicht')
        ->assertDontSee('nav.dashboard')
        ->assertNoJavaScriptErrors();
});
