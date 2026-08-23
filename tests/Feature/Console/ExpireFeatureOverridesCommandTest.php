<?php

declare(strict_types=1);

use App\Models\FeatureOverride;

it('deletes only overrides whose expiry has passed', function (): void {
    $expired = FeatureOverride::factory()->expired()->create();
    $live = FeatureOverride::factory()->create(['expires_at' => now()->addDay()]);
    $permanent = FeatureOverride::factory()->create();

    $this->artisan('app:expire-feature-overrides')
        ->expectsOutputToContain('Deleted 1 expired feature override(s).')
        ->assertSuccessful();

    expect(FeatureOverride::query()->pluck('id')->all())
        ->not->toContain($expired->id)
        ->toContain($live->id)
        ->toContain($permanent->id);
});
