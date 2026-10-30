<?php

declare(strict_types=1);

namespace App\Support\Install;

use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Applies one layer of `config/install.php` to a checkout: deletes its paths,
 * cuts its ranges out of the files that mention it and drops its env keys.
 * Every path and every range marker is checked before anything is touched, so
 * a manifest that drifted from the source fails loudly instead of half-pruning.
 *
 * @phpstan-type Layer array{delete: list<string>, edit: array<string, list<array{0: string, 1?: string}>>, env: list<string>, composer: list<string>}
 */
final readonly class Pruner
{
    /**
     * @param  array<mixed>  $layers  The `install.layers` manifest; each layer is shape-checked on use.
     */
    public function __construct(private string $root, private array $layers) {}

    /**
     * The steps a layer would take, in order. Throws when the manifest no
     * longer matches the source.
     *
     * @return list<string>
     */
    public function plan(string $layer): array
    {
        $definition = $this->layer($layer);
        $steps = [];

        foreach ($definition['delete'] as $path) {
            throw_unless(file_exists($this->path($path)), RuntimeException::class, "Manifest path does not exist: {$path}");
            $steps[] = "delete {$path}";
        }

        foreach ($definition['edit'] as $file => $ranges) {
            throw_unless(is_file($this->path($file)), RuntimeException::class, "Manifest file does not exist: {$file}");
            $this->cutAll(File::get($this->path($file)), $ranges, $file);
            $steps[] = sprintf('edit %s (%d cuts)', $file, count($ranges));
        }

        foreach ($definition['env'] as $key) {
            $steps[] = "drop env {$key}";
        }

        foreach ($definition['composer'] as $package) {
            $steps[] = "composer remove {$package}";
        }

        return $steps;
    }

    /**
     * @return list<string>
     */
    public function composerPackages(string $layer): array
    {
        return $this->layer($layer)['composer'];
    }

    /**
     * Delete, edit and drop env keys. The composer packages are left to the
     * caller, which owns the process.
     */
    public function apply(string $layer): void
    {
        $this->plan($layer);

        $definition = $this->layer($layer);

        foreach ($definition['edit'] as $file => $ranges) {
            File::put($this->path($file), $this->cutAll(File::get($this->path($file)), $ranges, $file));
        }

        foreach ($definition['delete'] as $path) {
            File::isDirectory($this->path($path))
                ? File::deleteDirectory($this->path($path))
                : File::delete($this->path($path));
        }

        $this->dropEnv($definition['env']);
    }

    /**
     * Set `KEY=value` in `.env.example` and `.env`, where the key exists.
     */
    public function setEnv(string $key, string $value): void
    {
        foreach (['.env.example', '.env'] as $file) {
            if (! is_file($this->path($file))) {
                continue;
            }

            File::put($this->path($file), preg_replace(
                '/^'.preg_quote($key, '/').'=.*$/m',
                addcslashes("{$key}={$value}", '\\$'),
                File::get($this->path($file)),
            ) ?? '');
        }
    }

    /**
     * @param  list<string>  $keys
     */
    private function dropEnv(array $keys): void
    {
        foreach (['.env.example', '.env'] as $file) {
            if (! is_file($this->path($file))) {
                continue;
            }

            $kept = array_filter(
                explode("\n", File::get($this->path($file))),
                static fn (string $line): bool => ! in_array(explode('=', $line, 2)[0], $keys, true),
            );

            File::put($this->path($file), implode("\n", $kept));
        }
    }

    /**
     * @param  list<array{0: string, 1?: string}>  $ranges
     */
    private function cutAll(string $contents, array $ranges, string $file): string
    {
        foreach ($ranges as $range) {
            $start = $range[0];
            $end = $range[1] ?? $start;

            throw_if($start === '', RuntimeException::class, "Manifest start is empty in {$file}");

            $parts = explode($start, $contents);

            throw_unless(count($parts) === 2, RuntimeException::class, "Manifest start must match exactly once in {$file}: {$start}");

            $tail = $start.$parts[1];
            $to = mb_strpos($tail, $end);

            throw_if($to === false, RuntimeException::class, "Manifest end not found in {$file}: {$end}");

            $contents = $parts[0].mb_substr($tail, $to + mb_strlen($end));
        }

        return $contents;
    }

    /**
     * @return Layer
     */
    private function layer(string $layer): array
    {
        $definition = $this->layers[$layer] ?? null;

        throw_unless(is_array($definition), RuntimeException::class, "Unknown layer: {$layer}");

        $edits = [];

        foreach (is_array($definition['edit'] ?? null) ? $definition['edit'] : [] as $file => $ranges) {
            throw_unless(is_string($file), RuntimeException::class, 'Edit keys must be file paths');

            foreach (is_array($ranges) ? $ranges : [] as $range) {
                throw_unless(is_array($range) && is_string($range[0] ?? null), RuntimeException::class, "Malformed edit for {$file}");

                $edits[$file][] = isset($range[1]) && is_string($range[1]) ? [$range[0], $range[1]] : [$range[0]];
            }
        }

        return [
            'delete' => $this->strings($definition['delete'] ?? null),
            'edit' => $edits,
            'env' => $this->strings($definition['env'] ?? null),
            'composer' => $this->strings($definition['composer'] ?? null),
        ];
    }

    /**
     * @return list<string>
     */
    private function strings(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, is_string(...))) : [];
    }

    private function path(string $relative): string
    {
        return $this->root.'/'.$relative;
    }
}
