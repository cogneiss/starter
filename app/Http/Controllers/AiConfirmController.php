<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ConsumeConfirmToken;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final readonly class AiConfirmController
{
    public function store(string $token, #[CurrentUser] User $user, ConsumeConfirmToken $action): RedirectResponse
    {
        $action->handle($token, $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Done.'),
        ]);

        return back();
    }
}
