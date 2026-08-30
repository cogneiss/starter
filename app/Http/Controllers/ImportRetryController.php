<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ParseImportBatch;
use App\Models\ImportBatch;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Re-run only the lines that failed, against the rows already stored — the
 * corrected file is a fresh upload, this is the same file's leftovers.
 *
 * The batch is resolved through a scoped query, so an id from another
 * organization is not found rather than refused.
 */
final readonly class ImportRetryController
{
    public function __invoke(
        Request $request,
        string $batch,
        OrganizationContext $context,
        #[CurrentUser] User $user,
    ): RedirectResponse {
        $record = ImportBatch::ownedBy($request->user())->findOrFail($batch);

        Gate::authorize('view', $record);

        dispatch(new ParseImportBatch($record->id, (string) $context->id(), $user->id, onlyFailures: true));

        return to_route('import.show', $record->id);
    }
}
