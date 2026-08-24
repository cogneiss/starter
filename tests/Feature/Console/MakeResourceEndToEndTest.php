<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * The generator's whole promise is that its output is green on arrival, so the
 * only honest way to test it is to generate into the real application and run
 * the tests it just wrote, in a real process that picks up the new routes,
 * migration and permission. Everything it touched is put back afterwards, and
 * the checkout lock keeps any other worker from reading the tree meanwhile.
 */
it('generates a resource whose own tests pass and whose code needs no formatting', function (): void {
    withCheckoutLock(function (): void {
        Process::preventStrayProcesses(false);

        // Restored from the copy taken here rather than by regenerating, so a
        // failure part way through cannot leave the committed files rewritten.
        $touched = ['routes/web.php', 'app/Support/PermissionCatalog.php', 'resources/js/types/generated.d.ts'];
        $original = array_combine($touched, array_map(
            static fn (string $path): string => File::get(base_path($path)),
            $touched,
        ));

        $generated = [
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
        ];

        try {
            test()->artisan('app:make-resource', ['name' => 'Widget', '--force' => true])->assertSuccessful();

            $result = Process::path(base_path())->timeout(600)->run([
                PHP_BINARY, 'artisan', 'test', '--compact', '--filter=Widget',
            ]);

            expect($result->successful())->toBeTrue($result->output().$result->errorOutput());

            // Generated code goes into a repository whose lint step is a gate, so a
            // stub that Pint wants to rewrite is a broken stub.
            $pint = Process::path(base_path())->timeout(600)->run([
                'vendor/bin/pint', '--test', ...$generated, ...File::glob('database/migrations/*_create_widgets_table.php'),
            ]);

            expect($pint->successful())->toBeTrue($pint->output().$pint->errorOutput());
        } finally {
            foreach ($original as $path => $contents) {
                File::put(base_path($path), $contents);
            }

            File::delete([
                ...array_map(base_path(...), $generated),
                ...File::glob(base_path('database/migrations/*_create_widgets_table.php')),
            ]);

            File::deleteDirectory(base_path('resources/js/pages/widget'));

            Process::path(base_path())->run([PHP_BINARY, 'artisan', 'wayfinder:generate', '--with-form']);
        }
    });
})->group('slow');
