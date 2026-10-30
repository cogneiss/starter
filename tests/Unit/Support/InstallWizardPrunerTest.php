<?php

declare(strict_types=1);

use App\Support\Install\Pruner;
use Illuminate\Support\Facades\File;

/**
 * A scratch checkout with one file per kind of step.
 */
function installFixture(): string
{
    $root = sys_get_temp_dir().'/install-wizard-'.uniqid();

    File::ensureDirectoryExists($root.'/app/Layer');
    File::put($root.'/app/Layer/One.php', 'one');
    File::put($root.'/gone.txt', 'gone');
    File::put($root.'/routes.php', "keep\n// layer start\nlayer\n// layer end\nkeep\nuse Layer;\nafter\n");
    File::put($root.'/.env.example', "A=1\nLAYER_KEY=x\nORGANIZATIONS_RESOLVER=session\nB=2");
    File::put($root.'/.env', "LAYER_KEY=x\nORGANIZATIONS_RESOLVER=session\n");

    return $root;
}

function installLayers(): array
{
    return ['layer' => [
        'delete' => ['app/Layer', 'gone.txt'],
        'composer' => ['vendor/package'],
        'env' => ['LAYER_KEY'],
        'edit' => ['routes.php' => [["// layer start\n", "// layer end\n"], ["use Layer;\n"]]],
    ]];
}

it('lists every step of a layer without touching anything', function (): void {
    $root = installFixture();

    expect(new Pruner($root, installLayers())->plan('layer'))->toBe([
        'delete app/Layer',
        'delete gone.txt',
        'edit routes.php (2 cuts)',
        'drop env LAYER_KEY',
        'composer remove vendor/package',
    ])->and(is_file($root.'/gone.txt'))->toBeTrue();

    File::deleteDirectory($root);
});

it('deletes, cuts ranges and drops env keys', function (): void {
    $root = installFixture();
    $pruner = new Pruner($root, installLayers());

    $pruner->apply('layer');

    expect(file_exists($root.'/app/Layer'))->toBeFalse()
        ->and(file_exists($root.'/gone.txt'))->toBeFalse()
        ->and(File::get($root.'/routes.php'))->toBe("keep\nkeep\nafter\n")
        ->and(File::get($root.'/.env.example'))->toBe("A=1\nORGANIZATIONS_RESOLVER=session\nB=2")
        ->and($pruner->composerPackages('layer'))->toBe(['vendor/package']);

    File::deleteDirectory($root);
});

it('sets an env value where the key exists and skips a missing file', function (): void {
    $root = installFixture();
    File::delete($root.'/.env');

    new Pruner($root, [])->setEnv('ORGANIZATIONS_RESOLVER', 'single');

    expect(File::get($root.'/.env.example'))->toContain("ORGANIZATIONS_RESOLVER=single\n");

    File::deleteDirectory($root);
});

it('refuses a manifest that no longer matches the source', function (array $layer, string $message): void {
    $root = installFixture();

    expect(fn (): array => new Pruner($root, ['layer' => $layer])->plan('layer'))->toThrow(RuntimeException::class, $message);

    File::deleteDirectory($root);
})->with([
    'missing path' => [['delete' => ['nope']], 'does not exist'],
    'missing file' => [['edit' => ['nope.php' => [['x']]]], 'file does not exist'],
    'start absent' => [['edit' => ['routes.php' => [['absent']]]], 'exactly once'],
    'start repeated' => [['edit' => ['routes.php' => [['keep']]]], 'exactly once'],
    'end absent' => [['edit' => ['routes.php' => [['// layer start', 'absent']]]], 'end not found'],
    'edit key not a path' => [['edit' => [['x']]], 'file paths'],
    'range not a string' => [['edit' => ['routes.php' => [[1]]]], 'Malformed'],
]);

it('refuses an unknown layer', function (): void {
    expect(fn (): array => new Pruner('/nowhere', [])->plan('ghost'))->toThrow(RuntimeException::class, 'Unknown layer');
});
