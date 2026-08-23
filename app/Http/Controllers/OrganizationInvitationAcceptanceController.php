<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AcceptOrganizationInvitation;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final readonly class OrganizationInvitationAcceptanceController
{
    public function show(string $token): Response
    {
        $invitation = OrganizationInvitation::findByToken($token);

        return Inertia::render('organization-invitation/show', [
            'token' => $token,
            'email' => $invitation?->email,
            'organization' => $invitation?->organization->name,
            'role' => $invitation?->role,
            'pending' => $invitation?->isPending() ?? false,
        ]);
    }

    /**
     * Accepting is deliberately strict about who is signed in: a link opened in
     * a browser signed in as somebody else logs that account out instead of
     * quietly attaching the wrong user.
     */
    public function update(Request $request, string $token, AcceptOrganizationInvitation $action): RedirectResponse
    {
        $invitation = OrganizationInvitation::findByToken($token);

        if (! $invitation instanceof OrganizationInvitation || ! $invitation->isPending()) {
            return to_route('organization-invitation-acceptance.show', ['token' => $token]);
        }

        $user = $request->user();

        if (! $user instanceof User) {
            $request->session()->put('url.intended', route('organization-invitation-acceptance.show', ['token' => $token]));

            return to_route('login');
        }

        if (! hash_equals(Str::lower($invitation->email), Str::lower($user->email))) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $request->session()->put('url.intended', route('organization-invitation-acceptance.show', ['token' => $token]));

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('That invitation belongs to another account. Sign in as :email to accept it.', [
                    'email' => $invitation->email,
                ]),
            ]);

            return to_route('login');
        }

        $action->handle($invitation, $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Invitation accepted.'),
        ]);

        return to_route('dashboard');
    }
}
