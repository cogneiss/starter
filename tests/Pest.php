<?php

declare(strict_types=1);

use App\Imports\ImportRegistry;
use App\Imports\ImportRunner;
use App\Jobs\ParseImportBatch;
use App\Models\ApiToken;
use App\Models\ImportBatch;
use App\Models\OnboardingProgress;
use App\Models\Organization;
use App\Models\TempUpload;
use App\Models\User;
use App\Resources\Definitions\FakeWidgetResource;
use App\Resources\ResourceContract;
use App\Resources\ResourceRegistry;
use App\Support\AiAvailability;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Laravel\Ai\Ai;
use Laravel\Ai\Contracts\Agent;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process as SymfonyProcess;
use Tests\TestCase;

pest()->tia()->locally();

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        Str::createRandomStringsNormally();
        Str::createUuidsNormally();
        Http::preventStrayRequests();
        Process::preventStrayProcesses();
        Sleep::fake();

        $this->freezeTime();
    })
    ->in('Browser', 'Feature', 'Unit');

/**
 * Nothing under `Feature/Ai` may reach a provider. Every agent the application
 * ships is faked here with no scripted answer, so a test that forgets its own
 * fake throws instead of dialling out. The protection is central rather than
 * per-file: a new test in the directory inherits it without asking.
 */
pest()->beforeEach(function (): void {
    foreach (classesIn(app_path('Ai/Agents'), 'App\Ai\Agents') as $agent) {
        if (is_subclass_of($agent, Agent::class)) {
            Ai::fakeAgent($agent)->preventStrayPrompts();
        }
    }
})->in('Feature/Ai');

/**
 * Evals grade prompts against real providers, so they are the one suite allowed
 * out to the network — and the one suite nothing blocking depends on. With no
 * key configured there is nothing to grade.
 */
pest()->extend(TestCase::class)
    ->group('Evals')
    ->beforeEach(function (): void {
        if (AiAvailability::faked()) {
            $this->markTestSkipped('No AI provider key is configured, so there is nothing to grade.');
        }
    })
    ->in('Evals');

expect()->extend('toBeOne', fn () => $this->toBe(1));

/**
 * A committed eval fixture: the cases to grade, and the model and date they
 * were written against.
 *
 * @return array{prompt: string, provider: string, model: string, captured_at: string, cases: list<array<string, mixed>>}
 */
