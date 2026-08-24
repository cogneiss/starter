<?php

declare(strict_types=1);

use App\Support\PermissionCatalog;
use App\Support\PermissionDefinition;

it('names every permission as resource dot verb and describes it', function (): void {
    expect(PermissionCatalog::all())->not->toBeEmpty();

    foreach (PermissionCatalog::all() as $definition) {
        expect($definition)->toBeInstanceOf(PermissionDefinition::class)
            ->and($definition->name)->toMatch('/^[a-z]+(\.[a-z]+)+$/')
            ->and($definition->group)->not->toBe('')
            ->and($definition->label)->not->toBe('')
            ->and($definition->description)->not->toBe('');
    }
});

it('has no duplicate names', function (): void {
    $names = PermissionCatalog::names();

    expect(array_unique($names))->toBe($names);
});

it('collects the permissions for a verb', function (): void {
    expect(PermissionCatalog::endingWith('view'))
        ->toBe(['organization.view', 'members.view', 'roles.view', 'ai.view']);
});
