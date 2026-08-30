<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\TempUpload;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('temp-uploads');
});

/**
 * An id from another organization is not found.
 *
 * The boundary is the where clause the query starts from, not a comparison made
 * after the row is already in hand — so a foreign id looks exactly like an id
 * that was never issued. Refusing it instead would confirm it exists.
 */
it('TempUploadScope does not find another organization batch', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $elsewhere = Organization::factory()->create();
    $stranger = User::factory()->forOrganization($elsewhere)->create();
    $theirs = uploadedImport($stranger, $elsewhere, "email,role\na@example.com,Member\n");

    $this->actingAs($owner)
        ->get(route('import.show', ['batch' => $theirs->id]))
        ->assertNotFound();
});

it('TempUploadScope does not find a colleague batch in the same organization', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();
    $colleague = User::factory()->forOrganization($organization)->create();

    $theirs = uploadedImport($colleague, $organization, "email,role\na@example.com,Member\n");

    $this->actingAs($owner)
        ->get(route('import.show', ['batch' => $theirs->id]))
        ->assertNotFound();
});

it('TempUploadScope does not find another organization upload', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $elsewhere = Organization::factory()->create();
    $stranger = User::factory()->forOrganization($elsewhere)->create();

    $theirs = TempUpload::factory()->clean()->create([
        'organization_id' => $elsewhere->id,
        'user_id' => $stranger->id,
        'promoted_at' => now(),
    ]);

    $this->actingAs($owner)
        ->get(route('import.download', ['upload' => $theirs->id]))
        ->assertNotFound();
});

it('TempUploadScope finds your own batch', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $mine = uploadedImport($owner, $organization, "email,role\na@example.com,Member\n");

    $this->actingAs($owner)
        ->get(route('import.show', ['batch' => $mine->id]))
        ->assertOk();
});
