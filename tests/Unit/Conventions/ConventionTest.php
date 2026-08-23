<?php

declare(strict_types=1);

use App\Data\UserData;
use App\Resources\ResourceRegistry;
use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * @param  list<class-string>  $models
 * @param  array<class-string, string>  $allowed
 * @return list<string>
 */
function modelsMissingAFactory(array $models, array $allowed, Closure $hasFactory): array
{
    $missing = [];

    foreach ($models as $model) {
        if (array_key_exists($model, $allowed) || $hasFactory($model)) {
            continue;
        }

        $missing[] = <<<TEXT
        Model {$model} has no factory.

        Fix one of:
          php artisan make:factory {$model}Factory
          or add {$model}::class => '<reason>' to config/conventions.php models_without_factory
        TEXT;
    }

    return $missing;
}

/**
 * @param  list<class-string>  $models
 * @param  array<class-string, string>  $allowed
 * @param  list<class-string>  $adapted
 * @return list<string>
 */
function modelsMissingAnAdapter(array $models, array $allowed, array $adapted): array
{
    $missing = [];

    foreach ($models as $model) {
        if (array_key_exists($model, $allowed) || in_array($model, $adapted, true)) {
            continue;
        }

        $short = class_basename($model);

        $missing[] = <<<TEXT
        Model {$model} has no resource adapter.

        Fix one of:
          php artisan app:make-resource {$short}
          or add {$model}::class => '<reason>' to config/conventions.php non_resource_models
        TEXT;
    }

    return $missing;
}

/**
 * @param  list<class-string>  $classes
 * @return list<string>
 */
function classesMissingTypeScript(array $classes): array
{
    $missing = [];

    foreach ($classes as $class) {
        if (new ReflectionClass($class)->getAttributes(TypeScript::class) !== []) {
            continue;
        }

        $missing[] = "{$class} has no #[TypeScript] attribute, so it never reaches resources/js/types/generated.d.ts.";
    }

    return $missing;
}

it('gives every model a factory', function (): void {
    $models = classesIn(app_path('Models'), 'App\Models');

    expect($models)->not->toBeEmpty();

    expect(modelsMissingAFactory(
        $models,
        config()->array('conventions.models_without_factory'),
        fn (string $model): bool => class_exists(Factory::resolveFactoryName($model)),
    ))->toBe([]);
});

it('exports every data object to TypeScript', function (): void {
    $registry = new ResourceRegistry;

    $classes = [
        ...classesIn(app_path('Data'), 'App\Data'),
        ...array_map(fn (object $resource): string => $resource->dataClass(), $registry->all()),
    ];

    expect($classes)->not->toBeEmpty()
        ->and(classesMissingTypeScript(array_values(array_unique($classes))))->toBe([]);
});

it('gives every model a resource adapter', function (): void {
    $models = classesIn(app_path('Models'), 'App\Models');

    expect($models)->not->toBeEmpty();

    expect(modelsMissingAnAdapter(
        $models,
        config()->array('conventions.non_resource_models'),
        array_map(fn (object $resource): string => $resource->model(), new ResourceRegistry()->all()),
    ))->toBe([]);
});

it('reports a model with no factory until it is allowlisted', function (): void {
    $none = fn (): bool => false;

    expect(modelsMissingAFactory(['App\Models\Fixture'], [], $none))
        ->toHaveCount(1)
        ->and(modelsMissingAFactory(['App\Models\Fixture'], [], $none)[0])
        ->toContain('php artisan make:factory App\Models\FixtureFactory')
        ->and(modelsMissingAFactory(['App\Models\Fixture'], ['App\Models\Fixture' => 'because'], $none))
        ->toBe([]);
});

it('reports a model with no adapter until it is allowlisted or adapted', function (): void {
    expect(modelsMissingAnAdapter(['App\Models\Fixture'], [], []))
        ->toHaveCount(1)
        ->and(modelsMissingAnAdapter(['App\Models\Fixture'], [], [])[0])
        ->toContain('php artisan app:make-resource Fixture')
        ->and(modelsMissingAnAdapter(['App\Models\Fixture'], ['App\Models\Fixture' => 'because'], []))
        ->toBe([])
        ->and(modelsMissingAnAdapter(['App\Models\Fixture'], [], ['App\Models\Fixture']))
        ->toBe([]);
});

it('reports a data object with no TypeScript attribute', function (): void {
    expect(classesMissingTypeScript([ResourceRegistry::class]))
        ->toHaveCount(1)
        ->and(classesMissingTypeScript([UserData::class]))
        ->toBe([]);
});
