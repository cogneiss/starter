<?php

declare(strict_types=1);

use App\Actions\SeedOrganizationRoles;
use App\Models\Organization;

it('belongs to an organization and casts its flags', function (): void {
    $organization = Organization::factory()->create();

    $owner = resolve(SeedOrganizationRoles::class)->handle($organization)['Owner'];

    expect($owner->organization?->id)->toBe($organization->id)
        ->and($owner->protected)->toBeTrue()
        ->and($owner->created_at)->not->toBeNull();
});
