<?php

declare(strict_types=1);

use App\Actions\UpdateOrganization;
use App\Models\Organization;

it('updates the organization', function (): void {
    $organization = Organization::factory()->create();

    $updated = resolve(UpdateOrganization::class)->handle($organization, [
        'name' => 'Renamed',
        'slug' => 'renamed',
    ]);

    expect($updated->refresh()->name)->toBe('Renamed')
        ->and($updated->slug)->toBe('renamed');
});
