<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;

/**
 * What a precognitive request is, from the server's side.
 *
 * The messages a field shows while someone types are the same messages the
 * submission would produce, because they come from the same form request. The
 * request stops at the rules: the controller body never runs, so a live check
 * of a rename cannot rename anything.
 */

/**
 * @return array{0: User, 1: Organization}
 */
function admin(): array
{
    $organization = Organization::factory()->create(['name' => 'Acme Inc.', 'slug' => 'acme-inc']);

    return [User::factory()->forOrganization($organization)->create(), $organization];
}

it('PrecognitionRequest answers a valid field without touching the record', function (): void {
    [$user, $organization] = admin();

    $this->actingAs($user)
        ->withHeaders(['Precognition' => 'true', 'Precognition-Validate-Only' => 'name'])
        ->patch(route('organization.update'), [
            'name' => 'Renamed Inc.',
            'slug' => 'renamed-inc',
            'require_two_factor' => false,
        ])
        ->assertNoContent()
        ->assertHeader('Precognition', 'true');

    expect($organization->refresh()->name)->toBe('Acme Inc.')
        ->and($organization->slug)->toBe('acme-inc');
});

it('PrecognitionRequest returns the form request message for a bad field', function (): void {
    [$user] = admin();

    $this->actingAs($user)
        ->withHeaders(['Precognition' => 'true', 'Precognition-Validate-Only' => 'name'])
        ->patchJson(route('organization.update'), ['name' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

it('PrecognitionRequest validates only the field it was asked about', function (): void {
    [$user] = admin();

    // The slug is missing, but the request asked about the name, so a person
    // filling the first field is not told off about the second one yet.
    $this->actingAs($user)
        ->withHeaders(['Precognition' => 'true', 'Precognition-Validate-Only' => 'name'])
        ->patchJson(route('organization.update'), ['name' => 'Renamed Inc.'])
        ->assertNoContent();
});

it('PrecognitionRequest leaves an ordinary submission alone', function (): void {
    [$user, $organization] = admin();

    $this->actingAs($user)
        ->fromRoute('organization.edit')
        ->patch(route('organization.update'), [
            'name' => 'Renamed Inc.',
            'slug' => 'renamed-inc',
            'require_two_factor' => false,
        ])
        ->assertRedirectToRoute('organization.edit');

    expect($organization->refresh()->name)->toBe('Renamed Inc.');
});
