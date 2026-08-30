<?php

declare(strict_types=1);

use App\Models\ImportRow;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('temp-uploads');
});

/**
 * The ability is asked per row, because the rows do not all ask for the same
 * thing.
 *
 * Inviting somebody as a Member and inviting them as an Owner are two different
 * grants. Somebody who may do the first and not the second uploads one file
 * containing both, and the answer has to differ line by line — a check run once
 * for the file would either let the Owner row through or refuse the Member one.
 */
it('ImportPerRecordPolicyMixedBatch refuses the row the actor may not grant and imports the other', function (): void {
    $organization = Organization::factory()->create();

    // Somebody who may invite, but may not hand out the organization itself.
    $inviter = User::factory()->forOrganization($organization, 'Member')->create();

    resolve(OrganizationContext::class)->runAs(
        $organization,
        fn (): User => $inviter->givePermissionTo('members.invite'),
    );

    $batch = uploadedImport($inviter, $organization, <<<'CSV'
        email,role
        wants-to-own@example.com,Owner
        ordinary@example.com,Member
        CSV);

    runImport($batch, $inviter);

    $rows = $batch->rows()->orderBy('line_number')->get();

    expect($rows[0]->errors)->toBe(['You are not allowed to import that row.'])
        ->and($rows[0]->status)->toBe(ImportRow::FAILED)
        ->and($rows[1]->status)->toBe(ImportRow::IMPORTED);

    expect(OrganizationInvitation::withoutOrganizationScope()->pluck('email')->all())->toBe(['ordinary@example.com']);
});
