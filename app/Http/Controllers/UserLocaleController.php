<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserLocaleRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

/**
 * Choosing a language.
 *
 * The choice is written to the person rather than only to the session, so it
 * survives the next sign-in on another machine, and to the session as well, so
 * the reply to this request is already in the new language.
 */
final readonly class UserLocaleController
{
    public function __invoke(UpdateUserLocaleRequest $request, #[CurrentUser] User $user): RedirectResponse
    {
        $locale = $request->locale();

        $user->forceFill(['locale' => $locale])->save();

        $request->session()->put('locale', $locale);

        return back();
    }
}
