<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * A working copy of this checkout in a temporary directory: the committed and
 * untracked source, `.env` and the built assets, with every vendor package and
 * node_modules linked rather than copied. A file's `__DIR__` is its real path,
 * so whatever locates the autoloader from it is copied instead: Composer's own
 * autoloader, so `App\` resolves against the copy and a class generated there
 * is found, the bin proxies, and Pest, whose binary looks for it four levels up.
 */
function checkoutCopy(string $copy): void
{
    File::deleteDirectory($copy);

    $source = Process::path(base_path())->run(['git', 'ls-files', '-z', '--cached', '--others', '--exclude-standard'])->throw();

    foreach (array_filter(explode("\0", $source->output())) as $path) {
        if (is_file(base_path($path))) {
            File::ensureDirectoryExists(dirname($copy.'/'.$path));
            File::copy(base_path($path), $copy.'/'.$path);
        }
    }

    File::copy(base_path('.env'), $copy.'/.env');
    File::link(base_path('public/build'), $copy.'/public/build');
    File::link(base_path('node_modules'), $copy.'/node_modules');

    File::ensureDirectoryExists($copy.'/vendor');
    File::copy(base_path('vendor/autoload.php'), $copy.'/vendor/autoload.php');

    foreach (File::directories(base_path('vendor')) as $package) {
        if (in_array(basename($package), ['bin', 'composer', 'pestphp'], true)) {
            File::copyDirectory($package, $copy.'/vendor/'.basename($package));
        } else {
            File::link($package, $copy.'/vendor/'.basename($package));
        }
    }

    // A copy drops the executable bit the generator runs vendor/bin/pint by.
    foreach (File::files($copy.'/vendor/bin') as $proxy) {
        File::chmod($proxy->getPathname(), 0755);
    }
}

/**
 * The generator's whole promise is that its output is green on arrival, so the
 * only honest way to test it is to generate into a real application and run the
 * tests it just wrote, in a real process that picks up the new routes, migration
 * and permission. That application is a copy of this one, so the parallel
 * workers running beside it never see a half-generated tree.
 */
it('generates a resource whose own tests pass and whose code needs no formatting', function (): void {
    Process::preventStrayProcesses(false);

    $copy = sys_get_temp_dir().'/make-resource-e2e-'.getmypid();

    try {
        checkoutCopy($copy);

        test()->artisan('app:make-resource', ['name' => 'Widget', '--force' => true, '--base' => $copy])->assertSuccessful();

        $result = Process::path($copy)->timeout(600)->run([
            PHP_BINARY, 'artisan', 'test', '--compact', '--filter=Widget',
        ]);

        expect($result->successful())->toBeTrue($result->output().$result->errorOutput());

        // Generated code goes into a repository whose lint step is a gate, so a
        // stub that Pint wants to rewrite is a broken stub.
        $pint = Process::path($copy)->timeout(600)->run([
            'vendor/bin/pint', '--test',
            'app/Models/Widget.php',
            'database/factories/WidgetFactory.php',
            'app/Data/WidgetData.php',
            'app/Policies/WidgetPolicy.php',
            'app/Actions/CreateWidget.php',
            'app/Http/Requests/CreateWidgetRequest.php',
            'app/Http/Controllers/WidgetController.php',
            'app/Resources/Definitions/WidgetResource.php',
            'tests/Feature/Controllers/WidgetControllerTest.php',
            'tests/Unit/Actions/CreateWidgetTest.php',
            'tests/Unit/Models/WidgetTest.php',
            'tests/Unit/Data/WidgetDataTest.php',
            ...File::glob($copy.'/database/migrations/*_create_widgets_table.php'),
        ]);

        expect($pint->successful())->toBeTrue($pint->output().$pint->errorOutput());
    } finally {
        File::deleteDirectory($copy);
    }
})->group('slow');
