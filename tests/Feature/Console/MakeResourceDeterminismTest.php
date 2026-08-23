<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

beforeEach(function (): void {
    Process::fake();

    $this->bases = [];
});

afterEach(function (): void {
    foreach ($this->bases as $base) {
        File::deleteDirectory($base);
    }
});

/**
 * A base directory holding the two files the generator appends to.
 */
function makeResourceBase(string $suffix): string
{
    $base = sys_get_temp_dir().'/make-resource-'.getmypid().'-'.$suffix;

    File::deleteDirectory($base);
    File::ensureDirectoryExists($base.'/routes');
    File::ensureDirectoryExists($base.'/app/Support');
    File::copy(base_path('routes/web.php'), $base.'/routes/web.php');
    File::copy(base_path('app/Support/PermissionCatalog.php'), $base.'/app/Support/PermissionCatalog.php');

    return $base;
}

/**
 * Every generated file keyed by its path, with the migration's timestamp taken
 * out of the key so two runs can be compared.
 *
 * @return array<string, string>
 */
function generatedContents(string $base): array
{
    $contents = [];

    foreach (File::allFiles($base) as $file) {
        $path = mb_substr((string) $file, mb_strlen($base) + 1);

        $contents[preg_replace('/\d{4}_\d{2}_\d{2}_\d{6}_/', '', $path)] = File::get((string) $file);
    }

    ksort($contents);

    return $contents;
}

it('writes byte-identical files on every run', function (): void {
    $this->bases = [makeResourceBase('first'), makeResourceBase('second')];

    foreach ($this->bases as $base) {
        $this->artisan('app:make-resource', ['name' => 'Invoice', '--base' => $base])->assertSuccessful();
    }

    [$first, $second] = array_map(generatedContents(...), $this->bases);

    expect(array_keys($first))->toBe(array_keys($second))
        ->and($first)->toBe($second);
});

it('writes PHP that parses and follows the conventions', function (): void {
    $this->bases = [makeResourceBase('valid')];

    $this->artisan('app:make-resource', ['name' => 'Invoice', '--base' => $this->bases[0]])->assertSuccessful();

    $parser = new ParserFactory()->createForNewestSupportedVersion();
    $finder = new NodeFinder();

    $php = array_filter(
        generatedContents($this->bases[0]),
        static fn (string $_, string $path): bool => str_ends_with($path, '.php'),
        ARRAY_FILTER_USE_BOTH,
    );

    // routes/web.php and the catalog were copied in, not generated.
    unset($php['routes/web.php'], $php['app/Support/PermissionCatalog.php']);

    expect($php)->not->toBeEmpty();

    foreach ($php as $path => $contents) {
        $ast = $parser->parse($contents);

        expect($ast)->not->toBeNull("{$path} does not parse");

        expect($finder->findInstanceOf($ast, Declare_::class))
            ->not->toBeEmpty("{$path} is missing declare(strict_types=1)");

        foreach ($finder->findInstanceOf($ast, Class_::class) as $class) {
            // The migration is an anonymous class, which cannot be final.
            if (! $class->name instanceof Identifier) {
                continue;
            }

            expect($class->isFinal())->toBeTrue("{$path} declares a class that is not final");
        }

        foreach ($finder->findInstanceOf($ast, ClassMethod::class) as $method) {
            $name = $method->name->toString();

            if ($name === '__construct') {
                continue;
            }

            expect($method->getReturnType())->not->toBeNull(
                sprintf('%s() has no return type in %s', $name, $path),
            );
        }

        $debug = $finder->find($ast, static fn (Node $node): bool => $node instanceof FuncCall
            && $node->name instanceof Name
            && in_array($node->name->toString(), ['dd', 'dump', 'var_dump', 'ray'], true));

        expect($debug)->toBeEmpty("{$path} leaves a debugging call behind");
    }
});

it('writes a page whose fields are labelled and whose errors announce themselves', function (): void {
    $this->bases = [makeResourceBase('page')];

    $this->artisan('app:make-resource', ['name' => 'Invoice', '--base' => $this->bases[0]])->assertSuccessful();

    $page = File::get($this->bases[0].'/resources/js/pages/invoice/create.tsx');

    preg_match_all('/<Input\b[^>]*?\bid="([^"]+)"/s', $page, $inputs);
    preg_match_all('/<Label\b[^>]*?\bhtmlFor="([^"]+)"/s', $page, $labels);

    expect($inputs[1])->not->toBeEmpty()
        // Every field is reachable by its label, not just placed next to one.
        ->and(array_diff($inputs[1], $labels[1]))->toBe([]);

    preg_match_all('/<InputError\b(.*?)\/>/s', $page, $errors);

    expect($errors[1])->not->toBeEmpty()
        ->and(array_filter($errors[1], static fn (string $props): bool => ! str_contains($props, 'aria-live')))
        ->toBe([]);
});
