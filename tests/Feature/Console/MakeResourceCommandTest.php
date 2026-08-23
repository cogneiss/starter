<?php

declare(strict_types=1);

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

beforeEach(function (): void {
    Process::fake();

    $this->base = sys_get_temp_dir().'/make-resource-'.getmypid();

    File::deleteDirectory($this->base);
    File::ensureDirectoryExists($this->base.'/routes');
    File::ensureDirectoryExists($this->base.'/app/Support');
    File::copy(base_path('routes/web.php'), $this->base.'/routes/web.php');
    File::copy(base_path('app/Support/PermissionCatalog.php'), $this->base.'/app/Support/PermissionCatalog.php');
});

afterEach(function (): void {
    File::deleteDirectory($this->base);
});

/**
 * @return list<string>
 */
function generatedPaths(string $base): array
{
    $paths = array_map(
        static fn (string $path): string => mb_substr($path, mb_strlen($base) + 1),
        array_map(strval(...), File::allFiles($base)),
    );

    sort($paths);

    return $paths;
}

it('generates every file of a resource', function (): void {
    $this->artisan('app:make-resource', ['name' => 'Project', '--base' => $this->base])
        ->assertSuccessful();

    $paths = generatedPaths($this->base);

    expect($paths)->toContain(
        'app/Models/Project.php',
        'database/factories/ProjectFactory.php',
        'app/Data/ProjectData.php',
        'app/Policies/ProjectPolicy.php',
        'app/Actions/CreateProject.php',
        'app/Http/Requests/CreateProjectRequest.php',
        'app/Http/Controllers/ProjectController.php',
        'app/Resources/Definitions/ProjectResource.php',
        'resources/js/pages/project/create.tsx',
        'tests/Feature/Controllers/ProjectControllerTest.php',
        'tests/Unit/Actions/CreateProjectTest.php',
        'tests/Unit/Models/ProjectTest.php',
        'tests/Unit/Data/ProjectDataTest.php',
    )
        ->and(collect($paths)->filter(fn (string $path): bool => str_contains($path, 'create_projects_table')))
        ->toHaveCount(1);

    expect(File::get($this->base.'/app/Models/Project.php'))->toContain('final class Project extends Model')
        ->and(File::get($this->base.'/app/Policies/ProjectPolicy.php'))->toContain("\$user->can('projects.create')")
        ->and(File::get($this->base.'/app/Resources/Definitions/ProjectResource.php'))->toContain("return 'projects';")
        ->and(File::get($this->base.'/resources/js/pages/project/create.tsx'))->toContain("from '@/routes/project'");
});

it('names a multi-word resource consistently', function (): void {
    $this->artisan('app:make-resource', ['name' => 'BlogPost', '--base' => $this->base])
        ->assertSuccessful();

    expect(generatedPaths($this->base))->toContain('resources/js/pages/blog-post/create.tsx')
        ->and(File::get($this->base.'/app/Resources/Definitions/BlogPostResource.php'))
        ->toContain("return 'blog-posts';", "return 'Blog posts';")
        ->and(File::get($this->base.'/app/Policies/BlogPostPolicy.php'))
        ->toContain("\$user->can('blogposts.create')");
});

it('registers the routes and the permission', function (): void {
    $this->artisan('app:make-resource', ['name' => 'Project', '--base' => $this->base])
        ->assertSuccessful();

    expect(File::get($this->base.'/routes/web.php'))
        ->toContain('use App\Http\Controllers\ProjectController;', "->name('project.create');", "->name('project.store');")
        ->and(File::get($this->base.'/app/Support/PermissionCatalog.php'))
        ->toContain("'projects.create',");
});

it('slots the controller import in alphabetically', function (): void {
    $this->artisan('app:make-resource', ['name' => 'Project', '--base' => $this->base])
        ->assertSuccessful();

    $imports = array_values(array_filter(
        explode("\n", File::get($this->base.'/routes/web.php')),
        static fn (string $line): bool => str_starts_with($line, 'use App\Http\Controllers\\'),
    ));

    $sorted = $imports;
    sort($sorted);

    expect($imports)->toBe($sorted)
        ->and($imports)->toContain('use App\Http\Controllers\ProjectController;');
});

