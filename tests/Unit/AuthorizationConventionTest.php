<?php

declare(strict_types=1);

use App\Concerns\BelongsToOrganization;
use Illuminate\Support\Facades\Gate;

/**
 * Policy methods that deliberately skip one of the two gates. Add a
 * `Class@method` entry here with a comment saying why, never a blanket skip.
 */
$exceptions = [
    // A saved search is one person's own note about a list. Ownership is the
    // whole of the question, so there is no permission to hold and none to
    // check — the method still checks the organization.
    'App\Policies\SavedSearchPolicy@manage',
];

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
