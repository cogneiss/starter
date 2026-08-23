<?php

declare(strict_types=1);

use App\Concerns\BelongsToOrganization;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

/**
 * Policy methods that deliberately skip one of the two gates. Add a
 * `Class@method` entry here with a comment saying why, never a blanket skip.
 */
$exceptions = [
    //
];

/**
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

it('gives every organization-scoped model a policy', function (): void {
    $models = classesIn(app_path('Models'), 'App\Models');

    expect($models)->not->toBeEmpty();

    foreach ($models as $model) {
        if (! in_array(BelongsToOrganization::class, class_uses_recursive($model), true)) {
            continue;
        }

        expect(Gate::getPolicyFor($model))
            ->not->toBeNull("{$model} uses BelongsToOrganization but has no policy.");
    }
});

it('checks the organization and a permission in every policy method', function () use ($exceptions): void {
    $policies = classesIn(app_path('Policies'), 'App\Policies');

    expect($policies)->not->toBeEmpty();

    foreach ($policies as $policy) {
        $reflection = new ReflectionClass($policy);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor() || $method->getDeclaringClass()->getName() !== $policy) {
                continue;
            }

            if (in_array($policy.'@'.$method->getName(), $exceptions, true)) {
                continue;
            }

            $file = (array) file((string) $reflection->getFileName());
            $body = implode('', array_slice(
                $file,
                $method->getStartLine() - 1,
                $method->getEndLine() - $method->getStartLine() + 1,
            ));

            $where = $policy.'@'.$method->getName();

            expect($body)->toMatch('/context->id\(\)|organization_id/', "{$where} does not check the organization.")
                ->and($body)->toMatch('/->can\(|->hasPermissionTo\(/', "{$where} does not check a permission.");
        }
    }
});