function evalFixture(string $name): array
{
    /** @var array{prompt: string, provider: string, model: string, captured_at: string, cases: list<array<string, mixed>>} $fixture */
    $fixture = json_decode(
        (string) file_get_contents(__DIR__.'/Fixtures/Ai/'.$name.'.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    return $fixture;
}

/**
 * The fixture's cases, each wrapped so a dataset hands the whole case to the
 * test as one argument rather than spreading its keys across parameters.
 *
 * @return list<array{0: array<string, mixed>}>
 */
function evalCases(string $name): array
{
    return array_map(fn (array $case): array => [$case], evalFixture($name)['cases']);
}

/**
 * Serialises the handful of tests that read or write this repository's own
 * `wiki/_meta/audit.json`. It is one shared file, so two parallel workers
 * otherwise overwrite each other's fixture mid-assertion.
 */
function withWikiWorklistLock(Closure $work): void
{
    withRepositoryLock('wiki-worklist', $work);
}

/**
 * Serialises the tests that mutate this checkout's own source tree. The resource
 * generator writes real routes, a real permission and a real migration before
 * putting them back, and any test that reads the working tree meanwhile sees a
 * half-generated application rather than the committed one.
 */
function withCheckoutLock(Closure $work): void
{
    withRepositoryLock('checkout', $work);
}

/**
 * One named advisory lock, held for the duration of the given work.
 */
function withRepositoryLock(string $name, Closure $work): void
{
    $handle = fopen(sys_get_temp_dir().'/starter-'.$name.'.lock', 'c');

    flock($handle, LOCK_EX);

    try {
        $work();
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

/**
 * A scratch wiki for the documentation gates. One fixture per failure mode, so a
 * fixture can be as broken as it likes without breaking the real `wiki/`.
 */
function wikiFixture(string $name): string
{
    $root = storage_path('framework/testing/wiki-'.$name);

    File::deleteDirectory($root);
    File::ensureDirectoryExists($root);

    return $root;
}

function wikiPage(string $root, string $slug, string $frontmatter, string $body = ''): void
{
    $path = $root.'/'.$slug.'.md';

    File::ensureDirectoryExists(dirname($path));
    File::put($path, "---\n".$frontmatter."\n---\n\n".$body."\n");
}

/**
 * Every class under a PSR-4 directory, sorted. The convention guards walk these.
 *
 * @return list<class-string>
 */
function classesIn(string $directory, string $namespace): array
{
    $classes = [];

    foreach (Finder::create()->files()->in($directory)->name('*.php') as $file) {
        /** @var class-string $class */
        $class = $namespace.'\\'.Str::of($file->getRelativePathname())
            ->beforeLast('.php')
            ->replace(DIRECTORY_SEPARATOR, '\\')
            ->value();

        $classes[] = $class;
    }

    sort($classes);

    return $classes;
}

/**
 * The convention guard for a resource's search surface, as one body of code.
 *
 * An interface can only insist that a method exists. It cannot insist that
 * `searchable()` names real columns or that `recordLabel()` returns anything, so
 * a definition can satisfy `ResourceContract` and still be useless — or worse,
 * blow up at query time on a column that was never there. This returns one
 * string per defect, and the shipped definitions and the deliberately incomplete
 * fixture are both judged by it.
 *
 * @return list<string>
 */
function resourceSearchDefects(ResourceContract $resource): array
{
    $model = new ($resource->model());

    /**
     * Both column lists are judged the same way, and both blow up at query time
     * on a column that was never there, so the check is written once.
     *
     * @param  list<string>  $columns
     * @return list<string>
     */
    $columnDefects = function (string $method, array $columns, string $emptyReason) use ($model): array {
        $defects = [];

        if ($columns === []) {
            $defects[] = "{$method}() is empty, so {$emptyReason}";
        }

        foreach ($columns as $column) {
            if (! str_contains($column, '.')) {
                if (! Schema::hasColumn($model->getTable(), $column)) {
                    $defects[] = "{$method}() names '{$column}', which is not a column on {$model->getTable()}";
                }

                continue;
            }

            [$name, $field] = explode('.', $column, 2);

            if (! method_exists($model, $name)) {
                $defects[] = "{$method}() names '{$column}', but ".$model::class." has no {$name} relation";

                continue;
            }

            $relation = $model->{$name}();

            if (! $relation instanceof Relation) {
                $defects[] = "{$method}() names '{$column}', but {$name}() is not a relation";

                continue;
            }

            $table = $relation->getRelated()->getTable();

            if (! Schema::hasColumn($table, $field)) {
                $defects[] = "{$method}() names '{$column}', which is not a column on {$table}";
            }
        }

        return $defects;
    };

    $defects = [
        ...$columnDefects('searchable', $resource->searchable(), 'no term can ever match this resource'),
        ...$columnDefects('sortable', $resource->sortable(), 'a list of it has no order to fall back on'),
    ];

    if (mb_trim($resource->recordLabel($resource->model()::factory()->make())) === '') {
        $defects[] = 'recordLabel() is empty, so a hit would render as a blank row';
    }

    return $defects;
}

/**
 * Runs the shipped motion module and reports what it emits.
 *
 * The module is TypeScript with no PHP counterpart, so it is executed rather
 * than read: Bun imports the same file the bundle imports and prints the style
 * for every named transition. `$reduced` installs the `matchMedia` answer a
 * browser gives when the operating system is asking for reduced motion, which
 * is the only input the module takes.
 *
 * Symfony's process is used directly because the facade is faked for every
 * test in this suite, and this one genuinely has to run a program.
 *
 * @return array<string, array<string, string>>
 */
function motionStyles(bool $reduced): array
{
    $script = str_replace(
        ['__REDUCED__', '__MODULE__'],
        [$reduced ? 'true' : 'false', base_path('resources/js/lib/motion.ts')],
        <<<'JS_WRAP'
        globalThis.matchMedia = () => ({ matches: __REDUCED__ });

        const { motionTransitions, transitionStyle } = await import('__MODULE__');

        console.log(
            JSON.stringify(
                Object.fromEntries(
                    Object.keys(motionTransitions).map((name) => [
                        name,
                        transitionStyle(name),
                    ]),
                ),
            ),
        );
        JS_WRAP
    );

    $process = new SymfonyProcess(['bun', '-e', $script], base_path());
    $process->run();

    if (! $process->isSuccessful()) {
        throw new RuntimeException('bun could not run the motion module: '.$process->getErrorOutput());
    }

    /** @var array<string, array<string, string>> $styles */
    $styles = json_decode(mb_trim($process->getOutput()), true, flags: JSON_THROW_ON_ERROR);

    return $styles;
}

/**
 * A live API token for an organization, and the Authorization header a client
 * would send with it. The plaintext exists only in the returned header string.
 *
 * @param  list<string>  $abilities
 * @return array{0: ApiToken, 1: string}
 */
function apiBearer(Organization $organization, array $abilities = ['read:users'], ?User $user = null): array
{
    $plain = Str::random(40);

    $token = ApiToken::factory()->create([
        'organization_id' => $organization->id,
        'tokenable_id' => ($user ?? User::factory()->forOrganization($organization)->create())->id,
        'token' => hash('sha256', $plain),
        'abilities' => $abilities,
    ]);

    return [$token, 'Bearer '.$token->id.'|'.$plain];
}

/**
 * Swaps in a registry that discovered one extra resource nobody shipped.
 *
 * The shipped definitions are copied to a scratch directory, a fake definition
 * is written beside them, and the container's registry is replaced with one
 * reading that directory — so anything derived from the registry (abilities,
 * catalogue, routes) must pick the fake up with zero production-code changes.
 */
function withFakeResource(): string
{
    $directory = storage_path('framework/testing/resource-definitions');

    File::ensureDirectoryExists($directory);

    foreach (glob(app_path('Resources/Definitions/*.php')) ?: [] as $file) {
        File::copy($file, $directory.'/'.basename($file));
    }

    File::put($directory.'/FakeWidgetResource.php', <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace App\Resources\Definitions;

        use App\Data\UserData;
        use App\Models\Organization;
        use App\Models\User;
        use App\Resources\ResourceContract;
        use App\Resources\ScopedToOrganization;
        use Illuminate\Database\Eloquent\Builder;
        use Illuminate\Database\Eloquent\Model;

        final class FakeWidgetResource implements ResourceContract
        {
            use ScopedToOrganization;

            public function key(): string
            {
                return 'fake-widgets';
            }

            public function label(): string
            {
                return 'Fake widgets';
            }

            public function model(): string
            {
                return User::class;
            }

            public function dataClass(): string
            {
                return UserData::class;
            }

            public function policy(): ?string
            {
                return null;
            }

            public function url(Model $record): string
            {
                return route('user-profile.edit');
            }

            public function searchable(): array
            {
                return ['name', 'email'];
            }

            public function sortable(): array
            {
                return ['created_at', 'name'];
            }

            public function filters(): array
            {
                return [];
            }

            public function columns(): array
            {
                return [];
            }

            public function recordLabel(Model $record): string
            {
                assert($record instanceof User);

                return $record->name;
            }

            public function recordDescription(Model $record): ?string
            {
                return null;
            }

            public function scopedQuery(): Builder
            {
                return $this->scopedToOrganization(
                    fn (Organization $organization): Builder => $organization->users()->getQuery(),
                );
            }
        }
        PHP);

    if (! class_exists(FakeWidgetResource::class, false)) {
        require_once $directory.'/FakeWidgetResource.php';
    }

    app()->instance(ResourceRegistry::class, new ResourceRegistry(directory: $directory));

    return 'fake-widgets';
}

/**
 * An owner who has not been through onboarding yet.
 *
 * The user factory settles the checklist for everybody else — an established
 * member has already dealt with it — so the onboarding tests take that decision
 * back off the record and meet the gate the way a new owner does.
 *
 * @return array{0: User, 1: Organization}
 */
function ownerBeforeOnboarding(): array
{
    $organization = Organization::factory()->create();
    $owner = User::factory()->forOrganization($organization)->create();

    OnboardingProgress::withoutOrganizationScope()->delete();

    return [$owner, $organization];
}

/**
 * An uploaded CSV in the state the scanner left it, and the batch that reads it.
 *
 * Every import test starts from a file on the temp uploads disk rather than from
 * rows, so the parsing seam is exercised rather than assumed.
 */
function uploadedImport(
    User $user,
    Organization $organization,
    string $csv,
    string $state = 'clean',
): ImportBatch {
    $path = 'imports/'.Str::uuid()->toString().'.csv';

    Storage::disk('temp-uploads')->put($path, $csv);

    $factory = TempUpload::factory();

    $scanned = match ($state) {
        'clean' => $factory->clean(),
        'infected' => $factory->infected(),
        default => $factory,
    };

    $upload = $scanned->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'path' => $path,
        'size' => mb_strlen($csv),
    ]);

    return ImportBatch::factory()->create([
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'temp_upload_id' => $upload->id,
    ]);
}

/**
 * Run one batch the way the worker would, with nothing bound in advance.
 */
function runImport(ImportBatch $batch, User $user, bool $onlyFailures = false): void
{
    new ParseImportBatch($batch->id, $batch->organization_id, $user->id, $onlyFailures)
        ->handle(resolve(ImportRegistry::class), resolve(ImportRunner::class));
}
