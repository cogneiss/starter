<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateApiToken;
use App\Actions\RevokeApiToken;
use App\Data\ApiTokenData;
use App\Http\Requests\StoreApiTokenRequest;
use App\Models\ApiToken;
use App\Models\User;
use App\Support\ApiAbilities;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final readonly class OrganizationApiTokenController
{
    public function edit(): Response
    {
        Gate::authorize('viewAny', ApiToken::class);

        return Inertia::render('organization/api-tokens', [
            'tokens' => ApiToken::active()
                ->latest()
                ->get()
                ->map(fn (ApiToken $token): ApiTokenData => ApiTokenData::fromModel($token)),
            'abilities' => ApiAbilities::all(),
        ]);
    }

    public function store(StoreApiTokenRequest $request, #[CurrentUser] User $user, CreateApiToken $action): RedirectResponse
    {
        Gate::authorize('create', ApiToken::class);

        $expiresAt = $request->filled('expires_at')
            ? Date::parse($request->string('expires_at')->value())
            : null;

        /** @var list<string> $abilities */
        $abilities = $request->array('abilities');

        $token = $action->handle(
            $user,
            $request->string('name')->value(),
            $abilities,
            $expiresAt,
        );

        // The one and only place the plaintext exists: a flash the redirect
        // shows once. It is never stored and never listed.
        Inertia::flash('plainTextToken', $token->plainTextToken);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('API token created.'),
        ]);

        return to_route('api-token.edit');
    }

    public function destroy(ApiToken $token, RevokeApiToken $action): RedirectResponse
    {
        Gate::authorize('delete', $token);

        $action->handle($token);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('API token revoked.'),
        ]);

        return to_route('api-token.edit');
    }
}
