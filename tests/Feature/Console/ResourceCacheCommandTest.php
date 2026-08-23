<?php

declare(strict_types=1);

use App\Resources\Definitions\UserResource;
use App\Resources\ResourceRegistry;
use Illuminate\Support\ServiceProvider;

beforeEach(function (): void {
    $this->cachePath = base_path('tests/Fixtures/Resources/command-cache.json');

    $this->app->singleton(ResourceRegistry::class, fn (): ResourceRegistry => new ResourceRegistry(cachePath: $this->cachePath));
});

afterEach(function (): void {
    @unlink($this->cachePath);
});

/**
 * @return array<int, mixed>
 */
function cachedResources(string $path): array
{
    $classes = json_decode((string) file_get_contents($path), true);

    return is_array($classes) ? $classes : [];
}

it('writes the discovered adapters to the cache file', function (): void {
    $this->artisan('resource:cache')
        ->expectsOutputToContain('resource adapters.')
        ->assertSuccessful();

    expect(cachedResources($this->cachePath))->toContain(UserResource::class);
});

it('replaces a stale cache file rather than adding to it', function (): void {
    file_put_contents($this->cachePath, json_encode(['App\\Resources\\Definitions\\DeletedResource']));

    $this->artisan('resource:cache')->assertSuccessful();

    expect(cachedResources($this->cachePath))
        ->toHaveCount(count(resolve(ResourceRegistry::class)->classes()))
        ->not->toContain('App\\Resources\\Definitions\\DeletedResource');
});

it('removes the cache file', function (): void {
    file_put_contents($this->cachePath, json_encode([]));

    $this->artisan('resource:clear')
        ->expectsOutputToContain('Resource cache cleared.')
        ->assertSuccessful();

    expect(file_exists($this->cachePath))->toBeFalse();
});

it('is wired into optimize so a deploy caches resources', function (): void {
    expect(array_values(ServiceProvider::$optimizeCommands))->toContain('resource:cache')
        ->and(array_values(ServiceProvider::$optimizeClearCommands))->toContain('resource:clear');
});
