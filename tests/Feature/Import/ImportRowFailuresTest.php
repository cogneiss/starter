<?php

declare(strict_types=1);

use App\Models\ImportRow;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('temp-uploads');
});

/**
 * A bad line is a correction, not a rollback.
 *
 * Ninety-seven good rows are worth keeping while three are retyped, so each row
 * succeeds or fails on its own and the failures come back with the line number
 * they came from.
 */
it('ImportRowFailuresPartialSuccess imports the good lines and reports the bad ones', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    $batch = uploadedImport($owner, $organization, <<<'CSV'
        email,role
        good@example.com,Member
        not-an-email,Member
        CSV);

    runImport($batch, $owner);

    $rows = $batch->rows()->orderBy('line_number')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows[0]->status)->toBe(ImportRow::IMPORTED)
        ->and($rows[1]->status)->toBe(ImportRow::FAILED)
        ->and($rows[1]->line_number)->toBe(3)
        ->and($rows[1]->errors)->not->toBeEmpty();

    expect(OrganizationInvitation::withoutOrganizationScope()->pluck('email')->all())->toBe(['good@example.com']);

    $this->actingAs($owner)
        ->get(route('import.show', ['batch' => $batch->id]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('import/show')
            ->where('batch.imported', 1)
            ->where('batch.failed', 1)
            ->where('failures.0.line_number', 3));
});

/**
 * The Action decides what is impossible, not the importer. Whatever it throws
 * lands on the row that caused it and the rest of the file carries on.
 */
it('ImportRowFailuresExecuteException records what the writer refused', function (): void {
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();
    $member = User::factory()->forOrganization($organization, 'Member')->create();

    $batch = uploadedImport($owner, $organization, <<<CSV
        email,role
        {$member->email},Member
        fresh@example.com,Member
        CSV);

    runImport($batch, $owner);

    $rows = $batch->rows()->orderBy('line_number')->get();

    expect($rows[0]->status)->toBe(ImportRow::FAILED)
        ->and($rows[0]->errors)->not->toBeEmpty()
        ->and($rows[1]->status)->toBe(ImportRow::IMPORTED);
});
