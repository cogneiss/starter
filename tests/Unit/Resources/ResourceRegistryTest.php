<?php

declare(strict_types=1);

use App\Exceptions\UnknownResource;
use App\Models\Organization;
use App\Models\User;
use App\Resources\Definitions\UserResource;
use App\Resources\ResourceRegistry;
use Tests\Fixtures\Resources\Valid\FixtureResource;

function fixtureRegistry(string $directory = 'Valid', string $namespace = 'Valid'): ResourceRegistry
{
    return new ResourceRegistry(
        directory: base_path('tests/Fixtures/Resources/'.$directory),
        namespace: 'Tests\\Fixtures\\Resources\\'.$namespace.'\\',
    );
}

it('discovers adapters in a directory and ignores classes that do not implement the contract', function (): void {
    $registry = fixtureRegistry();

    expect($registry->keys())->toBe(['fixtures'])
        ->and($registry->get('fixtures'))->toBeInstanceOf(FixtureResource::class);
});

it('discovers every shipped adapter', function (): void {
    $registry = new ResourceRegistry;

    expect($registry->keys())->toBe([
        'organization-invitations',
        'organization-members',
        'organizations',
        'users',
    ]);
});

it('throws when a key has no adapter, naming the keys that do exist', function (): void {
    fixtureRegistry()->get('nope');
})->throws(UnknownResource::class, 'No resource adapter is registered for the key [nope]. Known keys: fixtures.');

it('throws when two adapters claim the same key', function (): void {
    fixtureRegistry('Duplicate', 'Duplicate')->all();
})->throws(UnknownResource::class, 'Two resource adapters claim the key [clashing]');

it('resolves an adapter from a model instance', function (): void {
    $registry = new ResourceRegistry;

    expect($registry->forModel(new User))->toBeInstanceOf(UserResource::class);
});

it('resolves an adapter from a model class string', function (): void {
    $registry = new ResourceRegistry;

    expect($registry->forModel(User::class))->toBeInstanceOf(UserResource::class);
});

it('returns null for a model with no adapter', function (): void {
    expect(fixtureRegistry()->forModel(Organization::class))->toBeNull();
});

it('builds the url for a record through its adapter', function (): void {
    $registry = new ResourceRegistry;

    expect($registry->urlFor(new User))->toBe(route('user-profile.edit'));
});

it('returns no url for a model with no adapter', function (): void {
    expect(fixtureRegistry()->urlFor(new Organization))->toBeNull();
});

it('lists the adapter classes for the cache to freeze', function (): void {
    expect(fixtureRegistry()->classes())->toBe([FixtureResource::class]);
});

it('defaults the cache to the bootstrap cache directory', function (): void {
    expect((new ResourceRegistry)->cachePath())->toBe(app()->bootstrapPath('cache/resources.json'));
});

it('reads the adapters back out of the cache file instead of scanning the directory', function (): void {
    $path = base_path('tests/Fixtures/Resources/cache-round-trip.json');

    try {
        file_put_contents($path, json_encode([FixtureResource::class]));

        $registry = new ResourceRegistry(cachePath: $path);

        expect($registry->keys())->toBe(['fixtures']);
    } finally {
        @unlink($path);
    }
});

it('falls back to scanning when the cache file holds something other than a class list', function (): void {
    $path = base_path('tests/Fixtures/Resources/cache-not-a-list.json');

    try {
        file_put_contents($path, '"nonsense"');

        $registry = new ResourceRegistry(cachePath: $path);

        expect($registry->keys())->toBe([
            'organization-invitations',
            'organization-members',
            'organizations',
            'users',
        ]);
    } finally {
        @unlink($path);
    }
});

it('drops cached entries that are no longer resource adapters', function (): void {
    $path = base_path('tests/Fixtures/Resources/cache-stale.json');

    try {
        file_put_contents($path, json_encode([FixtureResource::class, 'App\\Resources\\Definitions\\DeletedResource', 42]));

        $registry = new ResourceRegistry(cachePath: $path);

        expect($registry->keys())->toBe(['fixtures']);
    } finally {
        @unlink($path);
    }
});
