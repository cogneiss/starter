<?php

declare(strict_types=1);

use App\Models\User;
use App\Resources\ResourceColumn;
use App\Resources\ResourceContract;
use App\Resources\ResourceRegistry;
use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;
use Tests\Fixtures\Resources\Valid\FixtureResource;

it('is satisfied by a plain implementation that returns a real path', function (): void {
    $resource = new FixtureResource;

    expect($resource)->toBeInstanceOf(ResourceContract::class)
        ->and($resource->url(new User))->toBe(route('dashboard'));
});

// Datasets resolve before the application boots, so this registry is pointed at
// the definitions directory by hand rather than through `app_path()`.
dataset('shipped resources', fn (): array => array_map(
    fn (ResourceContract $resource): array => [$resource],
    array_values(new ResourceRegistry(directory: dirname(__DIR__, 3).'/app/Resources/Definitions')->all()),
));

it('uses a plural kebab-case key', function (ResourceContract $resource): void {
    expect($resource->key())->toMatch('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/');
})->with('shipped resources');

it('has a human label', function (ResourceContract $resource): void {
    expect(mb_trim($resource->label()))->not->toBe('');
})->with('shipped resources');

it('points at a model and a data class that exist', function (ResourceContract $resource): void {
    expect(is_subclass_of($resource->model(), Model::class))->toBeTrue()
        ->and(is_subclass_of($resource->dataClass(), Data::class))->toBeTrue();
})->with('shipped resources');

it('sends a record to an in-app path', function (ResourceContract $resource): void {
    $model = $resource->model();

    expect($resource->url(new $model))->toStartWith(config()->string('app.url'));
})->with('shipped resources');

it('names real columns to search and labels a record', function (ResourceContract $resource): void {
    expect(resourceSearchDefects($resource))->toBe([]);
})->with('shipped resources');

/**
 * An export is written from these columns, so a resource with none of them
 * exports empty rows, and one that names the same key twice writes the same
 * value into two headings.
 */
it('names each export column once and labels it', function (ResourceContract $resource): void {
    $columns = $resource->columns();

    $keys = array_map(fn (ResourceColumn $column): string => $column->key, $columns);

    expect($columns)->not->toBeEmpty()
        ->and($keys)->toBe(array_values(array_unique($keys)));

    foreach ($columns as $column) {
        expect(mb_trim($column->key))->not->toBe('')
            ->and(mb_trim($column->label))->not->toBe('');
    }
})->with('shipped resources');

it('points at a policy that exists, or at nothing', function (ResourceContract $resource): void {
    $policy = $resource->policy();

    expect($policy === null || class_exists($policy))->toBeTrue();
})->with('shipped resources');
