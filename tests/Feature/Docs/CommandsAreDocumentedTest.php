<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

/**
 * Plumbing nobody types. Composer lifecycle hooks fire on their own, and the
 * four sub-steps of `composer test` are only ever reached through `@`-references
 * from it — documenting them separately would tell a reader to run something the
 * gate already runs. Everything else in `scripts` is a command a human invokes,
 * so it belongs in FEATURES.md. This list lives here rather than in config
 * because it is a property of this test, not of the application.
 *
 * @var list<string>
 */
const UNDOCUMENTED_BY_DESIGN = [
    'post-autoload-dump',
    'post-update-cmd',
    'post-root-package-install',
    'post-create-project-cmd',
    'hooks:install',
    'test:lint',
    'test:type-coverage',
    'test:types',
    'test:unit',
];

/**
 * The frontend scripts a composer script wraps. `composer lint` runs
 * `bun run lint`, `composer test:knip` runs `bun run knip`; the composer form is
 * the documented one, and listing both would be two names for one command.
 *
 * @var list<string>
 */
const WRAPPED_BY_COMPOSER = ['dev', 'lint', 'test:lint', 'knip', 'test:types'];

function features(): string
{
    return (string) file_get_contents(base_path('FEATURES.md'));
}

/**
 * @param  array<string, mixed>  $scripts
 * @param  list<string>  $allowed
 * @return list<string>
 */
function undocumented(array $scripts, array $allowed, string $prefix): array
{
    $features = features();

    return collect(array_keys($scripts))
        ->reject(fn (string $name): bool => in_array($name, $allowed, true))
        ->reject(fn (string $name): bool => str_contains($features, $prefix.$name))
        ->values()
        ->all();
}

/**
 * @return array<string, mixed>
 */
function manifest(string $file): array
{
    /** @var array{scripts: array<string, mixed>} $decoded */
    $decoded = json_decode((string) file_get_contents(base_path($file)), true, flags: JSON_THROW_ON_ERROR);

    return $decoded['scripts'];
}

it('documents every composer script a human runs', function (): void {
    $missing = undocumented(manifest('composer.json'), UNDOCUMENTED_BY_DESIGN, 'composer ');

    expect($missing)->toBe([], sprintf(
        'FEATURES.md never mentions: %s. Document them under Commands, or add them to UNDOCUMENTED_BY_DESIGN with a reason.',
        implode(', ', array_map(static fn (string $name): string => 'composer '.$name, $missing)),
    ));
});

it('documents every bun script a human runs', function (): void {
    $missing = undocumented(manifest('package.json'), WRAPPED_BY_COMPOSER, 'bun run ');

    expect($missing)->toBe([], sprintf(
        'FEATURES.md never mentions: %s. Document them under Commands, or add them to WRAPPED_BY_COMPOSER with a reason.',
        implode(', ', array_map(static fn (string $name): string => 'bun run '.$name, $missing)),
    ));
});

it('documents every first-party artisan command', function (): void {
    $features = features();

    // By namespace rather than by class name: `laravel/mcp` also ships a
    // `MakeResourceCommand`, and matching on the basename would claim it.
    $missing = collect(Artisan::all())
        ->filter(fn (SymfonyCommand $command): bool => str_starts_with($command::class, 'App\\Console\\Commands\\'))
        ->keys()
        ->reject(fn (string $name): bool => str_contains($features, 'php artisan '.$name))
        ->values()
        ->all();

    expect($missing)->toBe([], sprintf(
        'FEATURES.md never mentions: %s. Document them under Commands.',
        implode(', ', array_map(static fn (string $name): string => 'php artisan '.$name, $missing)),
    ));
});
