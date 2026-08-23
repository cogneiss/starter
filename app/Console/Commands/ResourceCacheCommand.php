<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Resources\ResourceRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

#[Description('Freeze the discovered resource adapters into a cache file')]
#[Signature('resource:cache')]
final class ResourceCacheCommand extends Command
{
    public function handle(ResourceRegistry $resources, Filesystem $files): int
    {
        $this->callSilent('resource:clear');

        $classes = $resources->classes();

        $files->ensureDirectoryExists(dirname($resources->cachePath()));
        $files->put(
            $resources->cachePath(),
            json_encode($classes, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL,
        );

        $this->components->info(sprintf('Cached %d resource adapters.', count($classes)));

        return self::SUCCESS;
    }
}
