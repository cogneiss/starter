<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\StartImpersonation;
use App\Actions\StopImpersonation;
use App\Enums\KnownFeatures;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Laravel\Pennant\Feature;

final readonly class UserImpersonationController
{
    public function store(#[CurrentUser] User $impersonator, User $user, StartImpersonation $action): RedirectResponse
    {
        abort_unless(Feature::active(KnownFeatures::ImpersonationEnabled->value), 404);

        $action->handle($impersonator, $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('You are now impersonating :name.', ['name' => $user->name]),
        ]);

        return to_route('dashboard');
    }

    public function destroy(StopImpersonation $action): RedirectResponse
    {
        $action->handle();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Impersonation ended.'),
        ]);

        return to_route('dashboard');
    }
}
