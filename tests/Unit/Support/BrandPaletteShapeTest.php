<?php

declare(strict_types=1);

use App\Support\BrandPalette;

/**
 * The tokens an interface needs before it can be themed at all. A palette that
 * omits one of these looks fine on the page that does not use it and breaks on
 * the page that does, so the list is asserted whole rather than sampled.
 *
 * @var list<string>
 */
const EXPECTED_TOKENS = [
    'accent',
    'accent-active',
    'accent-hover',
    'border',
    'border-strong',
    'danger',
    'foreground',
    'foreground-muted',
    'on-accent',
    'on-danger',
    'on-primary',
    'on-success',
    'on-warning',
    'primary',
    'primary-active',
    'primary-hover',
    'success',
    'surface',
    'surface-raised',
    'surface-sunken',
    'warning',
];

it('emits the same complete token set for light and dark', function (): void {
    $palette = BrandPalette::from('#3366FF', '#FF9900');

    expect(array_keys($palette))->toBe(['light', 'dark']);

    foreach ($palette as $tokens) {
        $names = array_keys($tokens);
        sort($names);

        expect($names)->toBe(EXPECTED_TOKENS);

        foreach ($tokens as $value) {
            expect($value)->toMatch('/^oklch\(\d+\.\d{4} \d+\.\d{4} \d+\.\d{2}\)$/');
        }
    }
});

it('names only tokens it emits in the pairs it promises to keep readable', function (): void {
    $tokens = BrandPalette::from('#3366FF', '#FF9900')['light'];

    expect(BrandPalette::PAIRS)->not->toBeEmpty();

    foreach (BrandPalette::PAIRS as [$foreground, $background, $minimum]) {
        expect($tokens)->toHaveKeys([$foreground, $background]);
        expect($minimum)->toBeGreaterThanOrEqual(3.0);
    }
});

it('refuses a colour it cannot read', function (string $primary, string $accent): void {
    expect(fn (): array => BrandPalette::from($primary, $accent))
        ->toThrow(InvalidArgumentException::class);
})->with([
    ['rebeccapurple', '#FF9900'],
    ['#FFF', '#FF9900'],
    ['#3366FF', '#GGGGGG'],
]);

it('refuses to measure a token that is not an oklch colour', function (): void {
    expect(fn (): float => BrandPalette::contrast('white', 'oklch(1.0000 0.0000 0.00)'))
        ->toThrow(InvalidArgumentException::class);
});
