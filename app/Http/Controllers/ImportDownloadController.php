<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\TempUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The uploaded bytes, and only once something has vouched for them.
 *
 * The promotion stamp is part of the query rather than a check on the row that
 * came back, so an upload still in quarantine is not found.
 */
final readonly class ImportDownloadController
{
    public function __invoke(Request $request, string $upload): StreamedResponse
    {
        $record = TempUpload::ownedBy($request->user())
            ->whereNotNull('promoted_at')
            ->findOrFail($upload);

        Gate::authorize('view', $record);

        return Storage::disk($record->disk)->download($record->path, $record->original_name);
    }
}
