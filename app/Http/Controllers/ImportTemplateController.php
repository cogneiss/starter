<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Imports\ImportRegistry;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The blank file, written from the same column list the parser reads back.
 *
 * There is one column list per import and both ends read it, so a template can
 * never drift from what the importer expects.
 */
final readonly class ImportTemplateController
{
    public function __invoke(string $import, ImportRegistry $imports): StreamedResponse
    {
        Gate::authorize('imports.run');

        $columns = $imports->get($import)->columns();

        return response()->streamDownload(
            function () use ($columns): void {
                echo implode(',', $columns)."\n";
            },
            $import.'-template.csv',
            ['Content-Type' => 'text/csv'],
        );
    }
}
