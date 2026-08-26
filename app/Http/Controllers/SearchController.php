<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SearchResources;
use App\Data\SearchGroupData;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Backs the command palette. It answers JSON rather than an Inertia page: the
 * palette fetches it on every keystroke and never navigates to it.
 */
final readonly class SearchController
{
    public function __invoke(
        Request $request,
        #[CurrentUser] User $user,
        SearchResources $search,
    ): JsonResponse {
        $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $groups = $search->handle($user, $request->string('q')->toString());

        return response()->json([
            'groups' => array_map(fn (SearchGroupData $group): array => $group->toArray(), $groups),
        ]);
    }
}
