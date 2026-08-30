<?php

declare(strict_types=1);

use App\Imports\ImportRegistry;
use App\Models\Organization;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * One contract describes an import, and the upload screen, the blank template
 * and the parser all read it. A column added in one place cannot go missing in
 * the other two, because there is only one place.
 */
it('ImportContract writes the template from the columns the parser reads', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $import = resolve(ImportRegistry::class)->get('organization-invitations');

    expect($import->columns())->toBe(['email', 'role']);

    $response = $this->actingAs($owner)
        ->get(route('import.template', ['import' => 'organization-invitations']));

    $response->assertOk();

    expect(mb_trim($response->streamedContent()))->toBe(implode(',', $import->columns()));
});

it('ImportContract offers the same columns on the upload screen', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $this->actingAs($owner)
        ->get(route('import.create', ['import' => 'organization-invitations']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('import/create')
            ->where('columns', ['email', 'role']));
});

it('ImportContract refuses a key nothing is registered for', function (): void {
    resolve(ImportRegistry::class)->get('nothing-registered');
})->throws(InvalidArgumentException::class);
