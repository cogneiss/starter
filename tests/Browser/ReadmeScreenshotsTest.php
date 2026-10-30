<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\File;

/**
 * The README's screenshots, taken from the running kit rather than by hand, so
 * they show what the code renders today. `composer screenshots` runs this file
 * and `.github/workflows/screenshots.yml` commits the result when it changed.
 * The group is excluded from every other run: it writes into docs/images and
 * asserts nothing a browser test elsewhere does not already assert.
 */
function readmeScreenshot(string $url, string $name): void
{
    visit($url)
        ->resize(1280, 800)
        ->wait(1)
        ->assertNoJavaScriptErrors()
        ->screenshot(fullPage: false, filename: "readme-{$name}");

    File::move(
        base_path("tests/Browser/Screenshots/readme-{$name}.png"),
        base_path("docs/images/{$name}.png"),
    );
}

it('captures the sign-in page', function (): void {
    readmeScreenshot('/login', 'login');
})->group('screenshots');

it('captures the signed-in pages', function (string $url, string $name): void {
    $organization = Organization::factory()->create(['name' => 'Acme']);

    $owner = User::factory()->forOrganization($organization)->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);

    collect(['Grace Hopper', 'Alan Turing', 'Katherine Johnson'])->each(
        fn (string $member): User => User::factory()->forOrganization($organization)->create([
            'name' => $member,
            'email' => str($member)->before(' ')->lower().'@example.com',
        ]),
    );

    $this->actingAs($owner);

    readmeScreenshot($url, $name);
})->with([
    'dashboard' => ['/dashboard', 'dashboard'],
    'members' => ['/settings/members', 'members'],
])->group('screenshots');
