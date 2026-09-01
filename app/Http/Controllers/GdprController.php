<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\BuildGdprExport;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class GdprController
{
    public function store(#[CurrentUser] User $user): RedirectResponse
    {
        dispatch(new BuildGdprExport($user->id));

        return back()->with('success', __('Your export is being prepared. You will be notified when it is ready.'));
    }

    /**
     * The signed middleware already refused tampered, unsigned and expired
     * links. What remains is scoping: the file is looked up inside the
     * requester's own directory, so somebody else's file name is a 404.
     */
    public function show(#[CurrentUser] User $user, string $file): BinaryFileResponse
    {
        abort_unless(preg_match('/^[a-zA-Z0-9]{40}\.zip$/', $file) === 1, 404);

        $path = "gdpr/{$user->id}/{$file}";

        abort_unless(Storage::disk('local')->exists($path), 404);

        return response()->download(Storage::disk('local')->path($path), 'personal-data-export.zip');
    }
}
