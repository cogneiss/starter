<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Imports\ImportRegistry;
use App\Jobs\ParseImportBatch;
use App\Jobs\ScanTempUpload;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\TempUpload;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Uploading a file and reading what became of it.
 *
 * Nothing here opens the file. The request stores the bytes, records an upload
 * nobody trusts yet, and hands both to the queue — a request that parsed a
 * hundred thousand lines would time out, and a request that read a file before
 * the scanner had seen it would make the scanner pointless.
 *
 * Every route resolves its record through a scoped query, so an id from another
 * organization, or from a colleague, is not found rather than refused.
 */
final readonly class ImportController
{
    public function create(string $import, ImportRegistry $imports): Response
    {
        Gate::authorize('imports.run');

        return Inertia::render('import/create', [
            'import' => $imports->get($import)->key(),
            'columns' => $imports->get($import)->columns(),
        ]);
    }

    public function store(
        Request $request,
        string $import,
        ImportRegistry $imports,
        OrganizationContext $context,
        #[CurrentUser] User $user,
    ): RedirectResponse {
        Gate::authorize('imports.run');

        $key = $imports->get($import)->key();

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $file = $request->file('file');
        assert($file instanceof UploadedFile);

        $upload = TempUpload::query()->create([
            'user_id' => $user->id,
            'disk' => 'temp-uploads',
            'path' => (string) $file->store('imports', 'temp-uploads'),
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'expires_at' => now()->addHours(config()->integer('uploads.ttl_hours')),
        ]);

        $batch = ImportBatch::query()->create([
            'user_id' => $user->id,
            'temp_upload_id' => $upload->id,
            'import' => $key,
        ]);

        $organizationId = (string) $context->id();

        ScanTempUpload::dispatch($upload->id, $organizationId);
        ParseImportBatch::dispatch($batch->id, $organizationId, $user->id);

        return to_route('import.show', $batch->id);
    }

    public function show(Request $request, string $batch): Response
    {
        $record = ImportBatch::ownedBy($request->user())->with('rows')->findOrFail($batch);

        Gate::authorize('view', $record);

        return Inertia::render('import/show', [
            'batch' => [
                'id' => $record->id,
                'import' => $record->import,
                'status' => $record->status,
                'imported' => $record->rows->where('status', ImportRow::IMPORTED)->count(),
                'failed' => $record->rows->where('status', ImportRow::FAILED)->count(),
            ],
            'failures' => $record->rows
                ->where('status', ImportRow::FAILED)
                ->sortBy('line_number')
                ->map(fn (ImportRow $row): array => [
                    'line_number' => $row->line_number,
                    'data' => $row->data,
                    'errors' => $row->errors ?? [],
                ])
                ->values()
                ->all(),
        ]);
    }
}
