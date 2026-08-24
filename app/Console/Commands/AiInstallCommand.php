<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Prepares the database for the AI layer.
 *
 * Only PostgreSQL can carry pgvector, so on any other connection this is a
 * deliberate no-op rather than an error: everything except vector search works
 * on SQLite, and `composer setup` runs this unconditionally.
 */
#[Description('Prepare the database for the AI layer')]
#[Signature('ai:install')]
final class AiInstallCommand extends Command
{
    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->components->info('Not connected to PostgreSQL — nothing to install. Vector search stays unavailable on this connection.');

            return self::SUCCESS;
        }

        Schema::ensureVectorExtensionExists();

        $this->components->info('The vector extension is installed.');

        return self::SUCCESS;
    }
}
