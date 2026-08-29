<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;
use App\Support\BrandPalette;
use Illuminate\Support\Facades\Schema;

it('keeps the two brand hexes on the organization', function (): void {
    expect(Schema::hasColumns('organizations', ['brand_primary_color', 'brand_accent_color']))->toBeTrue();
});

it('paints the organization palette into the document that carries the page', function (): void {
    $organization = Organization::factory()->create([
        'brand_primary_color' => '#7C3AED',
        'brand_accent_color' => '#059669',
    ]);

    $palette = BrandPalette::from('#7C3AED', '#059669');

    $response = $this->actingAs(User::factory()->forOrganization($organization)->create())
        ->get(route('dashboard'));

    $response->assertOk();

    foreach ($palette['light'] as $token => $value) {
        $response->assertSee(sprintf('--brand-%s: %s;', $token, $value), false);
    }

    foreach ($palette['dark'] as $token => $value) {
        $response->assertSee(sprintf('--brand-%s: %s;', $token, $value), false);
    }

    // In the document itself, not in a prop: a palette that arrives with the
    // page arrives after the reader has already seen the wrong one.
    $response->assertSee(':root', false);
    $response->assertSee('html.dark', false);
});

it('falls back to the product palette for an organization that never chose colours', function (): void {
    $organization = Organization::factory()->create();

    $palette = BrandPalette::from(BrandPalette::DEFAULT_PRIMARY, BrandPalette::DEFAULT_ACCENT);

    $this->actingAs(User::factory()->forOrganization($organization)->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(sprintf('--brand-primary: %s;', $palette['light']['primary']), false);
});
