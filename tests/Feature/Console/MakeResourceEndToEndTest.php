<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * The generator's whole promise is that its output is green on arrival, so the
 * only honest way to test it is to generate into the real application and run
 * the tests it just wrote, in a real process that picks up the new routes,
 * migration and permission. Everything it touched is put back afterwards.
 */
it('generates a resource whose own tests pass', function (): void {
    Process::preventStrayProcesses(false);

    $touched = ['routes/web.php', 'app/Support/PermissionCatalog.php'];
    $original = array_combine($touched, array_map(
        static fn (string $path): string => File::get(base_path($path)),
        $touched,
    ));

    try {
        $this->artisan('app:make-resource', ['name' => 'Widget', '--force' => true])->assertSuccessful();

        $result = Process::path(base_path())->timeout(600)->run([
            PHP_BINARY, 'artisan', 'test', '--compact', '--filter=Widget',
        ]);

        expect($result->successful())->toBeTrue($result->output().$result->errorOutput());
    } finally {
        foreach ($original as $path => $contents) {
            File::put(base_path($path), $contents);
        }

        File::delete([
            base_path('app/Models/Widget.php'),
            base_path('database/factories/WidgetFactory.php'),
            base_path('app/Data/WidgetData.php'),
            base_path('app/Policies/WidgetPolicy.php'),
            base_path('app/Actions/CreateWidget.php'),
            base_path('app/Http/Requests/CreateWidgetRequest.php'),
            base_path('app/Http/Controllers/WidgetController.php'),
            base_path('app/Resources/Definitions/WidgetResource.php'),
            base_path('tests/Feature/Controllers/WidgetControllerTest.php'),
            base_path('tests/Unit/Actions/CreateWidgetTest.php'),
            base_path('tests/Unit/Models/WidgetTest.php'),
            base_path('tests/Unit/Data/WidgetDataTest.php'),
            ...File::glob(base_path('database/migrations/*_create_widgets_table.php')),
        ]);

        File::deleteDirectory(base_path('resources/js/pages/widget'));

        Process::path(base_path())->run([PHP_BINARY, 'artisan', 'wayfinder:generate', '--with-form']);
        Process::path(base_path())->run([PHP_BINARY, 'artisan', 'typescript:transform']);
    }
})->group('slow');
