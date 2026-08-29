<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserNotificationPreferenceRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserNotificationPreferenceController
{
    public function edit(#[CurrentUser] User $user): Response
    {
        return Inertia::render('user-notification-preference/edit', [
            'preferences' => $user->notificationPreferences(),
        ]);
    }

    public function update(UpdateUserNotificationPreferenceRequest $request, #[CurrentUser] User $user): RedirectResponse
    {
        $user->forceFill([
            'notification_preferences' => $request->preferences(),
        ])->save();

        return back();
    }
}
