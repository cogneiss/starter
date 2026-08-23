<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

/**
 * The generated types are committed, so the only way they can drift is if
 * somebody edits a Data class and forgets to regenerate. Running the
 * transformer and comparing catches exactly that.
 */
it('has generated types that match the Data classes', function (): void {
    $path = resource_path('js/types/generated.d.ts');
    $committed = file_get_contents($path);

    Artisan::call('typescript:transform');

    $regenerated = file_get_contents($path);
    file_put_contents($path, $committed);

    expect($regenerated)->toBe($committed);
});

it('emits a type for every payload shared with the frontend', function (string $type): void {
    expect(file_get_contents(resource_path('js/types/generated.d.ts')))
        ->toContain("export type {$type} = ");
})->with([
    'BrowserSession',
    'Impersonator',
    'LoginHistoryEntry',
    'MembershipStatus',
    'Organization',
    'OrganizationInvitation',
    'OrganizationMember',
    'Passkey',
    'User',
]);
