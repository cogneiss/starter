<?php

declare(strict_types=1);

use App\Support\PrecognitionAllowlist;

/**
 * The escape hatch. Both of its limits are enforced where the list is built, so
 * neither can be dodged by the person adding the entry.
 */
it('PrecognitionAllowlist excuses the routes it names and nothing else', function (): void {
    $allowlist = new PrecognitionAllowlist(['login.store' => 'A credential oracle if probed.']);

    expect($allowlist->excuses('login.store'))->toBeTrue()
        ->and($allowlist->excuses('organization.update'))->toBeFalse();
});

it('PrecognitionAllowlist refuses an entry with no written reason', function (string $reason): void {
    expect(fn (): PrecognitionAllowlist => new PrecognitionAllowlist(['organization.update' => $reason]))
        ->toThrow(InvalidArgumentException::class, 'needs a written reason');
})->with(['', '   ', "\n\t"]);

it('PrecognitionAllowlistCap refuses to hold more routes than the cap', function (): void {
    $entries = [];

    foreach (range(1, PrecognitionAllowlist::MAXIMUM + 1) as $index) {
        $entries['route.'.$index] = 'A reason for route '.$index.'.';
    }

    expect(fn (): PrecognitionAllowlist => new PrecognitionAllowlist($entries))
        ->toThrow(InvalidArgumentException::class, 'at most '.PrecognitionAllowlist::MAXIMUM.' routes');
});

it('PrecognitionAllowlistCap holds a list exactly at the cap', function (): void {
    $entries = [];

    foreach (range(1, PrecognitionAllowlist::MAXIMUM) as $index) {
        $entries['route.'.$index] = 'A reason for route '.$index.'.';
    }

    expect(new PrecognitionAllowlist($entries)->excuses('route.1'))->toBeTrue();
});
