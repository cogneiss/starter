<?php

declare(strict_types=1);

use Illuminate\Support\Arr;

/**
 * Every shipped locale carries the same keys.
 *
 * A translation file that is missing a key does not fall back to English here —
 * the front end renders the key itself — so a half-translated locale shows
 * `nav.dashboard` on the screen. This test is the reason that cannot ship.
 *
 * @return list<string>
 */
function localeKeys(string $locale): array
{
    /** @var array<string, mixed> $messages */
    $messages = require lang_path($locale.'/ui.php');

    $keys = array_keys(Arr::dot($messages));

    sort($keys);

    return $keys;
}

it('LocaleKeyParity ships the same keys in every locale', function (): void {
    /** @var list<string> $locales */
    $locales = config()->array('app.supported_locales');

    $reference = localeKeys('en');

    expect($reference)->not->toBeEmpty();

    foreach ($locales as $locale) {
        expect(localeKeys($locale))->toBe($reference, 'The '.$locale.' locale does not carry the same keys as en.');
    }
});

it('LocaleKeyParity ships a translation file for every supported locale', function (): void {
    /** @var list<string> $locales */
    $locales = config()->array('app.supported_locales');

    foreach ($locales as $locale) {
        expect(lang_path($locale.'/ui.php'))->toBeFile();
    }
});