it('appends a controller import that sorts after every existing one', function (): void {
    $this->artisan('app:make-resource', ['name' => 'Zebra', '--base' => $this->base])
        ->assertSuccessful();

    $lines = explode("\n", File::get($this->base.'/routes/web.php'));
    $at = (int) array_search('use App\Http\Controllers\ZebraController;', $lines, true);

    expect($lines[$at - 1])->toStartWith('use App\Http\Controllers\\');
});

it('leaves a routes file with no controller imports alone', function (): void {
    File::put($this->base.'/routes/web.php', "<?php\n\ndeclare(strict_types=1);\n");

    $this->artisan('app:make-resource', ['name' => 'Project', '--base' => $this->base])
        ->assertSuccessful();

    expect(File::get($this->base.'/routes/web.php'))
        ->not->toContain('use App\Http\Controllers\ProjectController;')
        ->and(File::get($this->base.'/routes/web.php'))->toContain("->name('project.create');");
});

it('does not register the same routes or permission twice', function (): void {
    $this->artisan('app:make-resource', ['name' => 'Project', '--base' => $this->base])->assertSuccessful();
    $once = [File::get($this->base.'/routes/web.php'), File::get($this->base.'/app/Support/PermissionCatalog.php')];

    $this->artisan('app:make-resource', ['name' => 'Project', '--base' => $this->base, '--force' => true])->assertSuccessful();

    expect([File::get($this->base.'/routes/web.php'), File::get($this->base.'/app/Support/PermissionCatalog.php')])
        ->toBe($once);
});

it('skips the migration when asked', function (): void {
    $this->artisan('app:make-resource', ['name' => 'Project', '--base' => $this->base, '--no-migration' => true])
        ->assertSuccessful();

    expect(generatedPaths($this->base))->toContain('app/Models/Project.php')
        ->and(collect(generatedPaths($this->base))->filter(fn (string $path): bool => str_contains($path, 'migrations')))
        ->toBeEmpty();
});

it('refuses to overwrite an existing file without --force', function (): void {
    File::ensureDirectoryExists($this->base.'/app/Models');
    File::put($this->base.'/app/Models/Project.php', '<?php // mine');

    $this->artisan('app:make-resource', ['name' => 'Project', '--base' => $this->base])
        ->expectsOutputToContain('These files already exist.')
        ->assertFailed();

    expect(File::get($this->base.'/app/Models/Project.php'))->toBe('<?php // mine');
});

it('overwrites an existing file with --force', function (): void {
    File::ensureDirectoryExists($this->base.'/app/Models');
    File::put($this->base.'/app/Models/Project.php', '<?php // mine');

    $this->artisan('app:make-resource', ['name' => 'Project', '--base' => $this->base, '--force' => true])
        ->assertSuccessful();

    expect(File::get($this->base.'/app/Models/Project.php'))->toContain('final class Project extends Model');
});

it('writes nothing on a dry run', function (): void {
    $this->artisan('app:make-resource', ['name' => 'Project', '--base' => $this->base, '--dry-run' => true])
        ->expectsOutputToContain('app/Models/Project.php')
        ->assertSuccessful();

    expect(generatedPaths($this->base))->toBe(['app/Support/PermissionCatalog.php', 'routes/web.php']);
});

it('refuses a name that is not alphabetic', function (): void {
    $this->artisan('app:make-resource', ['name' => 'Project2', '--base' => $this->base])
        ->expectsOutputToContain('has to be alphabetic')
        ->assertFailed();
});

it('hands the generated files to the formatters and the generators', function (): void {
    $this->artisan('app:make-resource', ['name' => 'Project', '--base' => $this->base])
        ->assertSuccessful();

    Process::assertRan(fn (PendingProcess $process): bool => is_array($process->command)
        && in_array('vendor/bin/pint', $process->command, true));

    Process::assertRan(fn (PendingProcess $process): bool => is_array($process->command)
        && in_array('node_modules/.bin/vp', $process->command, true));

    Process::assertRan(fn (PendingProcess $process): bool => is_array($process->command)
        && in_array('wayfinder:generate', $process->command, true));

    Process::assertRan(fn (PendingProcess $process): bool => is_array($process->command)
        && in_array('typescript:transform', $process->command, true));
});
