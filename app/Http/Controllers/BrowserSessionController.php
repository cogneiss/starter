<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\DeleteOtherBrowserSessions;
use App\Http\Requests\DeleteBrowserSessionRequest;
use App\Models\LoginHistory;
use App\Models\User;
use App\Support\BrowserSession;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class BrowserSessionController
{
    public function show(Request $request, #[CurrentUser] User $user): Response
    {
        return Inertia::render('browser-session/show', [
            'sessions' => BrowserSession::forUser($user, $request->session()->getId()),
            'logins' => LoginHistory::query()
                ->where('user_id', $user->id)
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (LoginHistory $login): array => [
                    'id' => $login->id,
                    'ip_address' => $login->ip_address,
                    'device' => BrowserSession::device($login->user_agent),
                    'successful' => $login->successful,
                    'created_at_diff' => $login->created_at->diffForHumans(),
                ])
                ->all(),
        ]);
    }

    public function destroy(
        DeleteBrowserSessionRequest $request,
        #[CurrentUser] User $user,
        DeleteOtherBrowserSessions $action,
    ): RedirectResponse {
        $action->handle(
            $user,
            $request->string('password')->value(),
            $request->session()->getId(),
        );

        return back();
    }
}
