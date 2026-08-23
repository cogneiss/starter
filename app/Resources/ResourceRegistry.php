<?php

declare(strict_types=1);

namespace App\Resources;

use App\Exceptions\UnknownResource;
use Illuminate\Database\Eloquent\Model;

/**
 * Every resource adapter in the application, keyed by `key()`.
 *
 * Discovery is one directory and one naming rule: every class file in
 * `app/Resources/Definitions` that implements {@see ResourceContract}. It runs
 * once per request and can be frozen to a file with `php artisan resource:cache`,
 * which `php artisan optimize` does for you on deploy.
 */
final class ResourceRegistry
{
    /**
     * @var array<string, ResourceContract>|null
     */
    private ?array $resources = null;

    /**
     * @param  string|null  $directory  Overrides the discovery directory, and disables the cache with it.
     */
    public function __construct(
        private readonly ?string $directory = null,
        private readonly string $namespace = 'App\\Resources\\Definitions\\',
        private readonly ?string $cachePath = null,
    ) {}

    /**
     * @return array<string, ResourceContract>
     */
    public function all(): array
    {
        return $this->resources ??= $this->discover();
    }

    /**
     * @throws UnknownResource when no adapter claims the key
     */
    public function get(string $key): ResourceContract
    {
        return $this->all()[$key] ?? throw UnknownResource::key($key, $this->keys());
    }

    /**
     * @param  Model|class-string<Model>  $model
     */
    public function forModel(Model|string $model): ?ResourceContract
    {
        $class = $model instanceof Model ? $model::class : $model;

        foreach ($this->all() as $resource) {
            if ($resource->model() === $class) {
                return $resource;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->all());
    }

    public function urlFor(Model $record): ?string
    {
        return $this->forModel($record)?->url($record);
    }

    /**
     * The adapter class names, for `resource:cache` to freeze.
     *
     * @return list<class-string<ResourceContract>>
     */
    public function classes(): array
    {
        return array_values(array_map(
            fn (ResourceContract $resource): string => $resource::class,
            $this->all(),
        ));
    }

    public function cachePath(): string
    {
        return $this->cachePath ?? app()->bootstrapPath('cache/resources.json');
    }

    /**
     * @return array<string, ResourceContract>
     */
    private function discover(): array
    {
        $resources = [];

        foreach ($this->adapterClasses() as $class) {
            $resource = new $class;

            if (isset($resources[$resource->key()])) {
                throw UnknownResource::duplicateKey($resource->key(), $resources[$resource->key()]::class, $class);
            }

            $resources[$resource->key()] = $resource;
        }

        ksort($resources);

        return $resources;
    }

    /**
     * @return list<class-string<ResourceContract>>
     */
    private function adapterClasses(): array
    {
        $cached = $this->cached();

        if ($cached !== null) {
            return $cached;
        }

        $files = glob(mb_rtrim($this->directory ?? app_path('Resources/Definitions'), '/').'/*.php');

        $classes = [];

        foreach ($files === false ? [] : $files as $file) {
            $class = $this->namespace.basename($file, '.php');

            if (is_a($class, ResourceContract::class, allow_string: true)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    /**
     * @return list<class-string<ResourceContract>>|null
     */
    private function cached(): ?array
    {
        if ($this->directory !== null || ! is_file($this->cachePath())) {
            return null;
        }

        $classes = json_decode((string) file_get_contents($this->cachePath()), true);

        return is_array($classes) ? array_values(array_filter(
            $classes,
            fn (mixed $class): bool => is_string($class) && is_a($class, ResourceContract::class, allow_string: true),
        )) : null;
    }
}
